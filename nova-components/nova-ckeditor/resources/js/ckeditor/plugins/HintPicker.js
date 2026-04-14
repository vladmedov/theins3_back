import ButtonView from '@ckeditor/ckeditor5-ui/src/button/buttonview'

function utf8ToBase64(str) {
    return btoa(unescape(encodeURIComponent(str)))
}

function base64ToUtf8(b64) {
    try {
        return decodeURIComponent(escape(atob(b64)))
    } catch {
        return ''
    }
}

export default class HintPicker {
    constructor(editor) {
        this.editor = editor
        this.config = editor.config
        this.model = editor.model
        this.ui = editor.ui
        this._lastHintRange = null
        this._boundApply = this.applyHint.bind(this)
        this._boundTriggerOpen = this.openModal.bind(this)
    }

    static get pluginName() {
        return 'hintPicker'
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
        this.ui.componentFactory.add('hintPicker', this.createButton.bind(this))
        Nova.$on(`ckeditor:hint:${this.attribute}:apply`, this._boundApply)
        Nova.$on(`ckeditor:hint:${this.attribute}:trigger-open`, this._boundTriggerOpen)
        this.editor.on('ready', this._attachHintClickListener.bind(this))
    }

    destroy() {
        Nova.$off(`ckeditor:hint:${this.attribute}:apply`, this._boundApply)
        Nova.$off(`ckeditor:hint:${this.attribute}:trigger-open`, this._boundTriggerOpen)
        if (this._hintClickHandler) {
            const domRoot = this.editor.editing.view.getDomRoot()
            if (domRoot) {
                domRoot.removeEventListener('click', this._hintClickHandler)
            }
            this._hintClickHandler = null
        }
    }

    _attachHintClickListener() {
        const domRoot = this.editor.editing.view.getDomRoot()
        if (!domRoot) {
            return
        }

        this._hintClickHandler = (e) => {
            const span = e.target.closest('.ck-hint-highlight')
            if (!span) {
                Nova.$emit(`ckeditor:hint:${this.attribute}:preview:hide`)
                return
            }

            const viewElement = this.editor.editing.view.domConverter.mapDomToView(span)
            if (!viewElement || !viewElement.is('attributeElement')) {
                Nova.$emit(`ckeditor:hint:${this.attribute}:preview:hide`)
                return
            }

            const viewRange = this.editor.editing.view.createRangeIn(viewElement)
            let modelRange
            try {
                modelRange = this.editor.editing.mapper.toModelRange(viewRange)
            } catch {
                Nova.$emit(`ckeditor:hint:${this.attribute}:preview:hide`)
                return
            }

            // Do not call setSelection(modelRange): a full-range selection draws the heavy blue
            // widget-style highlight. Terms don't change selection on preview click; we only read the model.
            let hintHtml = ''
            for (const item of modelRange.getItems()) {
                if (
                    (item.is('$text') || item.is('$textProxy')) &&
                    item.hasAttribute('terminHintHtml')
                ) {
                    hintHtml = item.getAttribute('terminHintHtml') || ''
                    break
                }
            }

            const displayText = (span.textContent || '').trim()

            document.querySelectorAll('.ck-hint-highlight--active').forEach((el) => {
                el.classList.remove('ck-hint-highlight--active')
            })
            span.classList.add('ck-hint-highlight--active')

            const rect = span.getBoundingClientRect()
            Nova.$emit(`ckeditor:hint:${this.attribute}:preview`, {
                rect: {
                    top: rect.top,
                    left: rect.left,
                    width: rect.width,
                    height: rect.height,
                },
                hintHtml,
                displayText,
            })
        }

        domRoot.addEventListener('click', this._hintClickHandler)
    }

    _defineSchema() {
        this.model.schema.extend('$text', {
            allowAttributes: ['terminHintHtml'],
        })

        this.model.schema.setAttributeProperties('terminHintHtml', {
            isFormatting: true,
            copyOnEnter: false,
        })
    }

