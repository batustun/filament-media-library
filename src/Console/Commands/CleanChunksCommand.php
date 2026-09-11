<?php

declare(strict_types=1);

namespace Batustun\FilamentMediaLibrary\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Removes chunk directories left behind by uploads that were never finished —
 * a closed tab, a lost connection. Without this they accumulate silently.
 *
 * Schedule it daily:
 *     Schedule::command('media-library:clean-chunks')->daily();
 */
class CleanChunksCommand extends Command
{
    protected $signature = 'media-library:clean-chunks {--hours=24 : Remove partial uploads older than this}';

    protected $description = 'Delete abandoned chunked-upload directories.';

    public function handle(): int
    {
        $root = storage_path('app/filament-media-library-chunks');

        if (! is_dir($root)) {
            $this->components->info('Nothing to clean.');

            return self::SUCCESS;
        }

        $cutoff = now()->subHours(max(1, (int) $this->option('hours')))->getTimestamp();
        $removed = 0;

        foreach (File::directories($root) as $directory) {
            if (filemtime($directory) < $cutoff) {
                File::deleteDirectory($directory);
                $removed++;
            }
        }

        $this->components->info($removed.' abandoned upload(s) removed.');

        return self::SUCCESS;
    }
}
