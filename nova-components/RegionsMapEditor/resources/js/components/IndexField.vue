<template>
  <span>{{ summary }}</span>
</template>

<script>
export default {
  props: ['resourceName', 'field'],

  computed: {
    summary() {
      const value = this.field.value
      if (!value) return ''
      try {
        const parsed = typeof value === 'string' ? JSON.parse(value) : value
        const count = Array.isArray(parsed?.regions) ? parsed.regions.length : 0
        return count ? `${count} regions` : ''
      } catch (_) {
        return ''
      }
    },
  },
}
</script>
