<template>
  <PanelItem :field="field">
    <template #value>
      <p v-if="summary">{{ summary }}</p>
      <p v-else>—</p>
    </template>
  </PanelItem>
</template>

<script>
export default {
  props: ['resource', 'resourceName', 'resourceId', 'field'],

  computed: {
    summary() {
      const value = this.field.value
      if (!value) return ''
      try {
        const parsed = typeof value === 'string' ? JSON.parse(value) : value
        const count = Array.isArray(parsed?.regions) ? parsed.regions.length : 0
        return count ? `${count} regions configured` : ''
      } catch (_) {
        return ''
      }
    },
  },
}
</script>
