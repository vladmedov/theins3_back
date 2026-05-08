<template>
  <div class="accordion-editor">
    <textarea ref="editor" class="accordion-editor-textarea" :value="modelValue"></textarea>
  </div>
</template>

<script>
/**
 * Wraps a single CKEditor instance bound to the parent's v-model. Reuses the bundled
 * CKEditor build exposed on `window.NovaCKEditor` by `nova-components/nova-ckeditor`'s
 * `field.js` so we don't duplicate the heavy CKEditor bundle in this package.
 *
 * The editor configuration mirrors the relevant subset of `nova-ckeditor`'s
 * `editor-field.vue::createCkEditor()` — only the bits we actually need for the
 * `toolbar-theins-medium` toolbar (no media browser, no snippet picker, no termin/hint
 * pickers since those rely on global Vue plumbing in the parent ckeditor field).
 */
export default {
  name: 'AccordionEditor',
  props: {
    modelValue: { type: String, default: '' },
    toolbarConfig: { type: Object, required: true },
    uiLanguage: { type: String, default: 'en' },
  },
  emits: ['update:modelValue'],
  data() {
    return {
      editorInstance: null,
      pendingValue: null,
      destroyed: false,
    }
  },
  mounted() {
    this.createEditor()
  },
  beforeUnmount() {
    this.destroyEditor()
  },
  watch: {
    modelValue(newValue) {
      const editor = this.editorInstance
      if (!editor) {
        return
      }
      if (typeof newValue !== 'string') {
        return
      }
      const current = editor.getData()
      if (this.normalizeHtml(current) === this.normalizeHtml(newValue)) {
        return
      }
      // Avoid emitting another update for our own programmatic set.
      this.suppressEmit = true
      editor.setData(newValue || '')
      this.$nextTick(() => { this.suppressEmit = false })
    },
  },
  methods: {
    createEditor() {
      const Builder = typeof window !== 'undefined' ? window.NovaCKEditor : null
      if (!Builder || typeof Builder.create !== 'function') {
        console.error('NovaCKEditor build is not available; ensure nova-ckeditor is loaded before accordion-items.')
        return
      }

      const cfg = this.toolbarConfig || {}
      const config = {
        toolbar: {
          items: cfg.items || [],
          shouldNotGroupWhenFull: !!cfg.shouldNotGroupWhenFull,
        },
        heading: {
          options: (cfg.options && cfg.options.headings) ? cfg.options.headings : undefined,
        },
        htmlSupport: this.normalizeHtmlSupport(cfg.htmlSupport),
        language: {
          ui: this.uiLanguage || 'en',
          content: cfg.contentLanguage || 'en',
          textPartLanguage: cfg.textPartLanguage || [],
        },
        ...(cfg.options || {}),
      }
      // `headings` lives under heading.options above; remove the duplicated key from generic options.
      if (config.headings) {
        delete config.headings
      }

      Builder.create(this.$refs.editor, config)
        .then((editor) => {
          if (this.destroyed) {
            editor.destroy().catch(() => {})
            return
          }
          this.editorInstance = editor

          if (cfg.height && cfg.height > 1) {
            editor.editing.view.change((writer) => {
              writer.setStyle('height', `${cfg.height}px`, editor.editing.view.document.getRoot())
            })
          }

          editor.model.document.on('change:data', () => {
            if (this.suppressEmit) return
            const data = editor.getData()
            this.$emit('update:modelValue', data)
          })

          if (this.pendingValue !== null) {
            editor.setData(this.pendingValue)
            this.pendingValue = null
          }
        })
        .catch((e) => {
          console.error('Failed to create accordion CKEditor instance:', e)
        })
    },

    destroyEditor() {
      this.destroyed = true
      if (this.editorInstance) {
        this.editorInstance.destroy().catch(() => {})
        this.editorInstance = null
      }
    },

    normalizeHtml(html) {
      if (typeof html !== 'string') return ''
      return html.replace(/\s+/g, ' ').trim()
    },

    normalizeHtmlSupport(htmlSupport) {
      const result = { allow: [], disallow: [] }
      if (!htmlSupport) return result
      const stringToRegex = (value) => {
        if (typeof value === 'string' && value.startsWith('/') && value.endsWith('/')) {
          return new RegExp(value.slice(1, -1))
        }
        return value
      }
      const normalizeList = (list) => {
        if (!Array.isArray(list)) return []
        return list.map((item) => {
          if (item && item.name) {
            item.name = stringToRegex(item.name)
          }
          return item
        })
      }
      result.allow = normalizeList(htmlSupport.allow)
      result.disallow = normalizeList(htmlSupport.disallow)
      return result
    },
  },
}
</script>

<style scoped>
.accordion-editor {
  width: 100%;
}
.accordion-editor-textarea {
  display: none;
}
.accordion-editor :deep(.ck-editor__editable_inline) {
  min-height: 120px;
}
</style>
