<?php

declare(strict_types=1);

namespace Batustun\FilamentMediaLibrary\Http\Controllers;

use Batustun\FilamentMediaLibrary\Services\MediaService;
use Batustun\FilamentMediaLibrary\Support\Authorize;
use Batustun\FilamentMediaLibrary\Support\SignedMedia;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\File;
use Throwable;

/**
 * Saves an image edited in the browser.
 *
 * The canvas sends back a complete re-encoded image, which replaces the
 * original IN PLACE: the URL does not change, so every page already showing it
 * picks up the edit, and conversions are regenerated from the new bytes.
 * There is no version history — the previous binary is gone.
 */
class ImageEditController extends Controller
{
    /** Formats the browser canvas can produce and we are willing to accept. */
    private const ACCEPTED = ['image/jpeg', 'image/png', 'image/webp'];

    public function store(Request $request, string $media, MediaService $service): JsonResponse
    {
        abort_unless((bool) $request->user(), 401);

        Authorize::ensure('manage', $request->user());

        $record = SignedMedia::findOrFail($request, $media);

        abort_if($record->isProviderBacked(), 422);

        $validated = $request->validate([
            'image' => ['required', 'file', 'mimetypes:'.implode(',', self::ACCEPTED)],
        ]);

        /** @var UploadedFile $upload */
        $upload = $validated['image'];

        // The edited file keeps the original's name, so the stored path — and
        // therefore the public URL — is unchanged.
        $temporary = tempnam(sys_get_temp_dir(), 'fml-edit-');

        if ($temporary === false) {
            abort(500);
        }

        try {
            File::copy($upload->getRealPath(), $temporary);

            $service->replace($record, new UploadedFile(
                $temporary,
                (string) $record->name,
                $upload->getMimeType(),
                null,
                true,
            ));
        } catch (Throwable $e) {
            @unlink($temporary);

            throw $e;
        }

        @unlink($temporary);

        return response()->json([
            'ok' => true,
            'url' => $record->fresh()?->publicUrl(),
        ]);
    }
}
