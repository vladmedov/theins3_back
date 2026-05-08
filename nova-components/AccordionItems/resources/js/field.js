import IndexField from './components/IndexField'
import DetailField from './components/DetailField'
import FormField from './components/FormField'

Nova.booting((app, store) => {
  app.component('index-accordion-items', IndexField)
  app.component('detail-accordion-items', DetailField)
  app.component('form-accordion-items', FormField)
})
