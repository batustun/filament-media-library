<?php

declare(strict_types=1);

namespace Batustun\FilamentMediaLibrary\Models;

use Batustun\FilamentMediaLibrary\Enums\MediaKind;
use Batustun\FilamentMediaLibrary\Models\Concerns\BelongsToTenant;
use Batustun\FilamentMediaLibrary\Models\Concerns\HasConversions;
use Batustun\FilamentMediaLibrary\Models\Concerns\HasCustomMetadata;
use Batustun\FilamentMediaLibrary\Models\Concerns\HasMediaTags;
use Batustun\FilamentMediaLibrary\Models\Concerns\HasPreview;
use Batustun\FilamentMediaLibrary\Models\Concerns\TracksUsage;
use Batustun\FilamentMediaLibrary\Providers\Contracts\MediaProvider;
use Batustun\FilamentMediaLibrary\Providers\MediaProviderRegistry;
use Batustun\FilamentMediaLibrary\Support\MediaLibraryConfig;
use Batustun\FilamentMediaLibrary\Support\MimeKindResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * @property string $id
 * @property string $disk
 * @property string|null $provider
 * @property string|null $external_id
 * @property string|null $directory
 * @property int|string|null $tenant_id the column config('filament-media-library.tenancy.column') names
 * @property string $path
 * @property string|null $url
 * @property string $name
 * @property string|null $title
 * @property string|null $alt
 * @property string|null $description
 * @property string|null $mime_type
 * @property string $kind
 * @property int $size
 * @property int|null $width
 * @property int|null $height
 * @property int|null $duration
 * @property string|null $hash
 * @property array<string, mixed>|null $meta
 * @property int|string|null $uploaded_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Media extends Model
{
    use BelongsToTenant;
    use HasConversions;
    use HasCustomMetadata;
    use HasMediaTags;
    use HasPreview;
    use HasUuids;
    use TracksUsage;

    /**
     * LIKE escape character. Deliberately not a backslash: MySQL and SQLite
     * disagree about backslashes inside SQL string literals.
     */
    protected const LIKE_ESCAPE = '!';

    protected $guarded = [];

    public function getTable(): string
    {
        return MediaLibraryConfig::table('media');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'duration' => 'integer',
            'meta' => 'array',
        ];
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(MediaLibraryConfig::userModel(), 'uploaded_by');
    }

    // -----------------------------------------------------------------
    // Accessors
    // -----------------------------------------------------------------

    public function getKindEnumAttribute(): MediaKind
    {
        return MediaKind::tryFrom((string) $this->kind) ?? MediaKind::Other;
    }

    public function getHumanSizeAttribute(): string
    {
        return MimeKindResolver::formatBytes((int) $this->size);
    }

    /**
     * Resolve the public URL at READ time.
     *
     * Order: a configured per-disk resolver → the disk's own url() → a stored
     * url column → the raw path. Resolving live is what makes the library
     * survive a CDN hostname change (BunnyCDN pull zone, S3 → CloudFront)
     * without rewriting a single row.
     */
    public function isProviderBacked(): bool
    {
        return $this->provider !== null && $this->provider !== '';
    }

    public function mediaProvider(): ?MediaProvider
    {
        return app(MediaProviderRegistry::class)->get($this->provider);
    }

    /**
     * The URL to play or display this item.
     *
     * For a provider-backed item that is the platform's playback URL; for a
     * file it is the disk URL, resolved at read time.
     */
    public function publicUrl(): string
    {
        if ($provider = $this->mediaProvider()) {
            return (string) ($provider->playbackUrl($this) ?? '');
        }

        return $this->resolveUrlFor((string) $this->path, fallbackToStoredUrl: true);
    }

    /** A still image for this item: the platform poster, or a variant. */
    public function posterUrl(): ?string
    {
        if ($provider = $this->mediaProvider()) {
            return $provider->posterUrl($this);
        }

        return $this->kind === MediaKind::Image->value ? $this->thumbnailUrl() : null;
    }

    /**
     * False only while a provider is still transcoding. Disk-backed items are
     * ready the moment they are written.
     */
    public function isReady(): bool
    {
        return $this->mediaProvider()?->isReady($this) ?? true;
    }

    /** Resolve any path on this item's disk through the same URL chain. */
    protected function resolveUrlFor(string $path, bool $fallbackToStoredUrl = false): string
    {
        $disk = (string) $this->disk;

        if ($resolver = MediaLibraryConfig::urlResolver($disk)) {
            try {
                $resolved = $resolver($path);

                if (is_string($resolved) && $resolved !== '') {
                    return $resolved;
                }
            } catch (Throwable) {
                // Fall through to the next strategy.
            }
        }

        try {
            $url = Storage::disk($disk)->url($path);

            if ($url !== '') {
                return $url;
            }
        } catch (Throwable) {
            // Disks without a public URL (plain "local", some FTP adapters)
            // throw here; that is expected, not exceptional.
        }

        if ($fallbackToStoredUrl) {
            $stored = (string) ($this->url ?? '');

            if ($stored !== '') {
                return $stored;
            }
        }

        return $path;
    }

    public function temporaryUrl(int $minutes = 5): ?string
    {
        try {
            return Storage::disk((string) $this->disk)
                ->temporaryUrl((string) $this->path, now()->addMinutes($minutes));
        } catch (Throwable) {
            return null;
        }
    }

    public function existsOnDisk(): bool
    {
        try {
            return Storage::disk((string) $this->disk)->exists((string) $this->path);
        } catch (Throwable) {
            return false;
        }
    }

    // -----------------------------------------------------------------
    // Scopes
    // -----------------------------------------------------------------

    public function scopeOnDisk(Builder $query, string $disk): Builder
    {
        return $query->where('disk', $disk);
    }

    /** Byte-identical files, used for duplicate detection on upload. */
    public function scopeWithHash(Builder $query, ?string $hash): Builder
    {
        return $hash === null || $hash === ''
            ? $query->whereRaw('1 = 0')
            : $query->where('hash', $hash);
    }

    /**
     * Match a directory. The empty string means "root", which has to cover
     * both NULL and '' — grouped so it can never leak an OR into whatever
     * conditions the caller already applied.
     */
    public function scopeInDirectory(Builder $query, ?string $directory): Builder
    {
        $directory = trim((string) $directory, '/');

        if ($directory === '') {
            return $query->where(
                fn (Builder $nested): Builder => $nested
                    ->whereNull('directory')
                    ->orWhere('directory', ''),
            );
        }

        return $query->where('directory', $directory);
    }

    /** Match a directory and everything nested below it. */
    public function scopeInDirectoryTree(Builder $query, string $directory): Builder
    {
        $directory = trim($directory, '/');

        if ($directory === '') {
            return $query;
        }

        $grammar = $query->getQuery()->getGrammar();
        $column = $grammar->wrap('directory');

        return $query->where(
            fn (Builder $nested): Builder => $nested
                ->where('directory', $directory)
                ->orWhereRaw(
                    "{$column} like ? escape '".self::LIKE_ESCAPE."'",
                    [self::escapeLike($directory).'/%'],
                ),
        );
    }

    public function scopeOfKind(Builder $query, string|MediaKind $kind): Builder
    {
        return $query->where('kind', $kind instanceof MediaKind ? $kind->value : $kind);
    }

    /**
     * Case-insensitive search across the user-visible columns.
     *
     * PostgreSQL gets ILIKE; every other driver gets LOWER(col) LIKE LOWER(?).
     * Both forms carry an explicit ESCAPE clause, because SQLite has no
     * default LIKE escape character at all — without it a user typing "%"
     * would silently match nothing. "!" is used rather than a backslash so the
     * clause needs no driver-specific string escaping.
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);

        if ($term === '') {
            return $query;
        }

        $pattern = '%'.self::escapeLike($term).'%';
        $isPostgres = $query->getModel()->getConnection()->getDriverName() === 'pgsql';
        $grammar = $query->getQuery()->getGrammar();

        return $query->where(function (Builder $nested) use ($pattern, $isPostgres, $grammar): void {
            foreach (['name', 'title', 'alt', 'description', 'path'] as $index => $column) {
                $wrapped = $grammar->wrap($column);

                [$sql, $binding] = $isPostgres
                    ? ["{$wrapped} ilike ? escape '".self::LIKE_ESCAPE."'", $pattern]
                    : ["lower({$wrapped}) like ? escape '".self::LIKE_ESCAPE."'", mb_strtolower($pattern)];

                $index === 0
                    ? $nested->whereRaw($sql, [$binding])
                    : $nested->orWhereRaw($sql, [$binding]);
            }
        });
    }

    /** Escape the LIKE wildcards a user may have typed. */
    protected static function escapeLike(string $value): string
    {
        $escape = self::LIKE_ESCAPE;

        return str_replace(
            [$escape, '%', '_'],
            [$escape.$escape, $escape.'%', $escape.'_'],
            $value,
        );
    }
}
