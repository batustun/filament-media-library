# Changelog

All notable changes to `batustun/filament-media-library` are documented here.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.4.4] - 2026-09-12

### Fixed
- **`doctor --prune` could erase the entire index.** When a disk was
  unreachable, every existence check failed and the whole library was reported
  as missing from storage — and pruning acts on exactly that list. The disk is
  now probed first: an unreachable one reports "—" instead of a count, and
  `--prune` refuses outright.

## [1.4.3] - 2026-09-12

### Fixed
- `media-library:doctor` and `media-library:sync` crashed with a raw Flysystem
  stack trace when a disk refused to enumerate — wrong or placeholder
  credentials, an adapter with no deep listing, a bucket too large to walk.
  They now report the disk and the reason. `doctor` degrades rather than
  aborting: the two checks that read the database are still reported.

## [1.4.2] - 2026-09-12

### Fixed
- Uploading from inside the picker confirmed the selection immediately, which
  closes the modal — so the file just added flashed past without ever appearing
  in the grid, and read as "the upload did nothing". The upload is now selected
  and the modal stays open; the editor confirms when ready.

## [1.4.1] - 2026-09-12

### Fixed
- A folder was invisible until its first file landed in it. Folders are derived
  from the directories of indexed files, so creating one and seeing the sidebar
  unchanged read as "it did not work" — the opposite of what had happened. The
  folder you are standing in is now listed immediately, along with its
  ancestors.

## [1.4.0] - 2026-09-12

### Added
- **Filament 4 support.** The package required Filament 5, which kept it out of
  every application still on 4. Every class and Blade component it uses exists
  unchanged in 4.12, and 4 emits the same `oklch()` colour variables, so the
  stylesheet needed no change either. The whole suite runs against both, and CI
  now covers Filament 4 and 5 across PHP 8.2-8.4 and Laravel 11-13.
- Livewire 3 alongside Livewire 4, which Filament 4 requires.

## [1.3.0] - 2026-09-12

### Added
- **Previews for the file types a browser cannot render.** Text, code, JSON,
  XML, Markdown and logs are shown inline from a bounded window read through the
  application; CSV is rendered as a table; ZIP archives list their contents via
  PHP's own ZipArchive. Nothing leaves your server, the permission check
  applies, and private disks work.
- Opt-in Office previews (`preview.office_viewer`) through Microsoft's or
  Google's viewer. Off by default: both need the file to be publicly reachable
  and both receive its URL.

### Fixed
- HEIC, HEIF and TIFF were classified as images and rendered in an `<img>`,
  which no mainstream browser can decode — the detail panel showed a broken
  image. They now fall back to the type icon everywhere, decided up front
  rather than relying on an `onerror` handler.

## [1.2.0] - 2026-09-12

### Added
- **The library attaches itself to fields you never changed.** Every Filament
  `FileUpload`, in every panel, gains a "Choose from Library" button on install.
  Purely additive: the button is appended to any hint actions the field already
  has, and the field keeps storing a disk-relative path, which is what a
  `FileUpload` stores natively. Controlled by `auto_attach.file_upload`.
- `auto_attach.index_uploads` (off by default) additionally routes those fields'
  uploads through the library, so a file uploaded anywhere is indexed and
  reusable. Off by default because it changes the generated filename.
- `LibraryPickerAction`, so the dedicated `MediaInput` and the automatic
  attachment share one definition of the picker rather than two.

### Fixed
- The README showed `RichEditor::make()->fileAttachmentProvider(...)`, which
  does not exist. In Filament v5 the provider belongs to the model's rich
  content attribute; the README now shows the real API.

### Notes
- RichEditor is deliberately not auto-attached: its provider lives on
  `RichContentAttribute`, which offers no global hook to attach to.

## [1.1.3] - 2026-09-12

### Changed
- `MediaService` no longer carries path hygiene, file inspection and disk-write
  policy alongside its orchestration. Those move to `MediaPath`,
  `FileInspector` and a `MediaWriter` collaborator, which is also where the two
  storage rules now live in one place: an ACL is sent only when configured, and
  an SVG is stored rewritten rather than copied. No public API changed — the
  service still exposes `normalizeDirectory()`, `hashFor()` and `readExif()`.

## [1.1.2] - 2026-09-12

