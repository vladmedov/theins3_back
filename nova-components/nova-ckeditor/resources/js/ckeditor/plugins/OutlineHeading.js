import ButtonView from '@ckeditor/ckeditor5-ui/src/button/buttonview'

export default class OutlineHeading {
    constructor(editor) {
        this.editor = editor
    }

    static get pluginName() {
        return 'outlineHeading'
    }

    static get requires() {
        return ['Enter']
    }

    afterInit() {
        const editor = this.editor
        const schema = editor.model.schema
        const conversion = editor.conversion

        // Register the outlineHeading element in the schema (block, like paragraph/heading)
        if (!schema.isRegistered('outlineHeading')) {
            schema.register('outlineHeading', {
                inheritAllFrom: '$block',
            })
        }

        // Upcast: <h3 class="outline-heading"> → outlineHeading model element
        conversion.for('upcast').elementToElement({
            model: 'outlineHeading',
            view: { name: 'h3', classes: ['outline-heading'] },
            converterPriority: 'high',
        })

        // Downcast (editing + data): outlineHeading → <h3 class="outline-heading">
        conversion.for('downcast').elementToElement({
            model: 'outlineHeading',
            view: (modelElement, { writer }) => writer.createContainerElement('h3', { class: 'outline-heading' }),
        })

        // Toolbar button
        editor.ui.componentFactory.add('outlineHeading', locale => {
            const view = new ButtonView(locale)

            view.set({
                label: locale.t('ОГЛАВЛЕНИЕ'),
                withText: true,
                tooltip: false,
                class: 'ck-outline-heading-btn',
            })

            const updateState = () => {
                const sel = editor.model.document.selection
                const block = sel.getFirstPosition()?.parent
                const isOutlineBlock = block?.is('element') && block.name === 'outlineHeading'
                view.isOn = isOutlineBlock

                const hasTermin = sel.hasAttribute('terminId')
                const hasHint = sel.hasAttribute('terminHintHtml')
                const canUse =
                    !editor.config.get('isReadOnly') &&
                    !hasHint &&
                    !hasTermin &&
                    (isOutlineBlock || !sel.isCollapsed)
                view.set('isEnabled', canUse)
            }

            editor.model.document.selection.on('change:range', updateState)
            editor.model.document.selection.on('change:attribute', updateState)
            editor.model.document.on('change:data', updateState)

            updateState()

            view.on('execute', () => {
                editor.model.change(writer => {
                    const selection = editor.model.document.selection
                    const block = selection.getFirstPosition()?.parent
                    if (!block) return

                    const isActive = block.is('element') && block.name === 'outlineHeading'
                    const newName = isActive ? 'paragraph' : 'outlineHeading'

                    writer.rename(block, newName)
                })
            })

            return view
        })

        // Same as HeadingEditing: after Enter, the split creates a second block of the same type.
        // outlineHeading is not in heading.options, so the empty block was not renamed to paragraph.
        const enterCommand = editor.commands.get('enter')
        if (enterCommand) {
            enterCommand.on('afterExecute', (evt, data) => {
                const parent = editor.model.document.selection.getFirstPosition()?.parent
                if (parent && parent.is('element', 'outlineHeading') && parent.childCount === 0) {
                    data.writer.rename(parent, 'paragraph')
                }
            })
        }
    }
}
