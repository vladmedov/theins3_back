<template>
  <div v-if="field.visible" class="space-y-2 md:flex md:flex-col md:space-y-2 py-5">
    <textarea
      :name="field.attribute + '__state'"
      :value="stateJson"
      class="regions-map-state-field"
      aria-hidden="true"
      tabindex="-1"
    ></textarea>

    <div ref="topBar" class="regions-map-top-bar px-6 md:px-8">
      <button type="button" class="regions-map-json-btn" @click="openJsonEditor">
        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <polyline points="16 18 22 12 16 6"></polyline>
          <polyline points="8 6 2 12 8 18"></polyline>
        </svg>
        <span>JSON</span>
      </button>
    </div>

    <div class="w-full px-6 md:px-8">
      <div class="regions-map-editor">
        <div class="regions-map-schema">
          <form-label class="mb-2">{{ t.schemaLabel }}</form-label>
          <SelectControl
            v-model="state.schema"
            :options="schemaSelectOptions"
            class="w-full"
            size="sm"
            :disabled="schemaLocked"
            @update:model-value="onSchemaChange"
          />
        </div>

        <div class="regions-map-title">
          <form-label class="mb-2">{{ t.titleLabel }}</form-label>
          <input
            type="text"
            v-model="state.title"
            :maxlength="limits.title"
            :placeholder="t.titlePlaceholder"
            class="w-full form-control form-control-bordered form-input"
            @input="onChange"
          />
        </div>

        <div class="regions-map-regions">
          <form-label class="mb-2">{{ t.regionsTable }}</form-label>
          <div class="regions-map-filter">
            <input
              type="search"
              v-model="filter"
              :placeholder="t.filterPlaceholder"
            />
          </div>
          <div class="regions-map-table-wrap">
            <table class="regions-map-table">
              <thead>
                <tr>
                  <th>{{ t.colRegion }}</th>
                  <th>{{ t.colColor }}</th>
                  <th>{{ t.colComment }}</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="region in filteredRegions" :key="region.id">
                  <td>
                    <div>{{ regionLabel(region.id) }}</div>
                    <div class="regions-map-id">{{ region.id }}</div>
                  </td>
                  <td>
                    <SelectControl
                      v-model="region.color"
                      :options="colorSelectOptions"
                      class="w-full"
                      size="sm"
                      @update:model-value="onRegionColorChange(region)"
                    />
                  </td>
                  <td>
                    <input
                      type="text"
                      v-model="region.comment"
                      :maxlength="limits.regionComment"
                      :placeholder="t.regionCommentPlaceholder"
                      class="w-full form-control form-control-bordered form-input h-8 text-xs"
                      @input="onChange"
                    />
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <div v-if="activeLegendColors.length > 0">
          <form-label class="mb-2">{{ t.colorLabels }}</form-label>
          <div class="regions-map-color-labels">
            <div
              v-for="color in activeLegendColors"
              :key="color"
              class="regions-map-color-row"
            >
              <span
                class="regions-map-color-swatch"
                :style="{ backgroundColor: colorHexMap[color] || '#ccc' }"
                :title="color"
              ></span>
              <label class="regions-map-color-input">
                <span class="sr-only">{{ color }}</span>
                <input
                  type="text"
                  v-model="state.color_labels[color]"
                  :placeholder="t.colorLabelPlaceholder(color)"
                  :maxlength="limits.colorLabel"
                  @input="onChange"
                />
              </label>
            </div>
          </div>
        </div>

        <div class="regions-map-comment">
          <form-label class="mb-2">{{ t.blockComment }}</form-label>
          <textarea
            v-model="commentText"
            rows="3"
            :placeholder="t.blockCommentPlaceholder"
            @input="onChange"
          ></textarea>
        </div>
      </div>

      <p v-if="hasError" class="help-text-error mt-2 text-sm text-red-500">
        {{ firstError }}
      </p>
    </div>

    <div v-if="jsonModal.open" class="regions-map-json-modal">
        <div class="regions-map-json-modal__backdrop" @click="closeJsonEditor"></div>
        <div class="regions-map-json-modal__panel" role="dialog" aria-modal="true">
          <div class="regions-map-json-modal__head">
            <div>
              <h3 class="regions-map-json-modal__title">{{ t.jsonModalTitle }}</h3>
              <p class="regions-map-json-modal__hint">{{ t.jsonModalHint }}</p>
            </div>
            <button type="button" class="regions-map-json-modal__close" @click="closeJsonEditor" :title="t.jsonCancel">
              ×
            </button>
          </div>
          <textarea
            ref="jsonTextarea"
            v-model="jsonModal.text"
            class="regions-map-json-modal__textarea"
            spellcheck="false"
          ></textarea>
          <p v-if="jsonModal.error" class="regions-map-json-modal__error">{{ jsonModal.error }}</p>
          <div class="regions-map-json-modal__actions">
            <button type="button" class="regions-map-json-btn regions-map-json-btn--primary" @click="applyJsonEditor">
              {{ t.jsonApply }}
            </button>
            <button type="button" class="regions-map-json-btn" @click="selectAllJson">
              {{ t.jsonSelectAll }}
            </button>
            <button type="button" class="regions-map-json-btn" @click="closeJsonEditor">
              {{ t.jsonCancel }}
            </button>
          </div>
        </div>
      </div>
  </div>