### Fixed
- **`fileInfoComponents()` and `hydrateFileInfoUsing()` did nothing.** The
  components were registered on the plugin but never rendered, and the hydrator
  was never called, so a host application's extra fields silently never
  appeared. They are now built as a real Filament schema, hydrated when an item
  is previewed, and their state is merged into what `saveFileInfoUsing()`
  receives.

### Removed
- The `tags.sync_spatie_tags` option, which was documented in the config but
  never implemented. Tags are the library's own; nothing was mirrored anywhere.

## [1.1.1] - 2026-09-12

### Changed
- The media browser is composed from five focused concerns —
  `InteractsWithMediaSources`, `BrowsesMediaFolders`, `FiltersMedia`,
  `SelectsMedia` and `MutatesMedia` — instead of one 900-line trait. No public
  API changed.

## [1.1.0] - 2026-09-12

### Added
- **Tags** — their own table and pivot, slug-matched so "Product Shots" and
  "product shots" are one tag, with a tag filter in the toolbar.
- **Custom metadata fields** — declare extra fields in config and they appear in
  the detail panel, stored under `meta.custom`. Undeclared keys are refused.
- **Extensibility** — `->filters()`, `->sorters()`, `->itemActions()`,
  `->bulkActions()`, `->mediaLibraryPage()`, `->fileInfoComponents()` with
  hydrate and save callbacks.
- **Chunked uploads** — files above a threshold are sliced in the browser and
  reassembled server-side, so `post_max_size` stops being the ceiling. Includes
  `media-library:clean-chunks` for abandoned uploads.
- **In-browser image editing** — crop, rotate and flip on a 2D canvas, with no
  image library and no build step. Saving replaces the original in place, so the
  URL is unchanged and conversions are regenerated.
- **Image optimiser integrations** — Bunny Optimizer, Cloudflare Images and
  Glide. Because URLs resolve at read time, enabling one rewrites every URL the
  library emits with no re-upload.
- **Multi-tenancy** — items are stamped with the current Filament tenant and a
  global scope keeps every query inside it, including direct model access.
- **Duplicate** an item into its own file and record.
- **Size filter**, and the grid/list choice and extension display are remembered
  per user.
- `media-library:import-spatie` — bring media managed by
  spatie/laravel-medialibrary into the library, idempotently and without
  touching its records.
- `MediaColumn` table column and `MediaEntry` infolist entry.

### Changed
- The `Media` model is composed from focused concerns (`BelongsToTenant`,
  `HasConversions`, `HasCustomMetadata`, `HasMediaTags`, `TracksUsage`) rather
  than one 600-line class.

### Fixed
- Attaching the same item to two collections on one model collapsed into a
  single pivot row, because the sync was not scoped to the collection.
- A metadata field declared without a `type` raised an undefined-key error.
- `thumbnailUrl()` ignored a configured optimiser.

### Notes
- A TipTap integration is not included: `awcodes/filament-tiptap-editor` does not
  support Filament v5, so there is no editor to integrate with yet.

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

[Unreleased]: https://github.com/batustun/filament-media-library/compare/v1.4.4...HEAD
[1.4.4]: https://github.com/batustun/filament-media-library/compare/v1.4.3...v1.4.4
[1.4.3]: https://github.com/batustun/filament-media-library/compare/v1.4.2...v1.4.3
[1.4.2]: https://github.com/batustun/filament-media-library/compare/v1.4.1...v1.4.2
[1.4.1]: https://github.com/batustun/filament-media-library/compare/v1.4.0...v1.4.1
[1.4.0]: https://github.com/batustun/filament-media-library/compare/v1.3.0...v1.4.0
[1.3.0]: https://github.com/batustun/filament-media-library/compare/v1.2.0...v1.3.0
[1.2.0]: https://github.com/batustun/filament-media-library/compare/v1.1.3...v1.2.0
[1.1.3]: https://github.com/batustun/filament-media-library/compare/v1.1.2...v1.1.3
[1.1.2]: https://github.com/batustun/filament-media-library/compare/v1.1.1...v1.1.2
[1.1.1]: https://github.com/batustun/filament-media-library/compare/v1.1.0...v1.1.1
[1.1.0]: https://github.com/batustun/filament-media-library/compare/v1.0.0...v1.1.0
[1.0.0]: https://github.com/batustun/filament-media-library/releases/tag/v1.0.0
