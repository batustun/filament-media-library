<?php

declare(strict_types=1);

namespace Batustun\FilamentMediaLibrary\Http\Controllers;

use Batustun\FilamentMediaLibrary\Models\Media;
use Batustun\FilamentMediaLibrary\Providers\BunnyStreamProvider;
use Batustun\FilamentMediaLibrary\Services\MediaService;
use Batustun\FilamentMediaLibrary\Support\MediaLibraryConfig;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Receives "transcoding finished" callbacks.
 *
 * Bunny Stream posts {VideoLibraryId, VideoGuid, Status} when a video changes
 * state. It does not sign the request, so the route carries a shared secret
 * that is compared in constant time; without a configured secret the endpoint
 * refuses every call rather than accepting unauthenticated writes.
 */
class ProviderWebhookController extends Controller
{
    public function bunnyStream(Request $request, MediaService $service): JsonResponse
    {
        if (! $this->secretMatches($request)) {
            return response()->json(['ok' => false], 403);
        }

        $guid = (string) ($request->input('VideoGuid') ?? $request->input('videoGuid') ?? '');

        if ($guid === '') {
            return response()->json(['ok' => false, 'reason' => 'missing guid'], 422);
        }

        $media = Media::query()
            ->where('provider', BunnyStreamProvider::KEY)
            ->where('external_id', $guid)
            ->first();

        if (! $media) {
            // Not ours: acknowledge so the platform stops retrying.
            return response()->json(['ok' => true, 'known' => false]);
        }

        // The payload only carries a status; the authoritative record — length,
        // resolutions, thumbnail — is read back from the API.
        $service->refreshFromProvider($media);

        return response()->json(['ok' => true]);
    }

    private function secretMatches(Request $request): bool
    {
        $expected = (string) (MediaLibraryConfig::provider(BunnyStreamProvider::KEY)['webhook_secret'] ?? '');

        if ($expected === '') {
            return false;
        }

        $given = (string) ($request->query('secret') ?? $request->header('X-Media-Library-Secret') ?? '');

        return $given !== '' && hash_equals($expected, $given);
    }
}
