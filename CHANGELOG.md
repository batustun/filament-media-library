# Changelog

All notable changes to `batustun/filament-media-library` are documented here.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.0.0] - 2026-09-12

First public release.

### Library
- Full-page media library: grid and list views, folder tree, search, type
  filter, date-range filter, sorting and a detail panel.
- Keyboard navigation, Shift+click range selection, drag-to-folder moves and
  drag-from-desktop uploads.
- Folder rename and move — files are relocated on the storage disk as well as
  in the index.
- Replace a file in place, keeping its id, path and URL so every page that
  references it updates at once.
- Duplicate detection: a byte-identical upload reuses the existing record
  instead of writing a second copy.
- Usage tracking — the detail panel shows how many models reference an item and
  warns before deleting one that is in use.

### Storage
- Disk agnostic through Flysystem: `public`, `local`, `s3`, MinIO, Spaces,
  BunnyCDN Storage, FTP/SFTP.
- Uploads are streamed, so a 512 MB file costs kilobytes of memory.
- Public URLs are resolved at read time, so changing a CDN hostname needs no
  backfill.
- No ACL is sent unless configured, for S3 buckets with ACLs disabled and for
  adapters with no visibility concept.
- Date tokens in upload directories: `posts/{Y}/{m}`.

### Video platforms
- `MediaProvider` contract for sources that are not filesystems.
- Bunny Stream provider: streamed upload, transcoding status, HLS playback via
  a pull zone (or the hosted embed player), poster images, deletion, and a
  secret-guarded status webhook.

### Images
- Automatic downscaled variants and `srcset`, generated with ext-gd — no image
  library to install. Variants are never upscaled, are rebuilt on replace and
  removed on delete.
- EXIF capture for JPEG and TIFF.

### Security
- Every uploaded SVG is parsed and stripped of scripts, event handlers,
  `javascript:` URLs, remote references and dangerous CSS; entities are never
  expanded. The sanitised document is what reaches the disk.
- Server-executable and same-origin-HTML extensions are refused, read from the
  original filename rather than the temporary path.
- `accepted_mime_types` and `max_upload_size_kb` are enforced on both the
  in-panel uploader and the JSON endpoint.
- Every Livewire action authorises itself; the browsable disk is always forced
  back into the configured allow-list.

### Filament integration
- `MediaInput` form field with a modal picker, `returns('url'|'path'|'id')`.
- `MediaColumn` table column and `MediaEntry` infolist entry.
- `MediaLibraryFileAttachmentProvider` for RichEditor inline uploads.
- `InteractsWithMedia` trait for attaching media to any model through named
  collections.
- Per-panel plugin configuration that never writes to the global config, so two
  panels can run against two different disks.
- Built from Filament's own Blade components and colour variables: the plugin
  inherits the panel's theme, brand colour and dark mode, with no Tailwind build
  step.

### Commands
- `media-library:sync` — index files that already exist on a disk.
- `media-library:doctor` — report and repair drift between the library and its
  storage (missing files, unindexed files, duplicates).

### Localisation
- English, Turkish, German, French, Spanish, Italian, Dutch, Brazilian
  Portuguese, Russian and Arabic. RTL works without extra rules.

[Unreleased]: https://github.com/batustun/filament-media-library/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/batustun/filament-media-library/releases/tag/v1.0.0
