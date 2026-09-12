<?php

declare(strict_types=1);

namespace Batustun\FilamentMediaLibrary\Console\Commands;

use Batustun\FilamentMediaLibrary\Exceptions\CannotListDisk;
use Batustun\FilamentMediaLibrary\Models\Media;
use Batustun\FilamentMediaLibrary\Services\MediaIndexer;
use Batustun\FilamentMediaLibrary\Services\MediaService;
use Batustun\FilamentMediaLibrary\Support\MediaLibraryConfig;
use Batustun\FilamentMediaLibrary\Support\MimeKindResolver;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

use function Laravel\Prompts\confirm;

use Throwable;

/**
 * Reports the three ways a media library drifts from its storage, and can
 * repair each one.
 */
class DoctorCommand extends Command
{
    protected $signature = 'media-library:doctor
        {--disk= : Flysystem disk to inspect (defaults to the configured default disk)}
        {--prune : Delete index rows whose file is missing from storage}
        {--index : Index files that exist on storage but are not in the library}
        {--force : Skip the confirmation prompt}';

    protected $description = 'Check the media library against its storage disk and report or repair drift.';

    /** Why the disk could not be enumerated, when that is what happened. */
    private ?string $listingFailure = null;

    public function handle(MediaIndexer $indexer, MediaService $service): int
    {
        $disk = (string) ($this->option('disk') ?: MediaLibraryConfig::defaultDisk());

        $this->components->info("Inspecting disk [{$disk}]");

        // Establish first whether the disk answers at all. When it does not,
        // every existence check would fail and report the entire library as
        // missing — and --prune would then delete the whole index.
        $unindexed = $this->findUnindexed($disk);
        $reachable = $unindexed !== null;

        $missing = $reachable ? $indexer->findOrphans($disk) : [];
        $duplicates = $this->findDuplicates($disk);

        $this->table(
            ['Check', 'Count'],
            [
                [
                    __('filament-media-library::filament-media-library.doctor.missing'),
                    $reachable ? count($missing) : '—',
                ],
                [
                    __('filament-media-library::filament-media-library.doctor.unindexed'),
                    $unindexed === null ? '—' : count($unindexed),
                ],
                [__('filament-media-library::filament-media-library.doctor.duplicates'), count($duplicates)],
            ],
        );

        if (! $reachable) {
            // The other two checks read the database and still stand, so the
            // report is degraded rather than abandoned.
            $this->components->warn(
                __('filament-media-library::filament-media-library.doctor.unlistable', [
                    'disk' => $disk,
                    'reason' => $this->listingFailure ?? '',
                ]),
            );
        }

        if ($duplicates !== []) {
            $this->components->warn('Byte-identical duplicates (the oldest of each group is the one to keep):');

            foreach (array_slice($duplicates, 0, 20) as $row) {
                $this->line(sprintf(
                    '  %s  ×%d  %s',
                    substr((string) $row->hash, 0, 12),
                    $row->total,
                    MimeKindResolver::formatBytes((int) $row->size),
                ));
            }
        }

        $repaired = false;

        if ($this->option('prune') && ! $reachable) {
            // Refusing is the whole point: with an unreachable disk every row
            // looks orphaned, and pruning would erase the entire index.
            $this->components->error(__('filament-media-library::filament-media-library.doctor.prune_blocked'));

            return self::FAILURE;
        }

        if ($missing !== [] && $this->option('prune')) {
            $repaired = true;
            $this->prune($missing, $service);
        }

        if ($unindexed !== null && $unindexed !== [] && $this->option('index')) {
            $repaired = true;
            $this->index($disk, $unindexed, $service);
        }

        if (! $repaired && $reachable && $missing === [] && $unindexed === [] && $duplicates === []) {
            $this->components->info(__('filament-media-library::filament-media-library.doctor.healthy'));
        }

        return self::SUCCESS;
    }

    /**
     * @param  array<int, Media>  $missing
     */
    private function prune(array $missing, MediaService $service): void
    {
        if (! $this->option('force') && ! $this->confirmDestructive(count($missing))) {
            return;
        }

        foreach ($missing as $media) {
            // The file is already gone; only the index row is removed.
            $service->delete($media, purgeDisk: false);
        }

        $this->components->info(count($missing).' index row(s) pruned.');
    }

    /**
     * @param  array<int, string>  $paths
     */
    private function index(string $disk, array $paths, MediaService $service): void
    {
        foreach ($paths as $path) {
            $service->indexFromDisk($disk, $path);
        }

        $this->components->info(count($paths).' file(s) indexed.');
    }

    private function confirmDestructive(int $count): bool
    {
        return confirm(
            label: "Delete {$count} index row(s) whose file is missing from storage?",
            default: false,
        );
    }

    /**
     * Files on the disk that the library does not know about, or null when the
     * disk cannot be enumerated at all.
     *
     * @return array<int, string>|null
     */
    private function findUnindexed(string $disk): ?array
    {
        try {
            $onDisk = Storage::disk($disk)->allFiles();
        } catch (Throwable $e) {
            $this->listingFailure = (new CannotListDisk($disk, $e))->reason();

            return null;
        }

        $indexed = Media::query()
            ->onDisk($disk)
            ->pluck('path')
            ->all();

        return array_values(array_diff($onDisk, $indexed));
    }

    /** @return array<int, object> */
    private function findDuplicates(string $disk): array
    {
        return DB::table(MediaLibraryConfig::table('media'))
            ->where('disk', $disk)
            ->whereNotNull('hash')
            ->groupBy('hash')
            ->havingRaw('count(*) > 1')
            ->select('hash')
            ->selectRaw('count(*) as total')
            ->selectRaw('max(size) as size')
            ->get()
            ->all();
    }
}
