<?php

declare(strict_types=1);

namespace Batustun\FilamentMediaLibrary\Http\Controllers;

use Batustun\FilamentMediaLibrary\Models\Media;
use Batustun\FilamentMediaLibrary\Services\MediaService;
use Batustun\FilamentMediaLibrary\Support\Authorize;
use Batustun\FilamentMediaLibrary\Support\MediaLibraryConfig;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MediaUploadController extends Controller
{
    public function store(Request $request, MediaService $service): JsonResponse
    {
        abort_unless((bool) $request->user(), 401);

        Authorize::ensure('upload', $request->user());

        $rules = [
            'file' => ['required', 'file', 'max:'.MediaLibraryConfig::maxUploadSizeKb()],
            'disk' => ['nullable', 'string', Rule::in(MediaLibraryConfig::disks())],
            'directory' => ['nullable', 'string', 'max:512'],
        ];

        if ($mimes = MediaLibraryConfig::acceptedMimeTypes()) {
            $rules['file'][] = 'mimetypes:'.implode(',', $mimes);
        }

        $validated = $request->validate($rules);

        $media = $service->store(
            $request->file('file'),
            $validated['disk'] ?? $service->defaultDisk(),
            $validated['directory'] ?? null,
            null,
            $request->user()->getAuthIdentifier(),
        );

        return response()->json(['data' => $this->transform($media)], 201);
    }

    public function destroy(Request $request, string $media, MediaService $service): JsonResponse
    {
        abort_unless((bool) $request->user(), 401);

        Authorize::ensure('delete', $request->user());

        $record = Media::query()->findOrFail($media);

        $service->delete($record);

        return response()->json(['ok' => true]);
    }

    /**
     * Stream a file back as an attachment.
     *
     * Served through the application rather than linked directly, so the
     * permission check applies and private disks work too — a CDN URL would
     * bypass both.
     */
    public function download(Request $request, string $media): StreamedResponse
    {
        abort_unless((bool) $request->user(), 401);

        Authorize::ensure('view', $request->user());

        $record = Media::query()->findOrFail($media);

        abort_if($record->isProviderBacked(), 404);

        $disk = Storage::disk($record->disk);

        abort_unless($disk->exists($record->path), 404);

        return $disk->download($record->path, $record->name);
    }

    /** @return array<string, mixed> */
    protected function transform(Media $media): array
    {
        return [
            'id' => $media->id,
            'url' => $media->publicUrl(),
            'disk' => $media->disk,
            'path' => $media->path,
            'name' => $media->name,
            'size' => $media->size,
            'mime_type' => $media->mime_type,
            'kind' => $media->kind,
            'width' => $media->width,
            'height' => $media->height,
        ];
    }
}
