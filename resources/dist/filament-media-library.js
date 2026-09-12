/**
 * Filament Media Library — browser interactions.
 *
 * One Alpine component drives both the full page and the modal picker, so the
 * keyboard model, range selection and drag-and-drop behave identically in both
 * places. Registered through FilamentAsset, so Alpine is already on the page.
 */
window.fmlBrowser = function ({ mode = 'page' } = {}) {
    return {
        mode,

        /** Index of the card the keyboard is on, -1 when nothing is focused. */
        cursor: -1,

        /** Last index clicked, so Shift+click knows where a range starts. */
        anchor: null,

        dragActive: false,
        draggingId: null,
        dropFolder: null,

        dialog: null,

        /** The file being shown full size, or null. */
        preview: null,

        /** The right-click menu: the file it is about, and where to put it. */
        contextMenu: null,

        /**
         * Folder paths whose children are on show.
         *
         * The tree arrives flat and fully expanded, which buries the top level
         * once a library has any depth; it starts closed and opens on demand.
         */
        expandedFolders: [],

        init() {
            this.$watch('dialog', (value) => {
                if (! value) return

                this.$nextTick(() => this.$refs.dialogInput?.focus())
            })
        },

        // ---------------------------------------------------------- selection

        /**
         * Shift+click selects the range between the last click and this one,
         * which is what every file manager does and what makes selecting forty
         * thumbnails bearable.
         */
        pick(index, id, event) {
            const ids = this.itemIds()

            if (event?.shiftKey && this.anchor !== null) {
                const [from, to] = [this.anchor, index].sort((a, b) => a - b)

                this.$wire.selectRange(ids.slice(from, to + 1))

                return
            }

            this.anchor = index

            if (event?.metaKey || event?.ctrlKey || this.mode === 'page') {
                this.$wire.toggleSelect(id)

                return
            }

            this.$wire.toggleSelectFor(id)
        },

        itemIds() {
            return Array.from(this.$refs.grid?.querySelectorAll('[data-media-id]') ?? [])
                .map((node) => node.dataset.mediaId)
        },

        // ------------------------------------------------------------- folders

        isFolderOpen(path) {
            return this.expandedFolders.includes(path)
        },

        /**
         * A folder shows only while every one of its ancestors is open.
         *
         * `forced` covers the folder currently being browsed and its ancestors:
         * the tree must never hide where you are standing.
         */
        isFolderVisible(path, forced = false) {
            if (forced) return true

            const segments = path.split('/')
            segments.pop()

            let ancestor = ''

            for (const segment of segments) {
                ancestor = ancestor ? `${ancestor}/${segment}` : segment

                if (! this.expandedFolders.includes(ancestor)) return false
            }

            return true
        },

        toggleFolder(path) {
            this.expandedFolders = this.isFolderOpen(path)
                ? this.expandedFolders.filter((open) => open !== path)
                : [...this.expandedFolders, path]
        },

        /** @param {string[]} paths every folder that has children */
        allFoldersOpen(paths) {
            return paths.length > 0 && paths.every((path) => this.expandedFolders.includes(path))
        },

        /** @param {string[]} paths every folder that has children */
        toggleAllFolders(paths) {
            this.expandedFolders = this.allFoldersOpen(paths) ? [] : [...paths]
        },

        // ------------------------------------------------------- context menu

        /**
         * Open the menu at the pointer, on the file that was clicked.
         *
         * Everything it offers is already reachable elsewhere; having it under
         * the right mouse button is what makes a file manager feel like one.
         */
        openContextMenu(index, event) {
            const card = this.cards()[index]

            if (! card) return

            this.closePreview()

            this.contextMenu = {
                ...card.dataset,
                index,
                selected: card.getAttribute('aria-selected') === 'true',
                x: event.clientX,
                y: event.clientY,
            }

            // Placed after render, when the menu's size is known, so it never
            // opens off the bottom or the side of the window.
            this.$nextTick(() => {
                const menu = this.$refs.contextMenu

                if (! menu || ! this.contextMenu) return

                const { width, height } = menu.getBoundingClientRect()

                this.contextMenu.x = Math.max(8, Math.min(this.contextMenu.x, window.innerWidth - width - 8))
                this.contextMenu.y = Math.max(8, Math.min(this.contextMenu.y, window.innerHeight - height - 8))

                menu.focus()
            })
        },

        closeContextMenu() {
            this.contextMenu = null
        },

        /** Run one menu entry and get the menu out of the way. */
        fromContextMenu(action) {
            const item = this.contextMenu

            this.closeContextMenu()

            if (item) action(item)
        },

        // ------------------------------------------------------------ preview

        /**
         * Show one file at full size.
         *
         * Everything the overlay needs is already on the card as data
         * attributes, so opening a preview costs no round trip and works the
         * same in the page and the modal picker.
         */
        openPreviewAt(index) {
            const card = this.cards()[index]

            if (! card) return

            this.preview = { ...card.dataset, index }

            // Focus moves in so Escape closes the preview and not the modal
            // the picker itself lives in.
            this.$nextTick(() => this.$refs.lightbox?.focus())
        },

        /** Step through the files on screen without closing the overlay. */
        movePreview(delta) {
            if (! this.preview) return

            const next = Number(this.preview.index) + delta

            if (next < 0 || next >= this.cards().length) return

            this.openPreviewAt(next)
        },

        closePreview() {
            this.preview = null
        },

        // ----------------------------------------------------------- keyboard

        cards() {
            return Array.from(this.$refs.grid?.querySelectorAll('[data-media-id]') ?? [])
        },

        moveCursor(delta) {
            // The preview owns the arrow keys while it is open.
            if (this.preview) {
                this.movePreview(delta > 0 ? 1 : -1)

                return
            }

            const cards = this.cards()
            if (! cards.length) return

            this.cursor = Math.max(0, Math.min(cards.length - 1, this.cursor + delta))
            cards[this.cursor]?.focus()
        },

        /** How many cards fit on a row, so Up/Down move a visual row. */
        columns() {
            const cards = this.cards()
            if (cards.length < 2) return 1

            const top = cards[0].getBoundingClientRect().top
            const index = cards.findIndex((card) => card.getBoundingClientRect().top > top)

            return index === -1 ? cards.length : index
        },

        // --------------------------------------------------------- drag & drop

        /** Files dragged in from the operating system. */
        dropFiles(event) {
            this.dragActive = false

            const files = event.dataTransfer?.files
            if (! files?.length) return

            const input = this.$refs.fileInput
            if (! input) return

            // Re-assign through a DataTransfer so Livewire's wire:model
            // observer sees a genuine change event.
            const transfer = new DataTransfer()
            for (const file of files) transfer.items.add(file)

            input.files = transfer.files
            input.dispatchEvent(new Event('change', { bubbles: true }))
        },

        /** A card dragged onto a folder in the sidebar. */
        startDragging(id) {
            this.draggingId = id
        },

        stopDragging() {
            this.draggingId = null
            this.dropFolder = null
        },

        dropOnFolder(path) {
            const id = this.draggingId

            this.stopDragging()

            if (! id) return

            this.$wire.moveItemTo(id, path)
        },

        // ------------------------------------------------------------ dialogs

        openDialog(kind, payload = {}, value = '') {
            this.dialog = { kind, payload, value }
        },

        closeDialog() {
            this.dialog = null
        },

        submitDialog() {
            const dialog = this.dialog
            if (! dialog) return

            const value = (dialog.value ?? '').trim()

            const actions = {
                'new-folder': () => value && this.$wire.createFolder(value),
                'rename-folder': () => value && this.$wire.renameFolder(dialog.payload.path, value),
                'rename-media': () => value && this.$wire.renameMedia(dialog.payload.id, value),
                'move-selection': () => this.$wire.moveSelectionTo(value),
                'move-media': () => this.$wire.moveItemTo(dialog.payload.id, value),
                'delete-media': () => this.$wire.deleteMedia(dialog.payload.id),
                'delete-folder': () => this.$wire.deleteFolder(dialog.payload.path),
            }

            actions[dialog.kind]?.()

            this.closeDialog()
        },

        /**
         * Save a file without navigating away.
         *
         * A cross-origin CDN ignores the `download` attribute and opens the
         * file instead, so the bytes are fetched and handed over as a blob.
         */
        async download(url, name) {
            const link = document.createElement('a')

            link.download = name || ''
            link.rel = 'noopener'
            link.href = url

            try {
                const response = await fetch(url, { credentials: 'omit' })

                if (response.ok) {
                    link.href = URL.createObjectURL(await response.blob())
                }
            } catch (error) {
                // Blocked by CORS: fall back to the plain link.
            }

            document.body.append(link)
            link.click()
            link.remove()

            if (link.href.startsWith('blob:')) URL.revokeObjectURL(link.href)
        },

        // ------------------------------------------------------------ clipboard

        async copy(text) {
            try {
                await navigator.clipboard.writeText(text)
                this.copied = true
                setTimeout(() => { this.copied = false }, 1600)
            } catch {
                // Clipboard access can be denied; the URL stays selectable.
            }
        },

        copied: false,
    }
}

