<template>
  <DefaultField
    :field="field"
    :errors="errors"
    :show-help-text="showHelpText"
    :full-width-content="fullWidthContent"
  >
    <template #field>
      <textarea
        :name="field.attribute + '__accordion_state'"
        :value="accordionStateJson"
        class="accordion-items-state-field"
        aria-hidden="true"
        tabindex="-1"
      ></textarea>

      <div class="accordion-items-list">
        <div
          v-for="(item, index) in value"
          :key="item._uid"
          class="accordion-items-row"
          :class="{ 'accordion-items-row--dragging': draggedIndex === index }"
          @dragover.prevent
          @drop="drop(index)"
        >
          <div
            class="accordion-items-handle"
            draggable="true"
            @dragstart="dragStart(index, $event)"
            @dragend="dragEnd"
            :title="dragHint"
          >
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <line x1="7" y1="5" x2="17" y2="5"></line>
              <line x1="7" y1="9" x2="17" y2="9"></line>
              <line x1="7" y1="13" x2="17" y2="13"></line>
              <line x1="7" y1="17" x2="17" y2="17"></line>
            </svg>
          </div>

          <div class="accordion-items-body">
            <div class="accordion-items-row-head">
              <span class="accordion-items-index">#{{ index + 1 }}</span>
              <input
                type="text"
                v-model="item.title"
                :placeholder="titlePlaceholder"
                class="accordion-items-title-input"
              />
              <button
                type="button"
                class="accordion-items-remove"
                @click="removeItem(index)"
                :title="removeHint"
              >
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M3 6h18"></path>
                  <path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"></path>
                  <path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"></path>
                </svg>
              </button>
            </div>

            <AccordionEditor
              :model-value="item.content"
              :toolbar-config="toolbarConfig"
              :ui-language="uiLanguage"
              @update:model-value="onItemContentChange(index, $event)"
            />
          </div>
        </div>
      </div>

      <div class="accordion-items-add">
        <button type="button" class="accordion-items-add-btn" @click="addItem">
          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <line x1="12" y1="5" x2="12" y2="19"></line>
            <line x1="5" y1="12" x2="19" y2="12"></line>
          </svg>
          <span>{{ addHint }}</span>
        </button>
      </div>
    </template>
  </DefaultField>
</template>

<script>
import { FormField, HandlesValidationErrors } from 'laravel-nova'
import debounce from 'lodash/debounce'
import AccordionEditor from './AccordionEditor.vue'

let uidCounter = 0
const nextUid = () => `ai-${Date.now()}-${++uidCounter}`

