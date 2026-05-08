<template>
  <PanelItem :field="field">
    <template #value>
      <div v-if="parsedValue.length" class="accordion-detail-list">
        <details
          v-for="(item, index) in parsedValue"
          :key="index"
          class="accordion-detail-item"
        >
          <summary class="accordion-detail-title">
            {{ item.title || `#${index + 1}` }}
          </summary>
          <div class="accordion-detail-body" v-html="item.content || ''" />
        </details>
      </div>
      <p v-else class="accordion-detail-empty">—</p>
    </template>
  </PanelItem>
</template>

<script>
export default {
  props: ['resource', 'resourceName', 'resourceId', 'field'],

  computed: {
    parsedValue() {
      const value = this.field.value
      if (!value) return []
      if (Array.isArray(value)) return value
      try {
        const parsed = JSON.parse(value)
        return Array.isArray(parsed) ? parsed : []
      } catch (_) {
        return []
      }
    },
  },
}
</script>

<style scoped>
.accordion-detail-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
  margin-top: 6px;
}

.accordion-detail-item {
  border: 1px solid #e5e7eb;
  border-radius: 6px;
  background: #fff;
  padding: 8px 12px;
}

.accordion-detail-title {
  cursor: pointer;
  font-weight: 600;
  font-size: 14px;
  list-style: none;
}

.accordion-detail-title::-webkit-details-marker {
  display: none;
}

.accordion-detail-body {
  margin-top: 8px;
  font-size: 14px;
  line-height: 1.5;
}

.accordion-detail-empty {
  color: #777;
  font-size: 14px;
  font-style: italic;
}
</style>
