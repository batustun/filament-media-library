<?php

declare(strict_types=1);

namespace Batustun\FilamentMediaLibrary\Console\Commands;

use Batustun\FilamentMediaLibrary\Exceptions\CannotListDisk;
use Batustun\FilamentMediaLibrary\Services\MediaIndexer;
use Batustun\FilamentMediaLibrary\Support\MediaLibraryConfig;
use Illuminate\Console\Command;

class SyncMediaCommand extends Command
{
    protected $signature = 'media-library:sync
        {--disk= : Flysystem disk to scan (defaults to the configured default disk)}
        {--directory= : Limit the scan to a sub-directory}
        {--chunk=500 : Files per batch}';

    protected $description = 'Index files that already exist on a storage disk into the media library.';

    public function handle(MediaIndexer $indexer): int
    {
        $disk = (string) ($this->option('disk') ?: MediaLibraryConfig::defaultDisk());
        $directory = $this->option('directory');
        $chunk = (int) $this->option('chunk');

        $this->info("Scanning disk [{$disk}]".($directory ? " directory [{$directory}]" : '').'…');

        $bar = $this->output->createProgressBar();
        $bar->setFormat("%current% processed — %message%\n");
        $bar->setMessage('starting…');
        $bar->start();

        try {
            $stats = $indexer->sync($disk, $directory, $chunk, function (string $path, string $status) use ($bar): void {
                $bar->setMessage("{$status}: {$path}");
                $bar->advance();
            });
        } catch (CannotListDisk $e) {
            $bar->finish();
            $this->newLine(2);

            $this->components->error(__('filament-media-library::filament-media-library.doctor.unlistable', [
                'disk' => $disk,
                'reason' => $e->reason(),
            ]));

            return self::FAILURE;
        }

        $bar->finish();
        $this->newLine(2);

        $this->table(
            ['Indexed', 'Skipped', 'Failed'],
            [[$stats['indexed'], $stats['skipped'], $stats['failed']]],
        );

        return $stats['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