</template>

<script>
import { FormField, HandlesValidationErrors } from 'laravel-nova'
import debounce from 'lodash/debounce'

const COLOR_NAMES_RU = {
  gray: 'Серый',
  yellow: 'Жёлтый',
  red: 'Красный',
  green: 'Зелёный',
  black: 'Чёрный',
  blue: 'Синий',
  purple: 'Фиолетовый',
}

const COLOR_NAMES_EN = {
  gray: 'Gray',
  yellow: 'Yellow',
  red: 'Red',
  green: 'Green',
  black: 'Black',
  blue: 'Blue',
  purple: 'Purple',
}

export default {
  mixins: [FormField, HandlesValidationErrors],
  props: ['resourceName', 'resourceId', 'field'],
  data() {
    return {
      state: this.emptyState(),
      commentText: '',
      filter: '',
      autosavePaused: true,
      jsonModal: {
        open: false,
        text: '',
        error: '',
      },
      schemaLocked: false,
    }
  },
  computed: {
    colors() {
      return this.field.colors || []
    },
    colorHexMap() {
      return this.field.colorHexMap || {}
    },
    schemaRegions() {
      return this.field.schemaRegions || {}
    },
    regionLabels() {
      const schema = this.state?.schema || this.formDefaultSchema
      const data = this.schemaRegions[schema]
      if (data?.labels) {
        return data.labels
      }

      return this.field.regionLabels || {}
    },
    regionIds() {
      const schema = this.state?.schema || this.formDefaultSchema
      const data = this.schemaRegions[schema]
      if (data?.ids) {
        return data.ids
      }

      return this.field.regionIds || []
    },
    formDefaultSchema() {
      return this.field?.defaultSchema || this.field?.defaults?.schema || ''
    },
    schemaSelectOptions() {
      const schemas = this.field.schemas || []
      if (schemas.length > 0) {
        return schemas.map((item) => ({
          value: item.value,
          label: item.label,
        }))
      }

      return [{
        value: this.formDefaultSchema,
        label: this.formDefaultSchema,
      }]
    },
    limits() {
      return this.field.limits || {
        title: 500,
        comment: 500,
        commentLines: 10,
        colorLabel: 500,
        regionComment: 500,
      }
    },
    isRussian() {
      return (this.field.uiLanguage || 'en') === 'ru'
    },
    t() {
      if (this.isRussian) {
        return {
          jsonModalTitle: 'Данные блока (JSON)',
          jsonModalHint: 'Отредактируйте JSON, скопируйте через Ctrl+C / Cmd+C или вставьте из буфера через Ctrl+V / Cmd+V, затем нажмите «Применить».',
          jsonApply: 'Применить',
          jsonSelectAll: 'Выделить всё',
          jsonCancel: 'Отмена',
          titleLabel: 'Заголовок',
          titlePlaceholder: 'Заголовок карты',
          schemaLabel: 'Схема карты',
          blockComment: 'Подпись под легендой',
          blockCommentPlaceholder: 'Дата актуальности, источник и т.п. (каждая строка — отдельный абзац)',
          colorLabels: 'Легенда',
          colorLabelPlaceholder: (color) => `Статус для «${COLOR_NAMES_RU[color] || color}»`,
          regionsTable: 'Регионы',
          filterPlaceholder: 'Поиск по названию или коду…',
          colRegion: 'Регион',
          colColor: 'Цвет',
          colComment: 'Комментарий',
          colorDefault: 'Серый (по умолчанию)',
          regionCommentPlaceholder: 'Комментарий к региону',
          jsonApplyConfirm: 'Заменить текущие данные блока содержимым JSON?',
          jsonInvalid: 'JSON не прошёл проверку.',
          jsonEmpty: 'JSON не может быть пустым.',
          jsonSchemaLocked: 'Схему карты нельзя изменить после создания блока.',
        }
      }

      return {
        jsonModalTitle: 'Block data (JSON)',
        jsonModalHint: 'Edit JSON, copy with Ctrl+C / Cmd+C or paste with Ctrl+V / Cmd+V, then click Apply.',
        jsonApply: 'Apply',
        jsonSelectAll: 'Select all',
        jsonCancel: 'Cancel',
        titleLabel: 'Title',
        titlePlaceholder: 'Map title',
        schemaLabel: 'Map schema',
        blockComment: 'Caption below legend',
        blockCommentPlaceholder: 'Freshness date, source, etc. (each line is a separate paragraph)',
        colorLabels: 'Legend',
        colorLabelPlaceholder: (color) => `Status for “${COLOR_NAMES_EN[color] || color}”`,
        regionsTable: 'Regions',
        filterPlaceholder: 'Search by name or code…',
        colRegion: 'Region',
        colColor: 'Color',
        colComment: 'Comment',
        colorDefault: 'Gray (default)',
        regionCommentPlaceholder: 'Region comment',
        jsonApplyConfirm: 'Replace current block data with JSON contents?',
        jsonInvalid: 'JSON validation failed.',
        jsonEmpty: 'JSON must not be empty.',
        jsonSchemaLocked: 'The map schema cannot be changed after the block has been created.',
      }
    },
    filteredRegions() {
      const q = (this.filter || '').trim().toLowerCase()
      let list = this.state.regions

      if (q) {
        list = list.filter((region) => {
          const label = (this.regionLabels[region.id] || '').toLowerCase()
          return label.includes(q) || region.id.toLowerCase().includes(q)
        })
      }

      return [...list].sort((a, b) =>
        this.regionLabel(a.id).localeCompare(this.regionLabel(b.id), 'ru', { sensitivity: 'base' })
      )
    },
    colorSelectOptions() {
      return [
        { value: '', label: this.t.colorDefault },
        ...this.colors
          .filter((color) => color !== 'gray')
          .map((color) => ({
            value: color,
            label: this.colorLabelName(color),
          })),
      ]
    },
    activeLegendColors() {
      const used = new Set()

      this.state.regions.forEach((region) => {
        const color = !region.color || region.color === 'gray' ? 'gray' : region.color
        if (this.colors.includes(color)) {
          used.add(color)
        }
      })

      return this.colors.filter((color) => used.has(color))
    },
    stateJson() {
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
    field: {
      immediate: true,
      deep: true,
      handler() {
        this.setInitialValue()
      },
    },
  },
  methods: {
    emptyState() {
      const defaults = this.field?.defaults || {}
      return {
        schema: defaults.schema ?? this.formDefaultSchema,
        title: defaults.title ?? '',
        comment: defaults.comment ?? null,
        color_labels: { ...(defaults.color_labels || {}) },
        regions: Array.isArray(defaults.regions) ? defaults.regions.map((r) => ({ ...r })) : [],
      }
    },

    setInitialValue() {
      this.autosavePaused = true
      const raw = this.field?.value
      let parsed = this.emptyState()

      if (raw && typeof raw === 'object' && !Array.isArray(raw)) {
        parsed = this.mergeState(parsed, raw)
      } else if (typeof raw === 'string' && raw.length > 0) {
        try {
          const decoded = JSON.parse(raw)
          if (decoded && typeof decoded === 'object') {
            parsed = this.mergeState(parsed, decoded)
          }
        } catch (_) {
          // keep defaults
        }
      }

      this.state = parsed
      this.commentText = this.commentToText(parsed.comment)
      this.ensureAllRegions()
      this.syncSchemaLocked(raw)

      this.$nextTick(() => {
        this.autosavePaused = false
      })
    },

    syncSchemaLocked(raw) {
      if (this.field?.schemaLocked === true) {
        this.schemaLocked = true
        return
      }

      if (raw && typeof raw === 'object' && !Array.isArray(raw) && raw._schema_locked === true) {
        this.schemaLocked = true
        return
      }

      if (typeof raw === 'string' && raw.length > 0) {
        try {
          const decoded = JSON.parse(raw)
          if (decoded?._schema_locked === true) {
            this.schemaLocked = true
          }
        } catch (_) {
          // ignore
        }
      }
    },

    mergeState(base, incoming) {
      const next = {
        schema: incoming.schema ?? base.schema ?? this.formDefaultSchema,
        title: incoming.title ?? base.title ?? '',
        comment: incoming.comment ?? base.comment ?? null,
        color_labels: { ...base.color_labels, ...(incoming.color_labels || {}) },
        regions: Array.isArray(incoming.regions) ? incoming.regions.map((r) => ({ ...r })) : base.regions,
      }

      this.colors.forEach((color) => {
        if (!(color in next.color_labels)) {
          next.color_labels[color] = ''
        }
      })

      return next
    },

    ensureAllRegions() {
      const byId = {}
      this.state.regions.forEach((region) => {
        if (region && region.id) byId[region.id] = region
      })

      this.state.regions = this.regionIds.map((id) => {
        const existing = byId[id] || { id }
        return {
          id,
          color: existing.color === 'gray' ? '' : (existing.color || ''),
          comment: existing.comment || '',
        }
      })
    },

    commentToText(comment) {
      if (comment == null || comment === '') return ''
      if (Array.isArray(comment)) return comment.join('\n')
      return String(comment)
    },

    textToComment(text) {
      const lines = String(text || '')
        .split('\n')
        .map((line) => line.trim())
        .filter((line) => line.length > 0)

      if (lines.length === 0) return null
      if (lines.length === 1) return lines[0]
      return lines
    },

    regionLabel(id) {
      return this.regionLabels[id] || id
    },

    colorLabelName(color) {
      const map = this.isRussian ? COLOR_NAMES_RU : COLOR_NAMES_EN
      return map[color] || color
    },

    onSchemaChange() {
      if (this.schemaLocked) {
        return
      }

      this.ensureAllRegions()
      this.onChange()
    },

    onRegionColorChange(region) {
      if (!region.color) region.color = ''
      this.onChange()
    },

    onChange() {
      this.state.comment = this.textToComment(this.commentText)
      if (this.scheduleAutosave) this.scheduleAutosave()
    },

    serializableValue() {
      const colorLabels = {}
      this.colors.forEach((color) => {
        colorLabels[color] = this.state.color_labels[color] ?? ''
      })

      const regions = this.state.regions.map((region) => {
        const entry = { id: region.id }
        if (region.color && region.color !== 'gray') {
          entry.color = region.color
        }
        if (region.comment && String(region.comment).trim() !== '') {
          entry.comment = String(region.comment).trim()
        }
        return entry
      })

      const payload = {
        schema: this.state.schema || this.formDefaultSchema,
        color_labels: colorLabels,
        regions,
      }

      const title = String(this.state.title || '').trim()
      if (title !== '') {
        payload.title = title
      }

      const comment = this.textToComment(this.commentText)
      if (comment !== null) payload.comment = comment

      return payload
    },

    runAutosave() {
      if (this.autosavePaused) return
      if (typeof this.emitFieldValueChange === 'function') {
        this.emitFieldValueChange(this.fieldAttribute, this.serializableValue())
      }
      document.dispatchEvent(new CustomEvent('nova-autosave:change', {
        detail: { attribute: this.field.attribute, source: 'regions-map-editor' },
      }))
    },

    fill(formData) {
      formData.append(this.fieldAttribute, JSON.stringify(this.serializableValue()))
    },

    openJsonEditor() {
      this.jsonModal = {
        open: true,
        text: this.buildExportJson(),
        error: '',
      }

      this.$nextTick(() => {
        this.selectAllJson()
      })
    },

    closeJsonEditor() {
      this.jsonModal.open = false
      this.jsonModal.error = ''
    },

    selectAllJson() {
      const el = this.$refs.jsonTextarea
      if (!el) return

      el.focus()
      el.select()
      if (typeof el.setSelectionRange === 'function') {
        el.setSelectionRange(0, el.value.length)
      }
    },

    applyJsonEditor() {
      const text = this.jsonModal.text
      if (!text?.trim()) {
        this.jsonModal.error = this.t.jsonEmpty
        return
      }

      if (!this.applyImportedJson(text, (error) => {
        this.jsonModal.error = error
      })) {
        return
      }

      this.closeJsonEditor()
    },

    buildExportJson() {
      return JSON.stringify(
        {
          title: this.state.title || '',
          ...this.serializableValue(),
        },
        null,
        2
      )
    },

    applyImportedJson(text, onError = null) {
      let data
      try {
        data = JSON.parse(text)
      } catch (_) {
        const message = this.t.jsonInvalid
        if (onError) onError(message)
        else window.alert(message)
        return false
      }

      const errors = this.validateClipboard(data)
      if (errors.length > 0) {
        const message = `${this.t.jsonInvalid}\n\n${errors.join('\n')}`
        if (onError) onError(message)
        else window.alert(message)
        return false
      }

      if (!window.confirm(this.t.jsonApplyConfirm)) {
        return false
      }

      this.state = this.mergeState(this.emptyState(), {
        schema: this.schemaLocked
          ? this.state.schema
          : (data.schema ?? this.formDefaultSchema),
        title: data.title ?? '',
        comment: data.comment ?? null,
        color_labels: data.color_labels,
        regions: data.regions,
      })
      this.commentText = this.commentToText(this.state.comment)
      this.ensureAllRegions()
      this.onChange()

      return true
    },

    validateClipboard(data) {
      const errors = []
      if (!data || typeof data !== 'object') {
        errors.push('Invalid JSON object')
        return errors
      }

      if (data.schema != null && typeof data.schema !== 'string') {
        errors.push('schema must be a string')
      }
      if (
        this.schemaLocked
        && data.schema
        && data.schema !== this.state.schema
      ) {
        errors.push(this.t.jsonSchemaLocked)
      }
      const allowedSchemas = (this.field.schemas || []).map((item) => item.value)
      if (data.schema && allowedSchemas.length > 0 && !allowedSchemas.includes(data.schema)) {
        errors.push(`schema must be one of: ${allowedSchemas.join(', ')}`)
      }

      if (data.title != null && typeof data.title !== 'string') {
        errors.push('title must be a string')
      }
      if (data.title && data.title.length > this.limits.title) {
        errors.push(`title exceeds ${this.limits.title} characters`)
      }

      if (data.comment != null && typeof data.comment !== 'string' && !Array.isArray(data.comment)) {
        errors.push('comment must be a string or array of strings')
      }

      if (!data.color_labels || typeof data.color_labels !== 'object') {
        errors.push('color_labels is required')
      } else {
        this.colors.forEach((color) => {
          if (!(color in data.color_labels)) {
            errors.push(`color_labels.${color} is required`)
          }
        })
      }

      if (!Array.isArray(data.regions)) {
        errors.push('regions must be an array')
      } else if (data.regions.length !== this.regionIds.length) {
        errors.push(`regions must contain exactly ${this.regionIds.length} items`)
      } else {
        const seen = new Set()
        data.regions.forEach((region, index) => {
          if (!region || typeof region !== 'object') {
            errors.push(`regions[${index}] must be an object`)
            return
          }
          if (!this.regionIds.includes(region.id)) {
            errors.push(`regions[${index}].id is invalid`)
          }
          if (seen.has(region.id)) {
            errors.push(`duplicate id ${region.id}`)
          }
          seen.add(region.id)
          if (region.color && !this.colors.includes(region.color)) {
            errors.push(`regions[${index}].color is invalid`)
          }
        })

        this.regionIds.forEach((id) => {
          if (!seen.has(id)) {
            errors.push(`missing region id ${id}`)
          }
        })
      }

      return errors
    },
  },
}
</script>

<style scoped>
.sr-only {
  position: absolute;
  width: 1px;
  height: 1px;
  padding: 0;
  margin: -1px;
  overflow: hidden;
  clip: rect(0, 0, 0, 0);
  white-space: nowrap;
  border: 0;
}
</style>
