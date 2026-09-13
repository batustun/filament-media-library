<?php

declare(strict_types=1);

namespace Batustun\FilamentMediaLibrary\Console\Commands;

use Batustun\FilamentMediaLibrary\Exceptions\CannotListDisk;
use Batustun\FilamentMediaLibrary\Models\Media;
use Batustun\FilamentMediaLibrary\Services\MediaIndexer;
use Batustun\FilamentMediaLibrary\Services\MediaService;
use Batustun\FilamentMediaLibrary\Support\MediaLibraryConfig;
use Batustun\FilamentMediaLibrary\Support\MimeKindResolver;
use Filament\Facades\Filament;
use Filament\Panel;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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

        $this->reportTenancy();

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

    /**
     * What the library thinks about tenancy right now.
     *
     * "Why does a tenant still see everything" is almost always answered here:
     * the setting is off, or a cached config is still holding the old value.
     */
    private function reportTenancy(): void
    {
        $enabled = MediaLibraryConfig::tenancyEnabled();
        $column = MediaLibraryConfig::tenantColumn();

        if (! $enabled) {
            $this->components->warn(
                'Tenancy is OFF. Every panel — tenanted or not — sees every file. '
                .'Set MEDIA_LIBRARY_TENANCY=true, then run `php artisan config:clear`.',
            );

            return;
        }

        if (! Schema::hasColumn((new Media)->getTable(), $column)) {
            $this->components->error(
                "Tenancy is ON but the [{$column}] column is missing. Run `php artisan migrate`.",
            );

            return;
        }

        $base = Media::withoutGlobalScope(Media::TENANT_SCOPE);

        $untenanted = (clone $base)->whereNull($column)->count();
        $tenants = (clone $base)->whereNotNull($column)->distinct()->count($column);

        $this->table(
            ['Tenancy', 'Value'],
            [
                ['Enabled', 'yes'],
                ['Column', $column],
                ['Distinct tenants', $tenants],
                ['Rows with no tenant', $untenanted],
                ['Shared with every tenant', MediaLibraryConfig::tenancyShares() ? 'yes' : 'no'],
            ],
        );

        $typeColumn = MediaLibraryConfig::tenantTypeColumn();

        if (Schema::hasColumn((new Media)->getTable(), $typeColumn)) {
            $untyped = (clone $base)->whereNotNull($column)->whereNull($typeColumn)->count();
            $tenantedPanels = $this->tenantedPanelCount();

            if ($untyped > 0 && $tenantedPanels > 1) {
                $this->components->warn(
                    "{$untyped} rows record a tenant key but not which kind of tenant, and this "
                    ."application has {$tenantedPanels} tenanted panels. Two panels whose tenants "
                    ."both start at id 1 will each see those rows. Backfill [{$typeColumn}].",
                );
            }
        }

        if ($untenanted > 0 && ! MediaLibraryConfig::tenancyShares()) {
            $this->components->warn(
                "{$untenanted} rows belong to no tenant, so no tenant can see them. "
                .'Backfill them, or set MEDIA_LIBRARY_TENANCY_SHARED=true to share them with everyone.',
            );
        }
    }

    /** How many panels this application serves tenants from. */
    private function tenantedPanelCount(): int
    {
        try {
            return count(array_filter(
                Filament::getPanels(),
                fn (Panel $panel): bool => $panel->hasTenancy(),
            ));
        } catch (Throwable) {
            return 0;
        }
    }
}
