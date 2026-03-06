import ButtonView from '@ckeditor/ckeditor5-ui/src/button/buttonview'

export default class TerminPicker {
    constructor(editor) {
        this.editor = editor
        this.config = editor.config
        this.model = editor.model
        this.data = editor.data
        this.ui = editor.ui
    }

    static get pluginName() {
        return 'terminPicker'
    }

    static get requires() {
        return []
    }

    get attribute() {
        return this.config.get('attribute')
    }

    init() {
        this._defineSchema()
        this._defineConverters()
        this.ui.componentFactory.add('terminPicker', this.createButton.bind(this))
        Nova.$on(`ckeditor:termin:${this.attribute}:insert`,       this.insertTermin.bind(this))
        Nova.$on(`ckeditor:termin:${this.attribute}:trigger-open`, this.openModal.bind(this))
        this.editor.on('ready', this._attachClickListener.bind(this))
    }

    destroy() {
        Nova.$off(`ckeditor:termin:${this.attribute}:insert`,       this.insertTermin.bind(this))
        Nova.$off(`ckeditor:termin:${this.attribute}:trigger-open`, this.openModal.bind(this))
        if (this._editorClickHandler) {
            const domRoot = this.editor.editing.view.getDomRoot()
            if (domRoot) domRoot.removeEventListener('click', this._editorClickHandler)
        }
    }

    _attachClickListener() {
        const domRoot = this.editor.editing.view.getDomRoot()
        if (!domRoot) return

        this._editorClickHandler = (e) => {
            const span = e.target.closest('.ck-termin-highlight')
            if (span) {
                const rect = span.getBoundingClientRect()
                Nova.$emit(`ckeditor:termin:${this.attribute}:preview`, {
                    terminId: span.getAttribute('data-id'),
                    rect: { top: rect.top, left: rect.left, width: rect.width, height: rect.height },
                })
            } else {
                Nova.$emit(`ckeditor:termin:${this.attribute}:preview:hide`)
            }
        }

        domRoot.addEventListener('click', this._editorClickHandler)
    }

    _defineSchema() {
        this.model.schema.extend('$text', {
            allowAttributes: ['terminId'],
        })

        this.model.schema.setAttributeProperties('terminId', {
            isFormatting: true,
            copyOnEnter: false,
        })
    }

    _defineConverters() {
        const conversion = this.editor.conversion

        // HTML → Model (loading saved content / paste)
        conversion.for('upcast').elementToAttribute({
            view: {
                name: 'span',
                classes: ['termin'],
                attributes: { 'data-id': true },
            },
            model: {
                key: 'terminId',
                value: viewElement => viewElement.getAttribute('data-id'),
            },
        })

        // Model → Editing view (what the editor shows, with visual highlight)
        conversion.for('editingDowncast').attributeToElement({
            model: 'terminId',
            view: (terminId, { writer }) => {
                return writer.createAttributeElement(
                    'span',
                    { class: 'termin ck-termin-highlight', 'data-id': terminId },
                    { priority: 5 }
                )
            },
        })

        // Model → Data view (saved to DB — clean, no editor-specific classes)
        conversion.for('dataDowncast').attributeToElement({
            model: 'terminId',
            view: (terminId, { writer }) => {
                return writer.createAttributeElement(
                    'span',
                    { class: 'termin', 'data-id': terminId },
                    { priority: 5 }
                )
            },
        })
    }

    createButton(locale) {
        const { t } = locale
        const view = new ButtonView(locale)

        view.set({
            label: t('Термин'),
            withText: true,
            tooltip: false,
            class: 'ck-termin-btn',
        })

        view.on('execute', this.openModal.bind(this))
        view.set('isEnabled', !this.config.get('isReadOnly'))

        return view
    }

    openModal() {
        const selection = this.model.document.selection
        let terminId = null

        // If cursor is collapsed inside an existing termin span — expand the
        // selection to cover the full span so insertTermin can replace it cleanly.
        if (selection.isCollapsed && selection.hasAttribute('terminId')) {
            terminId = selection.getAttribute('terminId')

            this.model.change(writer => {
                const pos    = selection.getFirstPosition()
                const parent = pos.parent
                let start    = pos.offset
                let end      = pos.offset
                let offset   = 0

                for (const child of parent.getChildren()) {
                    const childEnd = offset + child.offsetSize
                    if (child.is('$text') && child.getAttribute('terminId') === terminId) {
                        start = Math.min(start, offset)
                        end   = Math.max(end, childEnd)
                    }
                    offset = childEnd
                }

                writer.setSelection(
                    writer.createRange(
                        writer.createPositionAt(parent, start),
                        writer.createPositionAt(parent, end),
                    )
                )
            })
        } else if (!selection.isCollapsed && selection.hasAttribute('terminId')) {
            // Text with a termin attribute is selected
            terminId = selection.getAttribute('terminId')
        }

        const activeSelection = this.model.document.selection
        const selectedText = Array.from(activeSelection.getFirstRange().getItems())
            .filter(item => item.is('$text') || item.is('$textProxy'))
            .map(item => item.data)
            .join('')

        Nova.$emit(`ckeditor:termin:${this.attribute}:open`, { selectedText, terminId })
    }

    insertTermin({ id, text }) {
        this.model.change(writer => {
            const selection = this.model.document.selection
            const range = selection.getFirstRange()

            if (!range.isCollapsed) {
                writer.remove(range)
            }

            const textNode = writer.createText(text, { terminId: String(id) })
            this.model.insertContent(textNode)
        })
    }

    get icon() {
        return `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
            <path d="M2 3.5A1.5 1.5 0 0 1 3.5 2h13A1.5 1.5 0 0 1 18 3.5v2A1.5 1.5 0 0 1 16.5 7H15v1h1a1 1 0 0 1 1 1v7a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V9a1 1 0 0 1 1-1h1V7H3.5A1.5 1.5 0 0 1 2 5.5v-2zM5 7h10V4H5v3zm0 3v5h10v-5H5zm2 1h6v1H7v-1zm0 2h4v1H7v-1z"/>
        </svg>`
    }
}
