# Filament Media Library

[![Latest Version](https://img.shields.io/packagist/v/batustun/filament-media-library.svg?style=flat-square)](https://packagist.org/packages/batustun/filament-media-library)
[![Tests](https://img.shields.io/github/actions/workflow/status/batustun/filament-media-library/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/batustun/filament-media-library/actions/workflows/run-tests.yml)
[![PHPStan](https://img.shields.io/github/actions/workflow/status/batustun/filament-media-library/phpstan.yml?branch=main&label=phpstan&style=flat-square)](https://github.com/batustun/filament-media-library/actions/workflows/phpstan.yml)
[![License](https://img.shields.io/packagist/l/batustun/filament-media-library.svg?style=flat-square)](LICENSE)

A WordPress-style media library for **Filament v5** — one central place to
upload, organise, search and reuse every asset in your panel.

- **Disk agnostic.** Everything goes through Flysystem, so `public`, `local`,
  `s3`, MinIO, DigitalOcean Spaces, BunnyCDN Storage and FTP/SFTP all work
  through the same code path. Uploads are **streamed**, never buffered.
- **Video platforms too.** Bunny Stream is supported as a first-class source
  alongside disks, with transcoding status, HLS playback and webhooks.
- **URLs resolved at read time.** Move to a different CDN and every existing
  record follows — no backfill, no broken images.
- **Automatic thumbnails.** Downscaled variants and a `srcset`, generated with
  ext-gd, so a grid serves 320px images instead of 4000px originals.
- **Safe by default.** Every uploaded SVG is sanitised; server-executable and
  same-origin-HTML uploads are refused.
- **Knows where things are used,** so deleting an in-use asset warns first.
- **Filament's own design language** — its components, its colour variables,
  its dark mode. No Tailwind build step, and RTL works out of the box.
- **Tags, custom metadata fields, in-browser image editing and chunked uploads.**
- **Extensible**: add your own filters, sorters, item and bulk actions, or swap
  the page class entirely.
- **Attaches itself.** Every existing `FileUpload` in every panel gains a
  "Choose from Library" button on install — no form to rewrite.
- **10 languages** included.

---

## Requirements

| | |
|---|---|
| PHP | 8.2+ |
| Laravel | 11.28 · 12 · 13 |
| Filament | 4.x · 5.x |

---

## Installation

```bash
composer require batustun/filament-media-library
php artisan migrate
```

That is the whole install.

> Not on Packagist yet? Add the repository first and the command above works
> unchanged:
>
> ```json
> "repositories": [
>     { "type": "vcs", "url": "https://github.com/batustun/filament-media-library.git" }
> ]
> ``` Migrations are auto-loaded — table names are
configurable, so there is nothing to publish and nothing that can run twice.

Register the plugin on any panel:

```php
use Batustun\FilamentMediaLibrary\FilamentMediaLibraryPlugin;

public function panel(Panel $panel): Panel
{
    return $panel->plugins([
        FilamentMediaLibraryPlugin::make(),
    ]);
}
```

Optionally publish the config, views or translations:

```bash
php artisan vendor:publish --tag="filament-media-library-config"
php artisan vendor:publish --tag="filament-media-library-views"
php artisan vendor:publish --tag="filament-media-library-translations"
```

---

## Storage

The library reads whatever is in `config/filesystems.php`:

```dotenv
MEDIA_LIBRARY_DISK=s3
```

…or per panel, which is what you want when one application serves several:

```php
FilamentMediaLibraryPlugin::make()
    ->defaultDisk('s3')
    ->disks(['s3', 'public'])
```

### Public URLs and CDNs

By default the URL comes from `Storage::disk($disk)->url($path)`. When the CDN
hostname differs from the storage endpoint — a BunnyCDN storage zone behind a
pull zone, or S3 behind CloudFront — register a resolver:

```php
// config/filament-media-library.php
'url_resolvers' => [
    'bunnycdn' => fn (string $path): string => 'https://my-zone.b-cdn.net/'.ltrim($path, '/'),
],
```

Resolvers run on **every read**, never at upload time. Change the hostname and
every existing record starts serving from the new one immediately.

### Storage visibility / ACLs

`visibility` defaults to `null`, meaning **no ACL is sent**. That is correct for
S3 buckets with Object Ownership enforced and for adapters such as BunnyCDN and
FTP that do not implement visibility. Set it only if your disk supports it:

```dotenv
MEDIA_LIBRARY_VISIBILITY=public
```

<details>
<summary><strong>Recipes: public · S3 · BunnyCDN Storage · private</strong></summary>

**Local `public` disk**

```dotenv
MEDIA_LIBRARY_DISK=public
```
```bash
php artisan storage:link
```

**Amazon S3 / MinIO / DigitalOcean Spaces**

```dotenv
MEDIA_LIBRARY_DISK=s3
AWS_ACCESS_KEY_ID=…
AWS_SECRET_ACCESS_KEY=…
AWS_DEFAULT_REGION=eu-central-1
AWS_BUCKET=…
AWS_URL=https://cdn.example.com     # optional CloudFront / custom domain
```

Leave `MEDIA_LIBRARY_VISIBILITY` unset if the bucket has ACLs disabled.

**BunnyCDN Storage** (storage zone for writes, pull zone for reads)

```php
'url_resolvers' => [
    'bunnycdn' => fn (string $path): string =>
        rtrim(config('bunnycdn.pull_zone_url'), '/').'/'.ltrim($path, '/'),
],
```

**A private disk with no public URL** — `publicUrl()` degrades gracefully
instead of throwing. For signed access:

```php
$media->temporaryUrl(minutes: 10);   // null if the adapter cannot sign
```
</details>

---

## Video platforms — Bunny Stream

Bunny **Stream** is not object storage: it accepts an upload, transcodes it
asynchronously and serves an adaptive manifest, so it cannot be a Flysystem
disk. It is wired in as a **provider** and appears beside your disks in the
source picker. (Bunny **Storage** is a different product — use it as a normal
disk, above.)

```dotenv
MEDIA_LIBRARY_BUNNY_STREAM=true
BUNNY_STREAM_LIBRARY_ID=123456
BUNNY_STREAM_API_KEY=…
BUNNY_STREAM_PULL_ZONE=vz-example.b-cdn.net     # optional
BUNNY_STREAM_WEBHOOK_SECRET=a-long-random-string
```

- Uploads are streamed straight to Bunny, so a multi-gigabyte master never
  enters PHP memory.
- With a pull zone the library serves the **HLS manifest** and the poster
  directly; without one it falls back to Bunny's hosted embed player.
- The detail panel shows transcoding progress and only offers playback once
  the platform reports it finished.

Point Bunny's webhook at:

```
POST https://your-app.test/media-library/webhooks/bunny-stream?secret=<BUNNY_STREAM_WEBHOOK_SECRET>
```

The secret is compared in constant time, and the endpoint refuses every call
when no secret is configured. Duration, resolutions and the thumbnail are read
back from the API rather than trusted from the payload.

Adding another platform (Mux, Cloudflare Stream) means implementing
`Batustun\FilamentMediaLibrary\Providers\Contracts\MediaProvider` and listing it
under `providers` in the config.

---

## Image conversions

Every uploaded image gets downscaled variants, so a grid serves thumbnails
instead of originals:

```php
'conversions' => [
    'enabled' => true,
    'queue' => env('MEDIA_LIBRARY_CONVERSION_QUEUE'),  // null = inline
    'quality' => 82,
    'sizes' => ['thumb' => 320, 'medium' => 768, 'large' => 1536],
],
```

```php
$media->thumbnailUrl();        // smallest variant, or the original
$media->conversionUrl('medium');
$media->srcset();              // "…-thumb.jpg 320w, …-medium.jpg 768w, … 4000w"
```

Widths only — heights follow the aspect ratio — and a variant is skipped when
it would be larger than the original. Generated with **ext-gd**, so there is no
image library to install; formats GD cannot decode (HEIC, AVIF, TIFF, SVG)
simply get no variants and serve the original.

---

## Tags and custom metadata

Tags live in their own table, so filtering and counting by them are real
queries. Matching is on a slug, so "Product Shots", "product shots" and
"product-shots" are one tag.

```php
$media->syncTagNames(['Campaign', 'Hero']);
$media->tagNames();
Media::query()->taggedWith('campaign')->get();
```

Declare extra fields and they appear in the detail panel, stored under
`meta.custom`:

```php
'metadata_fields' => [
    ['key' => 'photographer', 'label' => 'Photographer', 'type' => 'text'],
    ['key' => 'licence', 'type' => 'select', 'options' => ['rf' => 'Royalty free']],
    ['key' => 'featured', 'type' => 'boolean'],
],
```

Types: `text`, `textarea`, `number`, `url`, `date`, `boolean`, `select`.
Anything not declared here is refused, so a crafted request cannot write
arbitrary keys.

---

## Extending

Everything below is per panel, and none of it writes to global config.

```php
use Batustun\FilamentMediaLibrary\Filters\MediaFilter;
use Batustun\FilamentMediaLibrary\Filters\MediaSorter;

FilamentMediaLibraryPlugin::make()
    ->filters([
        MediaFilter::make('licence')
            ->label('Licence')
            ->options(['rf' => 'Royalty free', 'rm' => 'Rights managed'])
            ->using(fn (Builder $query, string $value) => $query->where('meta->custom->licence', $value)),

        MediaFilter::make('featured')
            ->label('Featured only')
            ->boolean()
            ->using(fn (Builder $query) => $query->where('meta->custom->featured', true)),
    ])
    ->sorters([
        MediaSorter::make('most_used')
            ->label('Most used')
            ->using(fn (Builder $query) => $query->withUsageCount()->orderByDesc('usage_count')),
    ])
    ->itemActions([Action::make('sendToReview')->action(fn (array $arguments) => /* ... */)])
    ->bulkActions([Action::make('exportSelected')->action(fn () => /* ... */)])
    ->mediaLibraryPage(MyMediaLibraryPage::class);
```

Extra fields in the file-info form, when the values live somewhere other than
the item's own metadata:

```php
FilamentMediaLibraryPlugin::make()
    ->fileInfoComponents([TextInput::make('sku')])
    ->hydrateFileInfoUsing(fn (Media $media) => ['sku' => $media->customMetaValue('sku')])
    ->saveFileInfoUsing(fn (Media $media, array $data) => /* persist $data['sku'] */);
```

---

## Large uploads

A single POST is capped by PHP's `upload_max_filesize` and `post_max_size`,
which no application code can raise at runtime. Files above the threshold are
sliced in the browser and reassembled server-side:

```php
'chunked_uploads' => [
    'enabled' => true,
    'chunk_size_mb' => 8,
    'threshold_mb' => 16,
],
```

Schedule the cleanup so abandoned uploads do not accumulate:

```php
Schedule::command('media-library:clean-chunks')->daily();
```

---

## Image editing

Crop, rotate and flip in the browser. Saving **replaces the original in place**,
so the URL does not change and every page already showing it picks up the edit;
conversions are regenerated from the new bytes. There is no version history.

Built on the 2D canvas — no image library, no build step. The button only
appears for formats a canvas can re-encode (JPEG, PNG, WebP); SVG, HEIC and
TIFF are excluded rather than silently mangled.

---

## Multi-tenancy

```dotenv
MEDIA_LIBRARY_TENANCY=true
```

Items are stamped with the current Filament tenant on upload, and a **global
scope** keeps every query inside it — including a direct `Media::find()` from
your own code, because a library that is only scoped on one query path is not
scoped at all.

The `tenant_id` column exists either way, so turning this on later needs no
migration in your application.

---

## Coming from spatie/laravel-medialibrary

Import what Spatie already manages, without touching its records:

```bash
php artisan media-library:import-spatie --dry-run
php artisan media-library:import-spatie --collection=cover
```

Safe to re-run: every imported row records the Spatie id it came from and is
skipped next time. This is an import, not a live driver — from then on the rows
belong to this library.

> The package's own tables are prefixed (`media_library_items`), so it coexists
> with Spatie's `media` table rather than colliding with it.

---

## Previews

| | Previewed with |
|---|---|
| Images (JPEG, PNG, GIF, WebP, AVIF, SVG, BMP, ICO) | the browser, at a mid-size rendition |
| Video, audio | native players |
| PDF | an inline frame |
| Text, code, JSON, XML, Markdown, logs | a bounded window read through your app |
| **CSV** | rendered as a table |
| ZIP archives | their entry list, via PHP's own ZipArchive |
| HEIC, HEIF, TIFF | the type icon — no mainstream browser decodes them |
| Word, Excel, PowerPoint | opt-in, see below |

```php
'preview' => [
    'text' => true,
    'archives' => true,
    'office_viewer' => null,   // null | 'microsoft' | 'google'
],
```

Text and archive previews are read **through your application**, so the
permission check applies, private disks work, and a two-gigabyte log cannot be
pulled into the panel by clicking on it — only a bounded window is sent, and
anything that turns out to be binary is refused rather than sprayed across the
screen.

> ⚠ `office_viewer` is the one exception: neither browsers nor PHP can render
> Office formats, so it hands the file's **URL to Microsoft or Google**, and the
> file must be reachable from the public internet. It is `null` by default for
> that reason.

---

## Security

| | |
|---|---|
| **SVG sanitisation** | Every uploaded SVG is parsed and stripped of `<script>`, `<foreignObject>`, event handlers, `javascript:` URLs, remote `<use>` references and dangerous CSS. Entities are never expanded, so XXE and billion-laughs are closed too. What lands on the disk is the sanitised document. |
| **Blocked extensions** | Server-executable files (`.php`, `.phar`, `.sh`, `.exe`, `.cgi`, `.asp`, …) and same-origin HTML (`.html`, `.htm`) are refused. The extension is read from the *original* filename, not the temporary path. |
| **Upload limits** | `accepted_mime_types` and `max_upload_size_kb` are enforced on **both** the in-panel uploader and the JSON endpoint. |
| **Disk allow-list** | The browsable disk arrives from a query string, so it is always forced back into the configured allow-list. |

```php
'security' => [
    'sanitize_svg' => true,
    'blocked_extensions' => ['php', 'phar', 'exe', 'sh', 'html', /* … */],
],
```

---

## Usage

### It attaches itself

On install, every Filament `FileUpload` in every panel gains a **"Choose from
Library"** button, so forms you already wrote can pick from the library without
being touched.

This is purely additive: the button is appended to whatever hint actions the
field already has, and what the field stores keeps its usual shape — the picker
writes a disk-relative path, exactly what a `FileUpload` stores natively.

```php
'auto_attach' => [
    'file_upload' => true,    // the picker on every FileUpload
    'index_uploads' => false, // also index what those fields upload
],
```

Turn `index_uploads` on and files uploaded through *any* field are indexed and
become reusable. It is off by default because it changes the generated
filename, and an application may already depend on the current one.

> RichEditor is not auto-attached: in Filament v5 its attachment provider
> belongs to the model's rich content attribute, which has no global hook. Wire
> it once per model — see below.

### The form field

```php
use Batustun\FilamentMediaLibrary\Filament\Components\MediaInput;

MediaInput::make('cover_image')
    ->directory('posts/{Y}/{m}')     // date tokens: {Y} {y} {m} {d} {H}
    ->acceptedKinds(['image'])
    ->returns('id');                 // 'url' (default) | 'path' | 'id'
```

`returns('id')` is the most future-proof: the URL is then resolved live, so the
field keeps working through a CDN migration.

### Table column and infolist entry

```php
use Batustun\FilamentMediaLibrary\Filament\Tables\MediaColumn;
use Batustun\FilamentMediaLibrary\Filament\Infolists\MediaEntry;

MediaColumn::make('cover_image')->circular();
MediaEntry::make('cover_image')->height(200);
```

Both accept whatever the field stored — a UUID, a path or a URL — and serve the
right rendition.

### RichEditor attachments

In Filament v5 the attachment provider belongs to the model's rich content
attribute, not to the field:

```php
use Batustun\FilamentMediaLibrary\Filament\RichEditor\MediaLibraryFileAttachmentProvider;
use Filament\Forms\Components\RichEditor\Models\Concerns\InteractsWithRichContent;
use Filament\Forms\Components\RichEditor\Models\Contracts\HasRichContent;

class Post extends Model implements HasRichContent
{
    use InteractsWithRichContent;

    protected function setUpRichContent(): void
    {
        $this->registerRichContent('body')
            ->fileAttachmentProvider(
                MediaLibraryFileAttachmentProvider::make()->directory('posts/inline')
            );
    }
}
```

Images dropped into the editor are stored in the library and referenced by id,
so the URL is resolved live — moving to a new CDN never breaks published
content.

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
$post->media('gallery');                      // one collection, in sort order

$media->usageCount();       // how many models reference it
$media->usageBreakdown();   // by model class and collection
```

The library shows this in the detail panel and warns before deleting something
that is still in use.

---

## In the library

- Grid and list views, folder tree, search, and filters for type, **tag**,
  **size** and **date range** — plus any you add yourself
- The grid/list choice is **remembered** per user; file extensions toggle on and off
- **Keyboard navigation** (arrows, Enter, Space) and **Shift+click** range select
- **Drag files onto a folder** to move them; drag from the desktop to upload
- **Rename or move a folder** — files move on the disk too
- **Replace a file in place**, keeping its URL so every page updates at once
- **Duplicate** an item into its own file and record
- **Edit images in the browser** — crop, rotate, flip
- **Duplicate detection**: a byte-identical upload reuses the existing record
  instead of writing a second copy
- Bulk move and bulk delete, copy URL, download

---

## Commands

```bash
# Index files that already exist on a disk
php artisan media-library:sync --disk=s3 --directory=legacy

# Check the library against its storage and repair drift
php artisan media-library:doctor --disk=s3
php artisan media-library:doctor --disk=s3 --prune --index

# Import media managed by spatie/laravel-medialibrary
php artisan media-library:import-spatie --dry-run

# Remove abandoned chunked uploads (schedule this daily)
php artisan media-library:clean-chunks
```

`doctor` reports index rows whose file is missing, files on the disk that are
not indexed, and byte-identical duplicates.

---

## Panel configuration

Everything is fluent and **scoped to the panel it is registered on** — a plugin
never writes to the global config, so two panels can run against two different
disks:

```php
FilamentMediaLibraryPlugin::make()
    ->defaultDisk('s3')
    ->disks(['s3', 'public'])
    ->visibility(null)
    ->urlResolvers(['s3' => fn ($path) => 'https://cdn.example.com/'.$path])
    ->navigationGroup('Content')
    ->navigationLabel('Assets')
    ->slug('assets')
    ->permissions()
    ->registerPage(false);   // picker + field only
```

Outside a panel — console commands, the JSON routes, queued jobs — the config
file is the source of truth.

---

## Authorisation

Off by default so the package works on a fresh app:

```dotenv
MEDIA_LIBRARY_PERMISSIONS=true
```

Four abilities: `media.view`, `media.upload`, `media.delete`, `media.manage`.
Any user model exposing `hasPermissionTo()` works —
[spatie/laravel-permission](https://github.com/spatie/laravel-permission) is the
common case but is **not** a hard dependency. With it installed:

```bash
php artisan db:seed --class="Batustun\FilamentMediaLibrary\Database\Seeders\MediaPermissionSeeder"
```

Every entry point — the page, the Livewire picker, the JSON routes — goes
through the same check.

---

## HTTP endpoints

| Method | URI | Ability |
|---|---|---|
| `POST` | `/media-library/upload` | `media.upload` |
| `GET` | `/media-library/{media}/download` | `media.view` |
| `DELETE` | `/media-library/{media}` | `media.delete` |
| `POST` | `/media-library/webhooks/bunny-stream` | shared secret |

```php
'routes' => ['enabled' => true, 'prefix' => 'media-library', 'middleware' => ['web', 'auth']],
```

---

## Database

| Config key | Default |
|---|---|
| `tables.media` | `media_library_items` |
| `tables.morph` | `media_library_attachables` |

Deliberately prefixed so they never collide with `spatie/laravel-medialibrary`'s
`media` table. Folders are derived from the `directory` column rather than a
table of their own, so the tree cannot drift from what is on the disk.

If the models you attach media to use UUID or ULID keys, set this **before**
running the migration:

```dotenv
MEDIA_LIBRARY_MORPH_KEY_TYPE=uuid   # id | uuid | ulid
```

---

## Localisation

Ships with **English, Turkish, German, French, Spanish, Italian, Dutch,
Brazilian Portuguese, Russian and Arabic**. English is the fallback.

RTL needs nothing from you: Filament sets `dir` from its own translations, and
every rule in the stylesheet uses logical properties, so an Arabic panel mirrors
correctly.

To add a language, publish the translations and drop in a folder. A test in this
repository asserts that every locale carries exactly the English key set and the
same `:placeholder` tokens, so a translation cannot silently drift.

---

## Styling

The package uses Filament's own Blade components for buttons, inputs, badges,
modals, dropdowns, sections, empty states and pagination, so it inherits your
panel's theme, brand colour and dark mode. The small stylesheet that remains
covers only layout, and reads Filament's `--primary-*` / `--gray-*` / `--danger-*`
custom properties.

There is **no Tailwind build step** and no `@source` line to add to a custom
theme.

---

## Testing

```bash
composer test        # Pest
composer analyse     # PHPStan level 5
composer format      # Pint
```

---

## Changelog

See [CHANGELOG.md](CHANGELOG.md). Maintainer notes live in [RELEASING.md](RELEASING.md).

## License

[MIT](LICENSE) © Batuhan Üstün