export default {
  mixins: [FormField, HandlesValidationErrors],
  components: { AccordionEditor },
  props: ['resourceName', 'resourceId', 'field'],
  data() {
    return {
      value: [],
      draggedIndex: null,
      autosavePaused: true,
    }
  },
  computed: {
    toolbarConfig() {
      return this.field.toolbarConfig || { items: [], options: {}, htmlSupport: {} }
    },
    uiLanguage() {
      return this.field.uiLanguage || 'en'
    },
    isRussian() {
      return this.uiLanguage === 'ru'
    },
    titlePlaceholder() {
      return this.isRussian ? 'Заголовок пункта' : 'Item title'
    },
    addHint() {
      return this.isRussian ? 'Добавить пункт' : 'Add item'
    },
    removeHint() {
      return this.isRussian ? 'Удалить пункт' : 'Remove item'
    },
    dragHint() {
      return this.isRussian ? 'Перетащить' : 'Drag to reorder'
    },
    accordionStateJson() {
      return JSON.stringify(this.serializableValue())
    },
  },
  created() {
    this.scheduleAutosave = debounce(this.runAutosave.bind(this), 400)
  },
  mounted() {
    this.setInitialValue()
  },
  watch: {
    value: {
      deep: true,
      handler() {
        if (this.scheduleAutosave) this.scheduleAutosave()
      },
    },
    field: {
      immediate: true,
      deep: true,
      handler(newField) {
        if (newField && newField.value !== undefined) {
          this.setInitialValue()
        }
      },
    },
  },
  methods: {
    setInitialValue() {
      this.autosavePaused = true
      const raw = this.field ? this.field.value : null
      let parsed = []
      if (Array.isArray(raw)) {
        parsed = raw
      } else if (typeof raw === 'string' && raw.length > 0) {
        try {
          const decoded = JSON.parse(raw)
          if (Array.isArray(decoded)) parsed = decoded
        } catch (_) {
          parsed = []
        }
      } else if (raw && typeof raw === 'object') {
        // Server may send keyed object; coerce to array preserving order.
        parsed = Object.keys(raw).map((k) => raw[k]).filter((v) => v && typeof v === 'object')
      }

      this.value = parsed.map((item) => ({
        _uid: nextUid(),
        title: typeof item.title === 'string' ? item.title : '',
        content: typeof item.content === 'string' ? item.content : '',
      }))

      this.$nextTick(() => { this.autosavePaused = false })
    },

    addItem() {
      this.value.push({ _uid: nextUid(), title: '', content: '' })
    },

    removeItem(index) {
      this.value.splice(index, 1)
    },

    onItemContentChange(index, html) {
      const item = this.value[index]
      if (!item) return
      item.content = typeof html === 'string' ? html : ''
      if (this.scheduleAutosave) this.scheduleAutosave()
    },

    runAutosave() {
      if (this.autosavePaused) return
      if (typeof this.emitFieldValueChange === 'function') {
        this.emitFieldValueChange(this.fieldAttribute, this.serializableValue())
      }
      this.notifyAutosaveChange('accordion-items')
    },

    notifyAutosaveChange(source) {
      if (typeof document === 'undefined') return

      document.dispatchEvent(new CustomEvent('nova-autosave:change', {
        detail: { attribute: this.field.attribute, source: source || 'accordion-items' },
      }))
    },

    serializableValue() {
      return (this.value || []).map((item) => ({
        title: item.title || '',
        content: item.content || '',
      }))
    },

    fill(formData) {
      const items = this.serializableValue()
      if (items.length === 0) {
        // Empty list: still emit the attribute so it overwrites any previous value server-side.
        formData.append(this.field.attribute, '')
        return
      }
      items.forEach((item, index) => {
        formData.append(`${this.field.attribute}[${index}][title]`, item.title || '')
        formData.append(`${this.field.attribute}[${index}][content]`, item.content || '')
      })
    },

    dragStart(index, event) {
      this.draggedIndex = index
      const dt = event.dataTransfer
      if (dt) {
        dt.effectAllowed = 'move'
        dt.setData('text/plain', String(index))
      }
    },

    dragEnd() {
      this.draggedIndex = null
    },

    drop(index) {
      if (this.draggedIndex === null) return
      const moved = this.value.splice(this.draggedIndex, 1)[0]
      this.value.splice(index, 0, moved)
      this.draggedIndex = null
    },
  },
}
</script>

<style scoped>
.accordion-items-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
  margin-top: 4px;
}

.accordion-items-row {
  display: flex;
  align-items: stretch;
  border: 1px solid #d1d5db;
  border-radius: 6px;
  background: #fff;
  gap: 0;
}

.accordion-items-row--dragging {
  opacity: 0.4;
  outline: 2px dashed #1976d2;
  outline-offset: 2px;
}

.accordion-items-handle {
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: grab;
  padding: 0 10px;
  color: #6b7280;
  border-right: 1px solid #e5e7eb;
  user-select: none;
  background: #f9fafb;
  border-top-left-radius: 6px;
  border-bottom-left-radius: 6px;
}

.accordion-items-handle:active {
  cursor: grabbing;
}

.accordion-items-body {
  display: flex;
  flex-direction: column;
  flex: 1;
  padding: 10px 12px;
  gap: 10px;
  min-width: 0;
}

.accordion-items-row-head {
  display: flex;
  align-items: center;
  gap: 8px;
}

.accordion-items-index {
  font-weight: 600;
  color: #6b7280;
  font-size: 13px;
  min-width: 28px;
}

.accordion-items-title-input {
  flex: 1;
  padding: 8px 10px;
  border: 1px solid #d1d5db;
  border-radius: 4px;
  font-size: 14px;
}

.accordion-items-title-input:focus {
  outline: none;
  border-color: #1976d2;
}

.accordion-items-remove {
  width: 36px;
  height: 36px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  background: #e54839;
  color: #fff;
  border: none;
  border-radius: 4px;
  cursor: pointer;
  flex-shrink: 0;
  transition: background-color 0.15s ease;
}

.accordion-items-remove:hover {
  background: #c03d31;
}

.accordion-items-add {
  margin-top: 12px;
}

.accordion-items-add-btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 8px 14px;
  border: 1px dashed #1976d2;
  border-radius: 6px;
  background: #fff;
  color: #1976d2;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  transition: background-color 0.15s ease;
}

.accordion-items-add-btn:hover {
  background: rgba(25, 118, 210, 0.08);
}

.accordion-items-state-field {
  position: absolute;
  width: 1px;
  height: 1px;
  overflow: hidden;
  clip: rect(0, 0, 0, 0);
  white-space: nowrap;
  border: 0;
  padding: 0;
  margin: -1px;
}
</style>
