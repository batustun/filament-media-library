# Filament Media Library

[![Latest Version](https://img.shields.io/packagist/v/batustun/filament-media-library.svg?style=flat-square)](https://packagist.org/packages/batustun/filament-media-library)
[![Tests](https://img.shields.io/github/actions/workflow/status/batustun/filament-media-library/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/batustun/filament-media-library/actions/workflows/run-tests.yml)
[![PHPStan](https://img.shields.io/github/actions/workflow/status/batustun/filament-media-library/phpstan.yml?branch=main&label=phpstan&style=flat-square)](https://github.com/batustun/filament-media-library/actions/workflows/phpstan.yml)
[![License](https://img.shields.io/packagist/l/batustun/filament-media-library.svg?style=flat-square)](LICENSE)

A WordPress-style media library for **Filament v5** — one central place to
upload, browse, search and reuse every asset in your panel.

- **Disk agnostic.** Everything goes through Flysystem, so `public`, `local`,
  `s3`, MinIO, DigitalOcean Spaces, BunnyCDN and FTP/SFTP all work through the
  same code path. Uploads are **streamed**, never buffered in memory.
- **URLs resolved at read time.** Move to a different CDN and every existing
  record follows — no backfill, no broken images.
- **Database indexed.** Files are rows, so they are searchable, filterable and
  attachable to your own models.
- **Drop-in form field.** `MediaInput` extends Filament's `FileUpload`, keeping
  the native UI and adding a "Choose from Library" picker.
- **Multi-panel safe.** Configuration lives on the plugin instance, so two
  panels can point at two different disks.
- English + Turkish out of the box, light and dark mode, no Tailwind build step.

---

## Requirements

| | |
|---|---|
| PHP | 8.2+ |
| Laravel | 11.28 · 12 · 13 |
| Filament | 5.x |

---

## Installation

```bash
composer require batustun/filament-media-library
php artisan migrate
```

That is the whole install. Migrations are auto-loaded (table names are
configurable, so there is nothing to publish and nothing that can run twice).

Register the plugin on any panel:

```php
use Batustun\FilamentMediaLibrary\FilamentMediaLibraryPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        ->plugins([
            FilamentMediaLibraryPlugin::make(),
        ]);
}
```

Optionally publish the config:

```bash
php artisan vendor:publish --tag="filament-media-library-config"
```

Views and translations can be published too, if you want to override them:

```bash
php artisan vendor:publish --tag="filament-media-library-views"
php artisan vendor:publish --tag="filament-media-library-translations"
```

---

## Configuring the disk

The library reads whatever you have in `config/filesystems.php`. Set the default
with an env variable:

```dotenv
MEDIA_LIBRARY_DISK=s3
```

…or per panel, which is what you want when one application serves several
panels:

```php
FilamentMediaLibraryPlugin::make()
    ->defaultDisk('s3')
    ->disks(['s3', 'public'])     // restrict what this panel may browse
```

### Public URLs and CDNs

By default the URL comes from `Storage::disk($disk)->url($path)`. When your CDN
hostname is different from the storage endpoint — a BunnyCDN storage zone behind
a pull zone, or an S3 bucket behind CloudFront — register a resolver:

```php
// config/filament-media-library.php
'url_resolvers' => [
    'bunnycdn' => fn (string $path): string =>
        'https://my-zone.b-cdn.net/'.ltrim($path, '/'),

    's3' => fn (string $path): string =>
        'https://cdn.example.com/'.ltrim($path, '/'),
],
```

Resolvers run on **every read**, never at upload time. Change the hostname and
every record that already exists starts serving from the new one immediately.

### Storage visibility / ACLs

`visibility` defaults to `null`, meaning **no ACL is sent at all**. This is the
correct default for S3 buckets with Object Ownership enforced (ACLs disabled)
and for adapters such as BunnyCDN and FTP that do not implement visibility.
Only set it if your disk actually supports it:

```dotenv
MEDIA_LIBRARY_VISIBILITY=public
```

### Recipes

<details>
<summary><strong>Local <code>public</code> disk</strong></summary>

```dotenv
MEDIA_LIBRARY_DISK=public
```

```bash
php artisan storage:link
```

Nothing else is needed — Laravel's `public` disk already has a `url`.
</details>

<details>
<summary><strong>Amazon S3 (or MinIO / DigitalOcean Spaces)</strong></summary>

```dotenv
MEDIA_LIBRARY_DISK=s3
AWS_ACCESS_KEY_ID=…
AWS_SECRET_ACCESS_KEY=…
AWS_DEFAULT_REGION=eu-central-1
AWS_BUCKET=…
AWS_URL=https://cdn.example.com     # optional; a CloudFront/custom domain
```

Leave `MEDIA_LIBRARY_VISIBILITY` unset if the bucket has ACLs disabled.
</details>

<details>
<summary><strong>BunnyCDN</strong></summary>

Storage zone for writes, pull zone for reads:

```php
'url_resolvers' => [
    'bunnycdn' => fn (string $path): string =>
        rtrim(config('bunnycdn.pull_zone_url'), '/').'/'.ltrim($path, '/'),
],
```

```dotenv
MEDIA_LIBRARY_DISK=bunnycdn
# leave MEDIA_LIBRARY_VISIBILITY unset — the adapter has no ACL concept
```
</details>

<details>
<summary><strong>Private disk with no public URL</strong></summary>

`publicUrl()` degrades gracefully instead of throwing. For signed access use:

```php
$media->temporaryUrl(minutes: 10);   // null if the adapter cannot sign
```
</details>

---

## Usage

### The form field

```php
use Batustun\FilamentMediaLibrary\Filament\Components\MediaInput;

MediaInput::make('cover_image')
    ->label('Cover image')
    ->directory('posts/covers')
    ->acceptedKinds(['image'])
    ->returns('url');       // 'url' (default) | 'path' | 'id'
```

`returns('id')` is the most future-proof: the URL is then resolved live, so the
field keeps working through a CDN migration.

### RichEditor attachments

```php
use Batustun\FilamentMediaLibrary\Filament\RichEditor\MediaLibraryFileAttachmentProvider;
use Filament\Forms\Components\RichEditor;

RichEditor::make('body')
    ->fileAttachmentProvider(
        MediaLibraryFileAttachmentProvider::make()->directory('posts/inline')
    );
```

### Attaching media to your own models

```php
use Batustun\FilamentMediaLibrary\Concerns\InteractsWithMedia;

class Post extends Model
{
    use InteractsWithMedia;
}
```

```php
$post->attachMedia($media, 'cover');
$post->syncMedia([$id1, $id2], 'gallery');   // ordered
$post->detachMedia($media, 'cover');

$post->media();            // every attachment
$post->media('gallery');   // one collection, in sort order
```

### Indexing files that are already on a disk

```bash
php artisan media-library:sync --disk=s3
php artisan media-library:sync --disk=s3 --directory=legacy/uploads
```

Idempotent — files that are already indexed are skipped.

---

## Panel configuration

Everything is fluent and **scoped to the panel it is registered on**:

```php
FilamentMediaLibraryPlugin::make()
    ->defaultDisk('s3')
    ->disks(['s3', 'public'])
    ->visibility(null)
    ->urlResolvers(['s3' => fn ($path) => 'https://cdn.example.com/'.$path])
    ->navigationGroup('Content')
    ->navigationIcon('heroicon-o-photo')
    ->navigationLabel('Assets')
    ->navigationSort(5)
    ->slug('assets')
    ->permissions()          // enable the permission gate on this panel
    ->registerPage(false)    // picker + field only, no full-page library
```

Read a setting back anywhere inside a panel request:

```php
filament('filament-media-library')->getDefaultDisk();
```

---

## Authorisation

Off by default, so the package works on a fresh app. Turn it on:

```dotenv
MEDIA_LIBRARY_PERMISSIONS=true
```

Four abilities are checked: `media.view`, `media.upload`, `media.delete`,
`media.manage` (rename + metadata). Any user model exposing `hasPermissionTo()`
works — [spatie/laravel-permission](https://github.com/spatie/laravel-permission)
is the common case but is **not** a hard dependency.

With spatie installed, seed the permissions:

```bash
php artisan db:seed --class="Batustun\FilamentMediaLibrary\Database\Seeders\MediaPermissionSeeder"
```

Rename them freely in the config:

```php
'permissions' => [
    'enabled' => true,
    'view'    => 'assets.view',
    'upload'  => 'assets.create',
    'delete'  => 'assets.destroy',
    'manage'  => 'assets.update',
],
```

Every entry point — the page, the Livewire picker and the JSON routes — goes
through the same check, so no action is reachable without it.

---

## HTTP endpoints

Enabled by default under the `media-library` prefix:

| Method | URI | Ability |
|---|---|---|
| `POST` | `/media-library/upload` | `media.upload` |
| `DELETE` | `/media-library/{media}` | `media.delete` |

```php
'routes' => [
    'enabled' => false,               // or turn them off entirely
    'prefix' => 'admin/media-library',
    'middleware' => ['web', 'auth'],
],
```

---

## Database

Three tables, all deliberately prefixed so they never collide with
`spatie/laravel-medialibrary`'s `media` table:

| Config key | Default |
|---|---|
| `tables.media` | `media_library_items` |
| `tables.folders` | `media_library_folders` |
| `tables.morph` | `media_library_attachables` |

If the models you attach media to use UUID or ULID primary keys, set this
**before** running the migration:

```dotenv
MEDIA_LIBRARY_MORPH_KEY_TYPE=uuid   # id | uuid | ulid
```

---

## Localisation

English is the fallback; Turkish ships with it. Every string lives in
`resources/lang/{locale}/filament-media-library.php`. To add a language, publish
the translations and drop in a new folder:

```bash
php artisan vendor:publish --tag="filament-media-library-translations"
```

---

## Styling

The package ships a pre-built, self-contained stylesheet registered through
`FilamentAsset`. It uses plain CSS and Filament's own colour custom properties,
so there is **no Tailwind build step** and no `@source` line to add to your
theme — it looks the same in every panel, with or without a custom theme, in
light and dark mode.

---

## Testing

```bash
composer test        # Pest
composer analyse     # PHPStan level 5
composer format      # Pint
```

---

## Changelog

See [CHANGELOG.md](CHANGELOG.md).

## License

[MIT](LICENSE) © Batuhan Üstün
