import IndexField from './components/IndexField'
import DetailField from './components/DetailField'
import FormField from './components/FormField'
import PreviewField from './components/PreviewField'

Nova.booting((app, store) => {
  app.component('index-image-gallery', IndexField)
  app.component('detail-image-gallery', DetailField)
  app.component('form-image-gallery', FormField)
  // app.component('preview-image-gallery', PreviewField)
})
