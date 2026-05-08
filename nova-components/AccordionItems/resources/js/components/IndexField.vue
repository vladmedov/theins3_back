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
        if (Array.isArray(parsed)) return `${parsed.length} item(s)`
        return ''
      } catch (_) {
        return ''
      }
    },
  },
}
</script>
