import ButtonView from '@ckeditor/ckeditor5-ui/src/button/buttonview'

export default class OutlineHeading {
    constructor(editor) {
        this.editor = editor
    }

    static get pluginName() {
        return 'outlineHeading'
    }

    static get requires() {
        return []
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
                const block = editor.model.document.selection.getFirstPosition()?.parent
                view.isOn = block?.is('element') && block.name === 'outlineHeading'
            }

            editor.model.document.selection.on('change:range', updateState)
            editor.model.document.on('change:data', updateState)

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
    }
}
