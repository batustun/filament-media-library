# Changelog

All notable changes to `batustun/filament-media-library` are documented here.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.8.1] - 2026-09-13

### Added
- `media-library:doctor` now reports tenancy: whether it is on, which column it
  uses, how many distinct tenants are in the index, and how many rows belong to
  no tenant and so are invisible to every one of them. With tenancy off it says
  plainly that every panel sees every file, which is what "the tenant still sees
  everything" almost always turns out to be.
- The tenant scope is now tested inside a real tenanted Filament panel — the
  picker, the folder sidebar and the stamping of new uploads — rather than only
  against a key held by hand. It holds.

## [1.8.0] - 2026-09-13

### Security
- **One tenant could read, overwrite and delete another tenant's files.** The
  tenant scope is a global scope on the model, which covers every Eloquent path
  — but it asks Filament for the current tenant, and the package's own HTTP
  routes run on plain `web` middleware, outside any panel, where Filament knows
  of no tenant. The scope then applied no filter at all, so
  `GET /media-library/{id}/download`, the preview endpoints,
  `POST /media-library/{id}/image` and `DELETE /media-library/{id}` answered for
  any id to any authenticated user with the matching ability.

  The scope now fails closed: with no tenant determinable it hides everything
  tenanted. A panel that does not use tenancy (the usual admin panel) and
  having no request to serve at all (`sync`, `doctor`, queue workers) are the
  only exceptions. The links the package mints for those routes are now signed,
  and the signature is what vouches for the one file it names.

  Anyone running tenanted panels with `MEDIA_LIBRARY_TENANCY=true` should
  upgrade. With it off — the default — every tenant could already see every
  file through the picker itself, which is now called out in the README and the
  config.
- Chunked uploads are stamped with the tenant their signed endpoint names.
  Running outside the panel, they were stored belonging to nobody and then
  vanished from the tenant that had just uploaded them.

### Added
- `MEDIA_LIBRARY_TENANCY_SHARED`, which makes media belonging to no tenant —
  what an admin panel uploads — visible to every tenant, for a library of
  common assets. Off by default, and it never exposes one tenant to another.
- `Media::actingForTenant()`, for code that has to run as a tenant Filament
  cannot resolve on its own.

### Changed
- `POST /media-library/upload` and `DELETE /media-library/{media}`, which the
  package's own interface never calls, are subject to the same rule: with
  tenancy on and no tenant determinable they answer for untenanted media only.
  Applications calling them from outside a panel should sign the URL or add
  their panel's middleware to `filament-media-library.routes.middleware`.

## [1.7.0] - 2026-09-13

### Fixed
- On the library page, creating, renaming, deleting and moving folders and files
  did nothing at all. One set of JavaScript drives both the page and the picker,
  and it calls `createFolder`, `renameMedia`, `deleteMedia`, `deleteFolder`,
  `moveItemTo` and `moveSelectionTo` — every one of which only the picker
  defined. A Livewire method that does not exist fails in the browser, so the
  page had been silently missing half its actions.
- The picker always opened at the top of the library with nothing selected, even
  when the field already held a file. It now opens in that file's folder with it
  selected, whichever shape the field stores — id, path or URL.
- The picker's footer sat above the modal's own, so two footer rows split a band
  of height between them and the select button never lined up with close. The
  modal no longer renders a footer; the picker owns the one row.
- The page's "details saved" notification printed a raw translation key:
  `messages.meta_updated` had never been defined in any language.
- Bulk delete was in the toolbar both hosts render but defined only on the page.
- Right-clicking a file did nothing, having been disabled in 1.6.0 along with
  the broken dialog it used to open.

### Added
- A right-click menu: preview, details, select or deselect, copy URL, open in a
  new tab, download, rename, move and delete, each shown only where it applies.
- Downloading works across origins — a CDN ignores the `download` attribute, so
  the bytes are fetched and handed over as a blob.
- Moving a single file, which previously had no prompt of its own.
- `WireContractTest` renders each host and checks that every method its markup
  and the shared JavaScript call actually exists on it. Seven of the fixes above
  are failures it reported.

### Changed
- Every action the front end calls now has one definition, on the shared trait,
  instead of one per host under two different names and with two different
  notification bodies. The library page went from roughly two hundred lines to
  seventy-five.
- Removed `copyUrlOf()` and `renameOne()`, which nothing called.

## [1.6.0] - 2026-09-13

### Fixed
- Choosing a file uploaded nothing. Files were staged behind a second button
  whose only visible change was its own tint — no name, no count, no preview —
  so picking a file was indistinguishable from the upload having failed, and
  nobody knew there was a second step. Files now upload the moment they are
  chosen or dropped, with a progress percentage while the bytes are in flight,
  and the button is gone.
- Right-clicking a file opened an empty modal. The card asked for a `context`
  dialog that the markup never defined and no handler ever answered.
- Nothing could be seen at full size. The picker had no detail panel at all and
  the page's is a sidebar; there was no way to actually look at a file.
- Clicking the chosen file again did not clear it. A single-value field could be
  changed but never emptied by the same gesture.
- The folder sidebar stopped short of the panel it sits in, and the picker's
  results were capped at 46% of the viewport, leaving the footer floating
  mid-modal with dead space beneath it. Both filled a fixed fraction of the
  screen rather than the space they were given.
- The library page's detail panel became unreachable and the uploader's
  destination hint named the folder being browsed rather than the one files
  actually land in.
- `user_model` now follows `config('auth.providers.users.model')` when it is not
  set explicitly, instead of assuming `App\Models\User`.