    _defineConverters() {
        const conversion = this.editor.conversion

        conversion.for('upcast').elementToAttribute({
            view: {
                name: 'span',
                classes: ['termin'],
                attributes: { 'data-description': true },
            },
            model: {
                key: 'terminHintHtml',
                value: (viewElement) => {
                    // Glossary term wins over hint if both attributes appear on one span.
                    if (viewElement.hasAttribute('data-id')) {
                        return null
                    }
                    return base64ToUtf8(viewElement.getAttribute('data-description') || '')
                },
            },
            converterPriority: 'low',
        })

        // Editing view: omit data-description (base64 can be huge and break the editable DOM). Model keeps terminHintHtml.
        // Use ck-hint-highlight (not ck-termin-highlight) so TerminPicker click/preview only targets glossary terms.
        conversion.for('editingDowncast').attributeToElement({
            model: 'terminHintHtml',
            view: (html, { writer }) => {
                return writer.createAttributeElement(
                    'span',
                    { class: 'termin ck-hint-highlight' },
                    { priority: 5 },
                )
            },
        })

        conversion.for('dataDowncast').attributeToElement({
            model: 'terminHintHtml',
            view: (html, { writer }) => {
                const encoded = utf8ToBase64(html || '')
                return writer.createAttributeElement(
                    'span',
                    { class: 'termin', 'data-description': encoded },
                    { priority: 5 },
                )
            },
        })
    }

    createButton(locale) {
        const { t } = locale
        const view = new ButtonView(locale)
        const editor = this.editor

        view.set({
            label: t('ПОДСКАЗКА'),
            withText: true,
            tooltip: false,
            class: 'ck-hint-btn',
        })

        const updateState = () => {
            const sel = editor.model.document.selection
            const hasTermin = sel.hasAttribute('terminId')
            const hasHint = sel.hasAttribute('terminHintHtml')
            view.isOn = hasHint
            const canUse =
                !this.config.get('isReadOnly') &&
                !hasTermin &&
                (hasHint || !sel.isCollapsed)
            view.set('isEnabled', canUse)
        }

        editor.model.document.selection.on('change:range', updateState)
        editor.model.document.selection.on('change:attribute', updateState)
        editor.model.document.on('change:data', updateState)

        view.on('execute', () => {
            this.openModal()
        })

        updateState()

        return view
    }

    openModal() {
        const selection = this.model.document.selection

        if (selection.hasAttribute('terminId')) {
            return
        }

        let hintHtml = ''

        if (selection.isCollapsed && selection.hasAttribute('terminHintHtml')) {
            hintHtml = selection.getAttribute('terminHintHtml') || ''

            this.model.change((writer) => {
                const pos = selection.getFirstPosition()
                const parent = pos.parent
                const hintVal = selection.getAttribute('terminHintHtml')
                let start = pos.offset
                let end = pos.offset
                let offset = 0

                for (const child of parent.getChildren()) {
                    const childEnd = offset + child.offsetSize
                    if (child.is('$text') && child.getAttribute('terminHintHtml') === hintVal) {
                        start = Math.min(start, offset)
                        end = Math.max(end, childEnd)
                    }
                    offset = childEnd
                }

                writer.setSelection(
                    writer.createRange(
                        writer.createPositionAt(parent, start),
                        writer.createPositionAt(parent, end),
                    ),
                )
            })
        } else if (!selection.isCollapsed && selection.hasAttribute('terminHintHtml')) {
            hintHtml = selection.getAttribute('terminHintHtml') || ''
        }

        const activeSelection = this.model.document.selection
        const selectedText = Array.from(activeSelection.getFirstRange().getItems())
            .filter((item) => item.is('$text') || item.is('$textProxy'))
            .map((item) => item.data)
            .join('')

        this._lastHintRange = activeSelection.getFirstRange()

        Nova.$emit(`ckeditor:hint:${this.attribute}:open`, {
            selectedText,
            hintHtml,
        })
    }

    applyHint({ text, descriptionHtml }) {
        this.model.change((writer) => {
            if (this._lastHintRange) {
                writer.setSelection(this._lastHintRange)
            }
            this._lastHintRange = null

            const selection = this.model.document.selection
            const range = selection.getFirstRange()

            if (selection.hasAttribute('terminId')) {
                writer.removeAttribute('terminId', range)
            }

            if (!range.isCollapsed) {
                writer.remove(range)
            }

            const textNode = writer.createText(text, {
                terminHintHtml: descriptionHtml || '',
            })
            this.model.insertContent(textNode)
        })
    }
}
