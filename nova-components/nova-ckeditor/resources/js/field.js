import EditorIndex from './fields/editor-index'
import EditorDetail from './fields/editor-detail'
import EditorField from './fields/editor-field'
import CkEditor from './ckeditor/ckeditor'

// Expose the bundled CKEditor build so other Nova field packages (e.g. AccordionItems)
// can instantiate editor instances without bundling their own copy of CKEditor.
if (typeof window !== 'undefined') {
    window.NovaCKEditor = CkEditor
}

Nova.booting((Vue) => {
    Vue.component('index-ckeditor', EditorIndex)
    Vue.component('detail-ckeditor', EditorDetail)
    Vue.component('form-ckeditor', EditorField)
})