/**
 * Chunked uploader.
 *
 * A single POST is capped by PHP's upload_max_filesize and post_max_size, which
 * the application cannot raise at runtime. Files above the configured threshold
 * are sliced here and reassembled server-side, so the cap stops mattering.
 */
window.fmlUploader = function ({ endpoint, csrf, chunkSize, threshold, disk, directory, progressLabel, chunkLabel }) {
    return {
        /** Percentage of the Livewire transfer in flight, null while idle. */
        percent: null,

        // Chunked transfer, for files too large to survive a single POST.
        busy: false,
        done: 0,
        total: 0,
        error: null,

        shouldChunk(file) {
            return Boolean(endpoint) && file.size > threshold
        },

        /**
         * One line describing whichever transfer is running, or null when idle.
         *
         * Both routes report through here so the dropzone has a single busy
         * state instead of two overlays that can show at once.
         */
        busyLabel() {
            if (this.busy) {
                return chunkLabel.replace(':done', this.done).replace(':total', this.total)
            }

            return this.percent === null ? null : progressLabel.replace(':percent', this.percent)
        },

        /** Uploads one file; resolves with the created media payload. */
        async upload(file) {
            const uuid = crypto.randomUUID
                ? crypto.randomUUID()
                : 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (c) => {
                    const r = (Math.random() * 16) | 0
                    return (c === 'x' ? r : (r & 0x3) | 0x8).toString(16)
                })

            this.total = Math.ceil(file.size / chunkSize)
            this.done = 0
            this.busy = true
            this.error = null

            try {
                for (let index = 0; index < this.total; index++) {
                    const start = index * chunkSize
                    const slice = file.slice(start, Math.min(start + chunkSize, file.size))

                    const body = new FormData()
                    body.append('uuid', uuid)
                    body.append('index', index)
                    body.append('total', this.total)
                    body.append('name', file.name)
                    body.append('chunk', slice, 'chunk')
                    if (disk) body.append('disk', disk)
                    if (directory) body.append('directory', directory)

                    const response = await fetch(endpoint, {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': csrf, Accept: 'application/json' },
                        body,
                    })

                    if (! response.ok) {
                        const payload = await response.json().catch(() => ({}))
                        throw new Error(payload.message || `Upload failed (${response.status})`)
                    }

                    this.done = index + 1

                    // The final part responds with the created record.
                    if (this.done === this.total) {
                        return (await response.json()).data
                    }
                }
            } catch (error) {
                this.error = error.message
                throw error
            } finally {
                this.busy = false
            }
        },

        /**
         * Takes the oversized files off the file input before Livewire's own
         * uploader sees them.
         *
         * This lives here rather than inline in the markup because Alpine only
         * wraps an inline expression in a function body when it *starts* with
         * `if`, `let` or `const` — a leading comment defeats that check and the
         * whole handler dies with a syntax error.
         */
        async interceptChange(event) {
            const oversized = Array.from(event.target.files ?? []).filter((file) => this.shouldChunk(file))

            if (! oversized.length) return

            // Runs before the first await, so it still stops the capture phase.
            event.stopPropagation()

            const input = event.target
            const ids = []

            try {
                for (const file of oversized) {
                    const media = await this.upload(file)

                    if (media?.id) ids.push(media.id)
                }
            } catch (error) {
                return // upload() already put the reason in `error`.
            } finally {
                input.value = ''
            }

            // Livewire never saw these, so the component is told explicitly.
            this.$wire.adoptUploads(ids)
        },
    }
}

