<?php

declare(strict_types=1);

namespace Batustun\FilamentMediaLibrary\Jobs;

use Batustun\FilamentMediaLibrary\Models\Media;
use Batustun\FilamentMediaLibrary\Services\ImageConversionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateImageConversions implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 2;

    public int $timeout = 120;

    public function __construct(public Media $media) {}

    public function handle(ImageConversionService $conversions): void
    {
        // The item may have been deleted between dispatch and execution.
        if (! $this->media->exists) {
            return;
        }

        $conversions->generate($this->media);
    }

    /** One job per item: re-uploading the same record supersedes the old run. */
    public function uniqueId(): string
    {
        return (string) $this->media->getKey();
    }
}
