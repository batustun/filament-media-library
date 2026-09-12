<?php

declare(strict_types=1);

namespace Batustun\FilamentMediaLibrary\Http\Controllers;

use Batustun\FilamentMediaLibrary\Models\Media;
use Batustun\FilamentMediaLibrary\Support\Authorize;
use Batustun\FilamentMediaLibrary\Support\MediaLibraryConfig;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Throwable;
use ZipArchive;

/**
 * Serves previews for the file types a browser cannot render on its own.
 *
 * Read through the application rather than linked directly, so the permission
 * check applies and private disks work — and, for text, so a 2 GB log file
 * cannot be pulled into a panel by clicking it.
 */
class PreviewController extends Controller
{
    /** How much of a text file is worth showing inline. */
    private const TEXT_LIMIT = 64 * 1024;

    /** How many entries of an archive to list. */
    private const ARCHIVE_LIMIT = 500;

    public function text(Request $request, string $media): JsonResponse
    {
        $item = $this->authorized($request, $media);

        if (! MediaLibraryConfig::previewsText()) {
            abort(404);
        }

        $disk = Storage::disk($item->disk);

        if (! $disk->exists($item->path)) {
            abort(404);
        }

        // Read a bounded window rather than the whole file: previewing must
        // never depend on the file being small.
        $handle = $disk->readStream($item->path);

        if (! is_resource($handle)) {
            abort(404);
        }

        try {
            $content = (string) fread($handle, self::TEXT_LIMIT + 1);
        } finally {
            fclose($handle);
        }

        $truncated = strlen($content) > self::TEXT_LIMIT;

        if ($truncated) {
            $content = substr($content, 0, self::TEXT_LIMIT);
        }

        // Anything that is not valid UTF-8 is binary wearing a text extension;
        // rendering it would spray control characters into the panel.
        if (! mb_check_encoding($content, 'UTF-8')) {
            return response()->json(['ok' => false, 'reason' => 'binary']);
        }

        return response()->json([
            'ok' => true,
            'content' => $content,
            'truncated' => $truncated,
            'csv' => $this->looksLikeCsv($item),
        ]);
    }

    public function archive(Request $request, string $media): JsonResponse
    {
        $item = $this->authorized($request, $media);

        if (! MediaLibraryConfig::previewsArchives() || ! class_exists(ZipArchive::class)) {
            abort(404);
        }

        // ZipArchive needs a real local path, so a remote disk is copied to a
        // temporary file first.
        $local = $this->localCopy($item);

        if ($local === null) {
            abort(404);
        }

        $zip = new ZipArchive;

        try {
            if ($zip->open($local) !== true) {
                return response()->json(['ok' => false, 'reason' => 'unreadable']);
            }

            $entries = [];

            for ($i = 0; $i < min($zip->numFiles, self::ARCHIVE_LIMIT); $i++) {
                $stat = $zip->statIndex($i);

                if ($stat === false) {
                    continue;
                }

                $entries[] = [
                    'name' => (string) $stat['name'],
                    'size' => (int) $stat['size'],
                ];
            }

            $total = $zip->numFiles;
            $zip->close();

            return response()->json([
                'ok' => true,
                'entries' => $entries,
                'total' => $total,
                'truncated' => $total > self::ARCHIVE_LIMIT,
            ]);
        } finally {
            if ($local !== $this->diskPath($item)) {
                @unlink($local);
            }
        }
    }

    private function authorized(Request $request, string $media): Media
    {
        abort_unless((bool) $request->user(), 401);

        Authorize::ensure('view', $request->user());

        $item = Media::query()->findOrFail($media);

        abort_if($item->isProviderBacked(), 404);

        return $item;
    }

    /** The file's path on the local filesystem, copying it down if need be. */
    private function localCopy(Media $item): ?string
    {
        if ($path = $this->diskPath($item)) {
            return $path;
        }

        try {
            $contents = Storage::disk($item->disk)->get($item->path);
        } catch (Throwable) {
            return null;
        }

        if ($contents === null) {
            return null;
        }

        $temporary = tempnam(sys_get_temp_dir(), 'fml-zip-');

        if ($temporary === false || file_put_contents($temporary, $contents) === false) {
            return null;
        }

        return $temporary;
    }

    /** A local disk exposes a real path; a remote one does not. */
    private function diskPath(Media $item): ?string
    {
        try {
            $path = Storage::disk($item->disk)->path($item->path);
        } catch (Throwable) {
            return null;
        }

        return is_file($path) ? $path : null;
    }

    private function looksLikeCsv(Media $item): bool
    {
        return strtolower((string) pathinfo((string) $item->name, PATHINFO_EXTENSION)) === 'csv';
    }
}
