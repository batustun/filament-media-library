<?php

declare(strict_types=1);

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
        'folders' => 'media_library_folders',
        'morph' => 'media_library_attachables',
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
    | application defines App\Models\User.
    |
    */
    'user_model' => env('AUTH_MODEL', 'App\\Models\\User'),

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
