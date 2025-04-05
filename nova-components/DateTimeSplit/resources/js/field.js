import IndexField from './components/IndexField'
import DetailField from './components/DetailField'
import FormField from './components/FormField'
import PreviewField from './components/PreviewField'

Nova.booting((app, store) => {
  app.component('index-date-time-split', IndexField)
  app.component('detail-date-time-split', DetailField)
  app.component('form-date-time-split', FormField)
  // app.component('preview-date-time-split', PreviewField)
})