/**
 * Bridges the picker's selection into the form field it was opened from.
 *
 * The picker is a nested Livewire component and cannot reach the field, so it
 * dispatches on the window; this bridge is rendered inside the FIELD's own
 * component and writes through $wire — Livewire's supported handle on the
 * closest component. Hand-resolving it from a wire:id lookup was one
 * assumption too many, and when it missed, it missed in silence.
 */
window.fmlPickerBridge = function ({ statePath, returns, multiple, failureTitle }) {
    const uuid = () => (crypto.randomUUID
        ? crypto.randomUUID()
        : 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (c) => {
            const r = (Math.random() * 16) | 0
            return (c === 'x' ? r : (r & 0x3) | 0x8).toString(16)
        }))

    // FileUpload keeps its raw state as a UUID-keyed object; a plain string or
    // an indexed array breaks getRawState() on the next Livewire round trip.
    const keyed = (values) => Object.fromEntries(values.map((value) => [uuid(), value]))

    const read = (item) => {
        if (returns === 'id') return item.id

        return returns === 'path' ? item.path : item.url
    }

    return {
        write(event) {
            if (event.detail?.statePath !== statePath) return

            const values = (event.detail.items || []).map(read).filter(Boolean)

            if (! values.length) {
                this.fail('the library returned nothing to insert')

                return
            }

            if (typeof this.$wire === 'undefined' || this.$wire === null) {
                this.fail('the form component could not be reached')

                return
            }

            try {
                this.$wire.set(statePath, multiple
                    ? { ...this.existing(), ...keyed(values) }
                    : keyed([values[0]]))
            } catch (error) {
                this.fail(error.message)

                return
            }

            // Close the action modal the picker was opened from. The field is
            // already filled, so a failure here must not read as a failed pick.
            try {
                this.$wire.unmountAction()
            } catch (error) {
                //
            }
        },

        /** Whatever the field already holds, but only if it is safe to spread. */
        existing() {
            const current = this.$wire.get(statePath)

            return current && typeof current === 'object' && ! Array.isArray(current) ? current : {}
        },

        /** Never fail quietly: a picker that does nothing is unexplainable. */
        fail(reason) {
            console.error('[filament-media-library] could not insert the selection:', reason)

            window.dispatchEvent(new CustomEvent('filament-media-library:failed', {
                detail: { statePath, reason },
            }))
        },

        /**
         * Close the modal on the picker's behalf.
         *
         * The picker renders the close button — it owns the footer — but only
         * the field's own component can unmount the action that opened it.
         */
        dismiss(event) {
            if (event.detail?.statePath !== statePath) return

            try {
                this.$wire.unmountAction()
            } catch (error) {
                //
            }
        },

        /** Only the field that failed says so — a page may hold several. */
        notifyFailure(event) {
            if (event.detail?.statePath !== statePath) return

            new FilamentNotification()
                .title(failureTitle)
                .body(event.detail.reason)
                .danger()
                .send()
        },
    }
}

