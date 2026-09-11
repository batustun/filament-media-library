<?php

declare(strict_types=1);

namespace Batustun\FilamentMediaLibrary\Console\Commands;

use Batustun\FilamentMediaLibrary\Models\Media;
use Batustun\FilamentMediaLibrary\Services\MediaService;
use Batustun\FilamentMediaLibrary\Support\MimeKindResolver;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Brings media that spatie/laravel-medialibrary already manages into this
 * library, so an application that has been using Spatie for years does not
 * have to choose between the two.
 *
 * This is an import, not a live driver: rows are copied into
 * media_library_items and from then on belong to this library. Spatie's own
 * records are left untouched, so nothing that reads them breaks.
 *
 * Re-running is safe — every imported row records the Spatie id it came from
 * and is skipped on the next pass.
 */
class ImportSpatieMediaCommand extends Command
{
    protected $signature = 'media-library:import-spatie
        {--collection= : Only import one Spatie collection}
        {--disk= : Only import media stored on this disk}
        {--dry-run : Report what would be imported without writing anything}';

    protected $description = 'Import media managed by spatie/laravel-medialibrary into the media library.';

    public function handle(MediaService $service): int
    {
        $table = (string) config('media-library.media_model_table', 'media');

        if (! $this->spatieTableExists($table)) {
            $this->components->error(
                "No spatie/laravel-medialibrary table found (looked for [{$table}]). Nothing to import.",
            );

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');

        $query = DB::table($table);

        if ($collection = $this->option('collection')) {
            $query->where('collection_name', $collection);
        }

        if ($disk = $this->option('disk')) {
            $query->where('disk', $disk);
        }

        $imported = 0;
        $skipped = 0;
        $failed = 0;

        $query->orderBy('id')->chunk(200, function ($rows) use (&$imported, &$skipped, &$failed, $dryRun, $service): void {
            foreach ($rows as $row) {
                try {
                    $path = $this->pathFor($row);

                    if ($this->alreadyImported($row->id, (string) $row->disk, $path)) {
                        $skipped++;

                        continue;
                    }

                    if ($dryRun) {
                        $imported++;
                        $this->line("  would import #{$row->id}  {$row->disk}:{$path}");

                        continue;
                    }

                    $this->importRow($row, $path, $service);
                    $imported++;
                } catch (Throwable $e) {
                    $failed++;
                    $this->components->warn("#{$row->id}: ".$e->getMessage());
                }
            }
        });

        $this->table(['Imported', 'Skipped', 'Failed'], [[$imported, $skipped, $failed]]);

        if ($dryRun) {
            $this->components->info('Dry run — nothing was written.');
        }

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Spatie stores each file under a directory named after its id, which is
     * how its path is rebuilt without booting its models.
     */
    private function pathFor(object $row): string
    {
        $prefix = trim((string) config('media-library.prefix', ''), '/');
        $directory = ($prefix === '' ? '' : $prefix.'/').$row->id;

        return $directory.'/'.$row->file_name;
    }

    private function alreadyImported(int|string $spatieId, string $disk, string $path): bool
    {
        return Media::query()
            ->where(function ($query) use ($spatieId, $disk, $path): void {
                $query->where('meta->spatie_media_id', $spatieId)
                    ->orWhere(fn ($nested) => $nested->where('disk', $disk)->where('path', $path));
            })
            ->exists();
    }

    private function importRow(object $row, string $path, MediaService $service): void
    {
        $custom = json_decode((string) ($row->custom_properties ?? '{}'), true) ?: [];

        Media::create([
            'disk' => $row->disk,
            'directory' => trim((string) dirname($path), '.\\/') ?: null,
            'path' => $path,
            'url' => $service->resolveUrl((string) $row->disk, $path),
            'name' => $row->file_name,
            'title' => $row->name ?: null,
            'mime_type' => $row->mime_type ?: null,
            'kind' => MimeKindResolver::fromMime($row->mime_type ?: null, (string) $row->file_name)->value,
            'size' => (int) $row->size,
            'meta' => [
                'spatie_media_id' => $row->id,
                'spatie_collection' => $row->collection_name,
                'spatie_model_type' => $row->model_type,
                'spatie_model_id' => $row->model_id,
                'custom' => is_array($custom) ? $custom : [],
            ],
            'created_at' => $row->created_at,
            'updated_at' => $row->updated_at,
        ]);
    }

    private function spatieTableExists(string $table): bool
    {
        try {
            return DB::getSchemaBuilder()->hasTable($table);
        } catch (Throwable) {
            return false;
        }
    }
}
