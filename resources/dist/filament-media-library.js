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

        // ----------------------------------------------------------- keyboard

        cards() {
            return Array.from(this.$refs.grid?.querySelectorAll('[data-media-id]') ?? [])
        },

        moveCursor(delta) {
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
                'delete-media': () => this.$wire.deleteMedia(dialog.payload.id),
                'delete-folder': () => this.$wire.deleteFolder(dialog.payload.path),
            }

            actions[dialog.kind]?.()

            this.closeDialog()
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
