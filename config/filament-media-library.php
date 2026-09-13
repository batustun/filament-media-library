<?php

declare(strict_types=1);
use Batustun\FilamentMediaLibrary\Providers\BunnyStreamProvider;

return [

    /*
    |--------------------------------------------------------------------------
    | Default Storage Disk
    |--------------------------------------------------------------------------
    |
    | The Flysystem disk used for browsing and uploading by default. Any disk
    | configured in config/filesystems.php works: "public", "local", "s3",
    | MinIO, DigitalOcean Spaces, BunnyCDN, FTP/SFTP, etc.
    |
    | Per panel, this can be overridden with:
    |   FilamentMediaLibraryPlugin::make()->defaultDisk('s3')
    |
    */
    'default_disk' => env('MEDIA_LIBRARY_DISK', 'public'),

    /*
    |--------------------------------------------------------------------------
    | Disk Allow-list
    |--------------------------------------------------------------------------
    |
    | Restrict which disks may be browsed / uploaded to from the media library.
    | An empty array means "every disk configured in config/filesystems.php".
    |
    */
    'disks' => [],

    /*
    |--------------------------------------------------------------------------
    | Upload Visibility
    |--------------------------------------------------------------------------
    |
    | Visibility passed to Storage::put(). Leave null to send no ACL at all —
    | required for S3 buckets with ACLs disabled (Object Ownership enforced)
    | and for adapters such as BunnyCDN / FTP that do not implement
    | visibility. Set to "public" or "private" only if your disk supports it.
    |
    */
    'visibility' => env('MEDIA_LIBRARY_VISIBILITY'),

    /*
    |--------------------------------------------------------------------------
    | Per-disk URL Resolvers
    |--------------------------------------------------------------------------
    |
    | Closures returning the public URL for a stored path, keyed by disk name.
    | Use these when the CDN hostname differs from the storage endpoint — the
    | classic BunnyCDN (storage zone vs pull zone) or S3-behind-CloudFront
    | setup. When no resolver matches, the package falls back to
    | Storage::disk($disk)->url($path).
    |
    | Because URLs are resolved at read time, changing a CDN hostname here
    | instantly fixes every existing record — no backfill required.
    |
    |   'bunnycdn' => fn (string $path) => 'https://cdn.example.com/'.ltrim($path, '/'),
    |
    | Per panel:
    |   FilamentMediaLibraryPlugin::make()->urlResolvers(['s3' => fn ($p) => ...])
    |
    */
    'url_resolvers' => [],

    /*
    |--------------------------------------------------------------------------
    | Persist Resolved URLs
    |--------------------------------------------------------------------------
    |
    | When true the resolved public URL is also written to the `url` column at
    | upload time. It is only ever used as a last-resort fallback for reading;
    | the live resolver always wins. Disable to keep the column empty.
    |
    */
    'persist_url' => true,

    /*
    |--------------------------------------------------------------------------
    | Default Upload Directory
    |--------------------------------------------------------------------------
    */
    'default_directory' => 'uploads',

    /*
    |--------------------------------------------------------------------------
    | Max Upload Size (kilobytes)
    |--------------------------------------------------------------------------
    |
    | Enforced by the HTTP upload endpoint. Your PHP (upload_max_filesize,
    | post_max_size) and web-server limits still apply on top of this.
    |
    */
    'max_upload_size_kb' => (int) env('MEDIA_LIBRARY_MAX_UPLOAD_KB', 524288), // 512 MB

    /*
    |--------------------------------------------------------------------------
    | Previews
    |--------------------------------------------------------------------------
    |
    | Images, video, audio and PDF are previewed by the browser itself. These
    | cover the rest.
    |
    | `text` reads a bounded window of a text or code file and shows it inline
    | (CSV is rendered as a table). Nothing leaves your server.
    |
    | `archives` lists what is inside a ZIP, using PHP's own ZipArchive.
    |
    | `office_viewer` previews Word, Excel and PowerPoint. Neither browsers nor
    | PHP can render those, so this hands the file's URL to a third party:
    |   "microsoft" -> view.officeapps.live.com
    |   "google"    -> docs.google.com/viewer
    |
    | ⚠ Both require the file to be reachable from the public internet, and both
    | send its URL to that company. It is null by default for exactly that
    | reason — turn it on only for content you are happy to share.
    |
    */
    'preview' => [
        'text' => true,
        'archives' => true,
        'office_viewer' => env('MEDIA_LIBRARY_OFFICE_VIEWER'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Automatic Attachment
    |--------------------------------------------------------------------------
    |
    | Whether the library attaches itself to fields you did not change.
    |
    | `file_upload` adds a "Choose from Library" button to EVERY Filament
    | FileUpload in every panel, so existing forms gain the picker without
    | being rewritten. It is purely additive: the button is appended to any
    | hint actions the field already has, and what the field stores does not
    | change shape — the picker writes a disk-relative path, exactly what a
    | FileUpload stores natively.
    |
    | `index_uploads` additionally routes those fields' uploads through the
    | library, so files uploaded anywhere are indexed and reusable. This is off
    | by default because it changes the generated filename, and an application
    | may already depend on the current one.
    |
    | RichEditor is deliberately absent: in Filament v5 its attachment provider
    | belongs to the MODEL's rich content attribute, which has no global hook to
    | attach to. Wire it once per model — the README shows how.
    |
    | Anything you wrote explicitly still wins: a MediaInput keeps its own
    | picker, and hint actions you define are appended to, never replaced.
    |
    */
    'auto_attach' => [
        'file_upload' => true,
        'index_uploads' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Upload Security
    |--------------------------------------------------------------------------
    |
    | `sanitize_svg` parses every uploaded SVG and strips <script>, event
    | handlers, javascript: URLs and remote references before it is stored. An
    | SVG is an XML document a browser will execute, so an unsanitised one
    | served from your own origin is stored XSS. Leave this on.
    |
    | `blocked_extensions` refuses uploads outright. The defaults cover
    | server-executable files and same-origin HTML — the two shapes that are
    | dangerous no matter which disk they land on. Add or remove freely; an
    | empty array disables the check.
    |
    */
    'security' => [
        'sanitize_svg' => true,

        'blocked_extensions' => [
            // Server-executable
            'php', 'php3', 'php4', 'php5', 'php7', 'php8', 'phtml', 'phps', 'phar',
            'exe', 'dll', 'so', 'com', 'bat', 'cmd', 'sh', 'bash', 'cgi', 'pl',
            'jsp', 'jspx', 'asp', 'aspx', 'ascx',
            // Server configuration
            'htaccess', 'htpasswd', 'ini',
            // Markup a browser will render, and script with it, from your origin
            'html', 'htm', 'xhtml', 'shtml',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Tags
    |--------------------------------------------------------------------------
    |
    | Free-form labels an editor can attach to any item, and filter the library
    | by. Stored in their own table so filtering and counting are real queries
    | rather than JSON scans.
    |
    */
    'tags' => [
        'enabled' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Metadata Fields
    |--------------------------------------------------------------------------
    |
    | Extra fields shown in the detail panel and stored under meta.custom.
    | Supported types: text, textarea, number, url, date, boolean, select.
    |
    |   ['key' => 'photographer', 'label' => 'Photographer', 'type' => 'text'],
    |   ['key' => 'licence', 'type' => 'select', 'options' => ['rf' => 'Royalty free']],
    |
    | `label` falls back to the translation
    | filament-media-library::filament-media-library.custom.{key}, then to a
    | humanised key.
    |
    */
    'metadata_fields' => [],

    /*
    |--------------------------------------------------------------------------
    | Image Optimizer
    |--------------------------------------------------------------------------
    |
    | An on-the-fly resizing CDN. Because public URLs are resolved at read time,
    | turning one on rewrites every URL the library emits — no re-upload and no
    | regenerated files.
    |
    | Drivers: null (use the generated conversions), "bunny" (Bunny Optimizer),
    | "cloudflare" (Cloudflare Images resizing) or "glide" (a league/glide route
    | in your own app).
    |
    */
    'optimizer' => [
        'driver' => env('MEDIA_LIBRARY_OPTIMIZER'),
        'quality' => 82,
        'format' => null,          // e.g. "webp" to force a format
        'glide_path' => '/img',    // only used by the glide driver
    ],

    /*
    |--------------------------------------------------------------------------
    | Multi-tenancy
    |--------------------------------------------------------------------------
    |
    | When enabled, items are stamped with the current Filament tenant on
    | upload and every library query is scoped to it, so one tenant can never
    | see or delete another's media.
    |
    | The scope fails closed: where no tenant can be determined and the request
    | is not being served by a panel without tenancy, nothing tenanted is
    | visible at all. The package's own HTTP routes run outside the panel, so
    | the links it mints are signed — the signature is what vouches for the one
    | file it names.
    |
    | LEAVING THIS OFF IN AN APPLICATION WITH TENANTED PANELS MEANS EVERY TENANT
    | SEES EVERY FILE. The column exists either way, so switching it on later
    | needs no migration — but existing rows have no tenant, and unless
    | 'shared' is on they become invisible to tenants until you backfill.
    |
    | 'shared' makes media belonging to no tenant — what an admin panel uploads
    | — visible to every tenant, for a library of common assets.
    |
    */
    'tenancy' => [
        'enabled' => (bool) env('MEDIA_LIBRARY_TENANCY', false),
        'shared' => (bool) env('MEDIA_LIBRARY_TENANCY_SHARED', false),
        'column' => 'tenant_id',
    ],

    /*
    |--------------------------------------------------------------------------
    | Chunked Uploads
    |--------------------------------------------------------------------------
    |
    | Slices large files in the browser and reassembles them server-side, so an
    | upload is no longer capped by PHP's post_max_size / upload_max_filesize.
    | Set `threshold_mb` to the size above which chunking kicks in.
    |
    */
    'chunked_uploads' => [
        'enabled' => true,
        'chunk_size_mb' => 8,
        'threshold_mb' => 16,
    ],

    /*
    |--------------------------------------------------------------------------
    | Interface
    |--------------------------------------------------------------------------
    */
    'ui' => [
        // Show the file extension on each card.
        'show_extensions' => true,
        // Remember each user's grid/list choice between visits.
        'remember_view_mode' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Media Providers
    |--------------------------------------------------------------------------
    |
    | Sources that are NOT filesystems. A video platform accepts an upload,
    | transcodes it asynchronously and serves an adaptive manifest — there is
    | no path to list and no single file to fetch, so it cannot be a Flysystem
    | disk. Providers sit alongside disks in the library and the picker.
    |
    | Bunny STREAM is configured here. Bunny STORAGE is a different product and
    | is used as an ordinary disk in config/filesystems.php.
    |
    | `pull_zone` is optional: without it playback falls back to Bunny's hosted
    | embed player, with it the library serves the HLS manifest and poster
    | directly. `webhook_secret` guards the status callback below.
    |
    */
    'providers' => [

        'bunny-stream' => [
            'enabled' => (bool) env('MEDIA_LIBRARY_BUNNY_STREAM', false),
            'driver' => BunnyStreamProvider::class,
            'library_id' => env('BUNNY_STREAM_LIBRARY_ID'),
            'api_key' => env('BUNNY_STREAM_API_KEY'),
            'pull_zone' => env('BUNNY_STREAM_PULL_ZONE'),
            'webhook_secret' => env('BUNNY_STREAM_WEBHOOK_SECRET'),
            'timeout' => 120,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Image Conversions
    |--------------------------------------------------------------------------
    |
    | Downscaled variants generated after upload, so a listing serves a 320px
    | thumbnail instead of the full-size original and an <img> can carry a
    | srcset. Widths only — the height follows the aspect ratio — and a variant
    | is skipped when it would be larger than the original.
    |
    | Generated with ext-gd, so there is no image library to install. Formats
    | GD cannot decode (HEIC, AVIF, TIFF, SVG) simply get no variants.
    |
    | Set `queue` to a queue name to run generation in the background, or leave
    | it null to generate inline during the request.
    |
    */
    'conversions' => [
        'enabled' => true,
        'queue' => env('MEDIA_LIBRARY_CONVERSION_QUEUE'),
        'quality' => 82,
        'sizes' => [
            'thumb' => 320,
            'medium' => 768,
            'large' => 1536,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Duplicate Uploads
    |--------------------------------------------------------------------------
    |
    | "reuse": when an uploaded file is byte-identical (same SHA-256) to one
    | already indexed on the same disk, the existing record is returned instead
    | of writing a second copy. One asset then keeps one canonical URL, so
    | replacing it later updates every page that references it.
    |
    | "allow": always write a new copy. Requires `hash_uploads` to be enabled
    | for "reuse" to have anything to compare.
    |
    */
    'duplicates' => env('MEDIA_LIBRARY_DUPLICATES', 'reuse'),

    /*
    |--------------------------------------------------------------------------
    | Accepted MIME Types
    |--------------------------------------------------------------------------
    |
    | Empty array = no restriction. Example: ['image/*', 'application/pdf'].
    |
    */
    'accepted_mime_types' => [],

    /*
    |--------------------------------------------------------------------------
    | Pagination
    |--------------------------------------------------------------------------
    */
    'page_sizes' => [24, 48, 96, 192],
    'default_page_size' => 48,

    /*
    |--------------------------------------------------------------------------
    | Authorisation
    |--------------------------------------------------------------------------
    |
    | Disabled by default so the package works out of the box on a fresh app.
    | Enable it to gate every action behind permission names. Any user model
    | exposing hasPermissionTo() works — spatie/laravel-permission is the
    | common case but is NOT a hard dependency.
    |
    */
    'permissions' => [
        'enabled' => (bool) env('MEDIA_LIBRARY_PERMISSIONS', false),
        'guard' => env('MEDIA_LIBRARY_GUARD', 'web'),
        'view' => 'media.view',
        'upload' => 'media.upload',
        'delete' => 'media.delete',
        'manage' => 'media.manage',
    ],

    /*
    |--------------------------------------------------------------------------
    | Panel Navigation
    |--------------------------------------------------------------------------
    |
    | `label` and `group` fall back to the package translations when null.
    |
    */
    'navigation' => [
        'enabled' => true,
        'group' => null,
        'icon' => 'heroicon-o-photo',
        'sort' => null,
        'label' => null,
        'slug' => 'media',
    ],

    /*
    |--------------------------------------------------------------------------
    | HTTP Routes
    |--------------------------------------------------------------------------
    |
    | The JSON upload/delete endpoints. Disable them entirely if you only use
    | the Filament page, the picker and the form field.
    |
    */
    'routes' => [
        'enabled' => true,
        'prefix' => 'media-library',
        'middleware' => ['web', 'auth'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Folder Tree Cache
    |--------------------------------------------------------------------------
    */
    'cache' => [
        'enabled' => true,
        'store' => null, // null = default cache store
        'ttl' => 120,    // seconds
    ],

    /*
    |--------------------------------------------------------------------------
    | Database Tables
    |--------------------------------------------------------------------------
    |
    | Deliberately prefixed so they never collide with spatie/laravel-medialibrary's
    | `media` table or with any other package.
    |
    */
    'tables' => [
        'media' => 'media_library_items',
        'morph' => 'media_library_attachables',
        'tags' => 'media_library_tags',
        'taggables' => 'media_library_taggables',
    ],

    /*
    |--------------------------------------------------------------------------
    | Attachable Key Type
    |--------------------------------------------------------------------------
    |
    | Primary-key type of the models you attach media to: "id" (auto-increment),
    | "uuid" or "ulid". Only read when the migration first runs.
    |
    */
    'morph_key_type' => env('MEDIA_LIBRARY_MORPH_KEY_TYPE', 'id'),

    /*
    |--------------------------------------------------------------------------
    | User Model
    |--------------------------------------------------------------------------
    |
    | Class name string (not ::class) so the package never assumes the host
    | application defines App\Models\User. Left unset it follows
    | config('auth.providers.users.model'), which is where an app that renamed
    | its user model has already said so.
    |
    */
    'user_model' => env('AUTH_MODEL'),

    /*
    |--------------------------------------------------------------------------
    | Hash Uploads
    |--------------------------------------------------------------------------
    |
    | Compute a SHA-256 of every upload for duplicate detection. Hashing is
    | streamed, so it stays memory-safe for very large files.
    |
    */
    'hash_uploads' => true,

    /*
    |--------------------------------------------------------------------------
    | Read Image Dimensions
    |--------------------------------------------------------------------------
    |
    | Read width/height from uploaded images. Requires GD or Imagick.
    |
    */
    'read_image_dimensions' => true,

];
