<?php

declare(strict_types=1);

namespace Batustun\FilamentMediaLibrary\Http\Controllers;

use Batustun\FilamentMediaLibrary\Models\Media;
use Batustun\FilamentMediaLibrary\Services\MediaService;
use Batustun\FilamentMediaLibrary\Support\Authorize;
use Batustun\FilamentMediaLibrary\Support\MediaLibraryConfig;
use Batustun\FilamentMediaLibrary\Support\SignedMedia;
use Batustun\FilamentMediaLibrary\Support\UploadGuard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use RuntimeException;
use Throwable;

/**
 * Receives a large file in slices and reassembles it.
 *
 * A single POST is capped by PHP's upload_max_filesize and post_max_size, which
 * no amount of application code can raise at runtime. Slicing in the browser
 * sidesteps both: each request carries a few megabytes, and the file only
 * becomes whole on disk.
 */
class ChunkedUploadController extends Controller
{
    /** Hard ceiling, so a crafted request cannot ask the server to track a million parts. */
    private const MAX_CHUNKS = 10000;

    public function store(Request $request, MediaService $service): JsonResponse
    {
        abort_unless((bool) $request->user(), 401);
        abort_unless(MediaLibraryConfig::chunkedUploadsEnabled(), 404);

        Authorize::ensure('upload', $request->user());

        $validated = $request->validate([
            'uuid' => ['required', 'string', 'uuid'],
            'index' => ['required', 'integer', 'min:0', 'max:'.(self::MAX_CHUNKS - 1)],
            'total' => ['required', 'integer', 'min:1', 'max:'.self::MAX_CHUNKS],
            'name' => ['required', 'string', 'max:255'],
            'chunk' => ['required', 'file'],
            'disk' => ['nullable', 'string', Rule::in(MediaLibraryConfig::disks())],
            'directory' => ['nullable', 'string', 'max:512'],
        ]);

        $uuid = (string) $validated['uuid'];
        $index = (int) $validated['index'];
        $total = (int) $validated['total'];
        $name = basename((string) $validated['name']);

        // Reject the extension before a single byte is stored, rather than
        // after the whole file has been reassembled.
        if ($index === 0 && ($violation = $this->violationForName($name))) {
            return response()->json(['ok' => false, 'message' => $violation], 422);
        }

        $directory = $this->chunkDirectory($uuid);
        File::ensureDirectoryExists($directory, 0755, true);

        $request->file('chunk')->move($directory, sprintf('%06d.part', $index));

        if ($index + 1 < $total) {
            return response()->json(['ok' => true, 'received' => $index + 1, 'total' => $total]);
        }

        try {
            // The endpoint runs outside the panel, so Filament knows of no
            // tenant here; the signed URL names the one the upload belongs to.
            $media = Media::actingForTenant(
                SignedMedia::tenantKey($request),
                fn (): Media => $this->assemble($service, $directory, $name, $total, $validated, $request),
                SignedMedia::tenantType($request),
            );
        } catch (Throwable $e) {
            $this->cleanUp($directory);

            throw $e;
        }

        $this->cleanUp($directory);

        return response()->json(['ok' => true, 'data' => $this->transform($media)], 201);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function assemble(
        MediaService $service,
        string $directory,
        string $name,
        int $total,
        array $validated,
        Request $request,
    ): Media {
        $parts = glob($directory.'/*.part') ?: [];

        if (count($parts) !== $total) {
            throw new RuntimeException('The upload is missing some of its parts.');
        }

        sort($parts, SORT_STRING);

        $assembled = $directory.'/'.$name;
        $out = fopen($assembled, 'wb');

        if ($out === false) {
            throw new RuntimeException('Unable to assemble the upload.');
        }

        try {
            foreach ($parts as $part) {
                $in = fopen($part, 'rb');

                if ($in === false) {
                    throw new RuntimeException('Unable to read an uploaded part.');
                }

                // Streamed part by part, so reassembly costs the same few
                // kilobytes of memory no matter how large the file is.
                stream_copy_to_stream($in, $out);
                fclose($in);
            }
        } finally {
            fclose($out);
        }

        $file = new UploadedFile($assembled, $name, null, null, true);

        UploadGuard::assertAcceptable($file);

        return $service->store(
            $file,
            $validated['disk'] ?? $service->defaultDisk(),
            $validated['directory'] ?? null,
            null,
            $request->user()?->getAuthIdentifier(),
        );
    }

    private function violationForName(string $name): ?string
    {
        $extension = strtolower((string) pathinfo($name, PATHINFO_EXTENSION));

        if ($extension !== '' && in_array($extension, MediaLibraryConfig::blockedExtensions(), true)) {
            return __('filament-media-library::filament-media-library.validation.blocked_type', [
                'extension' => $extension,
            ]);
        }

        return null;
    }

    private function chunkDirectory(string $uuid): string
    {
        // Str::uuid validation above already constrains this, but the path is
        // rebuilt from a sanitised value rather than the raw input.
        return storage_path('app/filament-media-library-chunks/'.Str::of($uuid)->replaceMatches('/[^a-f0-9-]/i', ''));
    }

    private function cleanUp(string $directory): void
    {
        if (is_dir($directory)) {
            File::deleteDirectory($directory);
        }
    }

    /** @return array<string, mixed> */
    private function transform(Media $media): array
    {
        return [
            'id' => $media->id,
            'url' => $media->publicUrl(),
            'path' => $media->path,
            'name' => $media->name,
            'size' => $media->size,
            'kind' => $media->kind,
        ];
    }
}
