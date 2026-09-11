# Changelog

All notable changes to `batustun/filament-media-library` are documented here.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.0.0] - 2026-09-12

First public release, extracted from a private application and hardened for
distribution.

### Added
- Full-page Filament media library with folder navigation, search, kind filter,
  sorting, grid/list views and a detail panel.
- `MediaInput` — a `FileUpload` field backed by the library, with a modal picker
  for reusing existing assets. `->returns('url'|'path'|'id')`.
- `MediaLibraryFileAttachmentProvider` for RichEditor inline uploads.
- `InteractsWithMedia` trait to attach media to any Eloquent model through named
  collections.
- `media-library:sync` command to index files that already exist on a disk.
- Per-disk URL resolvers, resolved at read time.
- English and Turkish translations.
- Per-panel plugin configuration (disk, disks allow-list, visibility, URL
  resolvers, navigation, permissions).

### Notes for anyone migrating from the pre-release internal package
- Namespace is now `Batustun\FilamentMediaLibrary\` (was `Borsa\MediaLibrary\`).
- Config, view and translation namespace is now `filament-media-library`
  (was `media-library`, which collided head-on with `spatie/laravel-medialibrary`).
- The Livewire picker component is `filament-media-library-picker`.
- The browser event is `filament-media-library:picked`.
- The console command is `media-library:sync` (was `media:sync`).
- Table names are unchanged: `media_library_items`, `media_library_folders`,
  `media_library_attachables`.

[Unreleased]: https://github.com/batustun/filament-media-library/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/batustun/filament-media-library/releases/tag/v1.0.0
