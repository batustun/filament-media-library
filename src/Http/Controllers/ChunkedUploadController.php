<?php

declare(strict_types=1);

namespace Batustun\FilamentMediaLibrary\Http\Controllers;

use Batustun\FilamentMediaLibrary\Models\Media;
use Batustun\FilamentMediaLibrary\Services\MediaService;
use Batustun\FilamentMediaLibrary\Support\Authorize;
use Batustun\FilamentMediaLibrary\Support\MediaLibraryConfig;
use Batustun\FilamentMediaLibrary\Support\MimeKindResolver;
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

    /** An abandoned upload is swept after this many seconds. */
    private const STALE_AFTER = 86400;

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

        $directory = $this->chunkDirectory($request, $uuid);

        if ($index === 0) {
            $this->pruneStale();
        }

        File::ensureDirectoryExists($directory, 0755, true);

        // The cap is on what has actually arrived, not on what the request
        // claims: `total` and `index` are the client's word, the bytes are not.
        $received = $this->bytesReceived($directory) + (int) $request->file('chunk')->getSize();

        $maxBytes = MediaLibraryConfig::maxUploadSizeKb() * 1024;

        if ($maxBytes > 0 && $received > $maxBytes) {
            $this->cleanUp($directory);

            return response()->json([
                'ok' => false,
                'message' => __('filament-media-library::filament-media-library.validation.too_large', [
                    'max' => MimeKindResolver::formatBytes($maxBytes),
                    'size' => MimeKindResolver::formatBytes($received),
                ]),
            ], 413);
        }

        $request->file('chunk')->move($directory, sprintf('%06d.part', $index));

        if ($index + 1 < $total) {
            return response()->json(['ok' => true, 'received' => $index + 1, 'total' => $total]);
        }

        if (count(glob($directory.'/*.part') ?: []) !== $total) {
            return response()->json(['ok' => false, 'message' => 'The upload is missing some of its parts.'], 422);
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

    private function chunkDirectory(Request $request, string $uuid): string
    {
        // Str::uuid validation above already constrains this, but the path is
        // rebuilt from a sanitised value rather than the raw input. The owner
        // is part of the path, so knowing another user's uuid reaches nothing.
        $owner = Str::of((string) $request->user()?->getAuthIdentifier())->replaceMatches('/[^a-zA-Z0-9_-]/', '');

        return self::chunkRoot().'/'.$owner.'/'.Str::of($uuid)->replaceMatches('/[^a-f0-9-]/i', '');
    }

    private static function chunkRoot(): string
    {
        return storage_path('app/filament-media-library-chunks');
    }

    private function bytesReceived(string $directory): int
    {
        return array_sum(array_map('filesize', glob($directory.'/*.part') ?: []));
    }

    /** Removes uploads that were started and never finished. */
    private function pruneStale(): void
    {
        foreach (glob(self::chunkRoot().'/*/*', GLOB_ONLYDIR) ?: [] as $stale) {
            if (filemtime($stale) < time() - self::STALE_AFTER) {
                File::deleteDirectory($stale);
            }
        }
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