/**
 * Canvas image editor: crop, rotate, flip.
 *
 * Written against the 2D canvas rather than an image library, so the package
 * keeps its no-build, no-dependency promise. The canvas re-encodes the whole
 * image, which is why saving replaces the original in place.
 */
window.fmlImageEditor = function ({ src, endpoint, csrf, mime }) {
    return {
        open: false,
        saving: false,
        rotation: 0,
        flipX: false,
        flipY: false,
        crop: null,          // { x, y, w, h } in displayed pixels
        dragging: null,
        image: null,

        async start() {
            this.open = true
            this.reset()

            await this.$nextTick()

            this.image = new Image()
            this.image.crossOrigin = 'anonymous'
            this.image.onload = () => this.draw()
            // Cache-bust so a previous edit is never what you edit next.
            this.image.src = src + (src.includes('?') ? '&' : '?') + 'v=' + Date.now()
        },

        reset() {
            this.rotation = 0
            this.flipX = false
            this.flipY = false
            this.crop = null
            this.draw()
        },

        rotate() {
            this.rotation = (this.rotation + 90) % 360
            this.crop = null
            this.draw()
        },

        draw() {
            const canvas = this.$refs.canvas
            if (! canvas || ! this.image) return

            const swap = this.rotation % 180 !== 0
            const w = swap ? this.image.height : this.image.width
            const h = swap ? this.image.width : this.image.height

            canvas.width = w
            canvas.height = h

            const context = canvas.getContext('2d')
            context.clearRect(0, 0, w, h)
            context.save()
            context.translate(w / 2, h / 2)
            context.rotate((this.rotation * Math.PI) / 180)
            context.scale(this.flipX ? -1 : 1, this.flipY ? -1 : 1)
            context.drawImage(this.image, -this.image.width / 2, -this.image.height / 2)
            context.restore()
        },

        // ------------------------------------------------------- crop box

        startCrop(event) {
            const rect = this.$refs.canvas.getBoundingClientRect()
            this.dragging = { x: event.clientX - rect.left, y: event.clientY - rect.top }
            this.crop = { x: this.dragging.x, y: this.dragging.y, w: 0, h: 0 }
        },

        moveCrop(event) {
            if (! this.dragging) return

            const rect = this.$refs.canvas.getBoundingClientRect()
            const x = event.clientX - rect.left
            const y = event.clientY - rect.top

            this.crop = {
                x: Math.min(this.dragging.x, x),
                y: Math.min(this.dragging.y, y),
                w: Math.abs(x - this.dragging.x),
                h: Math.abs(y - this.dragging.y),
            }
        },

        endCrop() {
            this.dragging = null

            // A click without a drag is not a crop.
            if (this.crop && (this.crop.w < 8 || this.crop.h < 8)) {
                this.crop = null
            }
        },

        cropStyle() {
            if (! this.crop) return 'display:none'

            return `left:${this.crop.x}px;top:${this.crop.y}px;width:${this.crop.w}px;height:${this.crop.h}px`
        },

        // ----------------------------------------------------------- save

        async save() {
            const canvas = this.$refs.canvas
            if (! canvas) return

            this.saving = true

            try {
                const output = this.crop ? this.cropped(canvas) : canvas
                const blob = await new Promise((resolve) => output.toBlob(resolve, mime || 'image/jpeg', 0.92))

                // Give the blob a real extension: some proxies and servers
                // sniff the part filename rather than its Content-Type.
                const extension = (blob.type.split('/')[1] || 'jpg').replace('jpeg', 'jpg')

                const body = new FormData()
                body.append('image', blob, `edited.${extension}`)

                const response = await fetch(endpoint, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrf, Accept: 'application/json' },
                    body,
                })

                if (! response.ok) throw new Error(`Save failed (${response.status})`)

                this.open = false
                this.$wire.$refresh()
            } finally {
                this.saving = false
            }
        },

        /** The crop box is in displayed pixels; the canvas may be larger. */
        cropped(canvas) {
            const rect = canvas.getBoundingClientRect()
            const scaleX = canvas.width / rect.width
            const scaleY = canvas.height / rect.height

            const target = document.createElement('canvas')
            target.width = Math.max(1, Math.round(this.crop.w * scaleX))
            target.height = Math.max(1, Math.round(this.crop.h * scaleY))

            target.getContext('2d').drawImage(
                canvas,
                this.crop.x * scaleX,
                this.crop.y * scaleY,
                target.width,
                target.height,
                0,
                0,
                target.width,
                target.height,
            )

            return target
        },
    }
}