### Added
- A full-size preview. A magnifier on each card, a double-click, or Enter opens
  the file as large as it fits — images, video, audio and PDFs inline, anything
  else with a link out — and the arrow keys step through the grid without
  closing it.
- The folder tree collapses. It arrives closed so a deep library does not bury
  its top level, opens a level at a time, and keeps the folder being browsed
  visible. A control beside "new folder" opens or closes the whole tree.
- `adoptUploads()`, so files sent through the chunked endpoint — which bypasses
  Livewire entirely — are announced and selected like any other upload.

### Changed
- `MediaPicker::uploadAndApply()` and `MediaLibrary::uploadFiles()` are now one
  `storeUploads()` on the shared trait, with `afterUpload()` and
  `uploadTargetDirectory()` as the points where the two hosts differ. The
  upload notification had been copied into both.
- The card's corner is one `.fml-card__tools` cluster rather than three
  separately positioned elements; `.fml-card__badge` and `.fml-card__peek` are
  gone. Run `php artisan filament:assets` after upgrading.
- Removed the `actions.upload`, `actions.uploading` and `messages.preparing`
  translations, which the second upload step needed and nothing else uses.

## [1.5.2] - 2026-09-12

### Fixed
- The browser's JavaScript never ran. Four separate mistakes in the markup each
  produced a syntax error in the browser and nothing anywhere else — the page
  rendered, every test passed, and clicking did nothing:
  - A Blade directive written inside a *component tag* attribute is never
    compiled. Blade compiles component tags first, lifting each attribute into
    a PHP string, so `@js(...)` reached the browser as literal text and the
    handler died on the `@`. This broke renaming, deleting and moving folders,
    the move-selection and rename-file actions, and the row checkbox.
  - A directive also swallows the newline that follows it — Blade pads that
    whitespace for echoes but not for directives — which ran two JavaScript
    statements together into one line. This is what actually killed 1.5.1's
    picker bridge: the fix was right, but `const returns = 'path'` and the line
    after it collapsed into a syntax error, so the handler was never defined.
  - Alpine only wraps an inline expression in a function body when it *begins*
    with `if`, `let` or `const`. The chunked uploader's handler began with a
    comment, so its `const` was a syntax error and large uploads never sliced.
  - The same handler called `$wire.\$refresh()`; the backslash was a PHP
    escaping habit and is a syntax error in JavaScript.
  Because Alpine aborts the rest of the tree when a directive throws during
  initialisation, any one of these also took out every component after it —
  which is why the folder dialogs would not open.
- `user_model` now follows `config('auth.providers.users.model')` when it is not
  set explicitly, instead of assuming `App\Models\User`. An application that
  renamed or moved its user model fataled when a file's detail pane was opened.

### Changed
- The picker-to-field bridge moved out of the markup and into
  `window.fmlPickerBridge`, and the chunked uploader's change handler into
  `interceptChange()`. Logic in an `x-data` attribute is compiled in the
  browser, where PHP cannot see it fail.

### Added
- `BladeRenderingTest` renders every view the package ships — the picker in both
  layouts, the library page, and the field bridge — then asserts no Blade
  directive survived uncompiled and parses every Alpine expression with Node,
  wrapping each one the way Alpine does. Three lints back it up: no directive
  inside a component tag, no `@js` in a view, and a check that every shipped
  view is actually reached by a test. All four bugs above fail these tests.
- The test suite can now render Livewire components. It never could before
  because Filament's `SupportServiceProvider` rebinds Livewire's `DataStore` to
  a subclass with a non-shared binding; registering Livewire after Filament —
  the order a real application gets — restores the singleton.

## [1.5.1] - 2026-09-12

### Fixed
- Choosing a file could do nothing at all. The bridge that writes the selection
  into the form field looked the component up by hand — `Livewire.find()` on a
  `wire:id` it walked the DOM for — and when that lookup missed it returned
  without a word. It now uses `$wire`, Livewire's supported handle on the
  closest component, and any failure raises a notification saying what went
  wrong instead of leaving the button looking dead.

## [1.5.0] - 2026-09-12

### Added
- **Folder cards in the grid.** A folder whose files all live a level deeper
  used to show "no files here" — which no file manager would do. The folders
  directly inside the one you are browsing now appear as cards, each with a
  count of everything beneath it.
- **A refresh button** in the toolbar, for when the library changed elsewhere.

### Fixed
- **The picker said nothing after an upload.** A byte-identical file is reused
  rather than stored again, so no new card appears — and in silence that is
  indistinguishable from a failed upload. It now reports what happened, and
  says explicitly when an existing copy was used instead.

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

[Unreleased]: https://github.com/batustun/filament-media-library/compare/v1.8.1...HEAD
[1.8.1]: https://github.com/batustun/filament-media-library/compare/v1.8.0...v1.8.1
[1.8.0]: https://github.com/batustun/filament-media-library/compare/v1.7.0...v1.8.0
[1.7.0]: https://github.com/batustun/filament-media-library/compare/v1.6.0...v1.7.0
[1.6.0]: https://github.com/batustun/filament-media-library/compare/v1.5.2...v1.6.0
[1.5.2]: https://github.com/batustun/filament-media-library/compare/v1.5.1...v1.5.2
[1.5.1]: https://github.com/batustun/filament-media-library/compare/v1.5.0...v1.5.1
[1.5.0]: https://github.com/batustun/filament-media-library/compare/v1.4.4...v1.5.0
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
