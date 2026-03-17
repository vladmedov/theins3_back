import IndexField from './components/IndexField'
import DetailField from './components/DetailField'
import FormField from './components/FormField'

Nova.booting((app, store) => {
  app.component('index-insertion-code', IndexField)
  app.component('detail-insertion-code', DetailField)
  app.component('form-insertion-code', FormField)
})