/**
 * Fetches a preview for a file the browser cannot render itself: a bounded
 * window of a text file, or the entry list of an archive.
 *
 * Read through the application, so the permission check applies and a private
 * disk works — and so a huge file cannot be pulled into the panel by clicking
 * on it.
 */
window.fmlFilePreview = function ({ endpoint, kind }) {
    return {
        loading: true,
        error: null,
        content: '',
        rows: null,
        entries: [],
        truncated: false,

        async load() {
            try {
                const response = await fetch(endpoint, { headers: { Accept: 'application/json' } })

                if (! response.ok) throw new Error(`HTTP ${response.status}`)

                const payload = await response.json()

                if (! payload.ok) {
                    this.error = payload.reason === 'binary'
                        ? 'Binary file'
                        : 'Preview unavailable'

                    return
                }

                this.truncated = Boolean(payload.truncated)

                if (kind === 'archive') {
                    this.entries = payload.entries ?? []

                    return
                }

                this.content = payload.content ?? ''
                this.rows = payload.csv ? this.parseCsv(this.content) : null
            } catch (error) {
                this.error = 'Preview unavailable'
            } finally {
                this.loading = false
            }
        },

        /**
         * Enough CSV to render a readable table: quoted fields, escaped quotes
         * and newlines inside quotes. Not a parser for arbitrary dialects —
         * this is a preview, not an import.
         */
        parseCsv(text) {
            const rows = []
            let row = []
            let field = ''
            let quoted = false

            for (let i = 0; i < text.length; i++) {
                const char = text[i]

                if (quoted) {
                    if (char === '"') {
                        if (text[i + 1] === '"') { field += '"'; i++ } else { quoted = false }
                    } else {
                        field += char
                    }

                    continue
                }

                if (char === '"') { quoted = true }
                else if (char === ',') { row.push(field); field = '' }
                else if (char === '\n') { row.push(field); rows.push(row); row = []; field = '' }
                else if (char !== '\r') { field += char }
            }

            if (field !== '' || row.length) { row.push(field); rows.push(row) }

            // Cap the table: a preview should stay glanceable.
            return rows.slice(0, 50)
        },

        humanSize(bytes) {
            if (! bytes) return '0 B'

            const units = ['B', 'KB', 'MB', 'GB']
            const power = Math.min(Math.floor(Math.log(bytes) / Math.log(1024)), units.length - 1)

            return `${(bytes / 1024 ** power).toFixed(power ? 1 : 0)} ${units[power]}`
        },
    }
}
