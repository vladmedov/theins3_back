import IndexField from './components/IndexField'
import DetailField from './components/DetailField'
import FormField from './components/FormField'

Nova.booting((app, store) => {
  app.component('index-regions-map-editor', IndexField)
  app.component('detail-regions-map-editor', DetailField)
  app.component('form-regions-map-editor', FormField)
})
