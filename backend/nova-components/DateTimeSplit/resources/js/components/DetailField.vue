<template>
  <PanelItem :index="index" :field="{ ...field, value: formattedDate }" />
</template>

<script>
export default {
  props: ['index', 'resource', 'resourceName', 'resourceId', 'field'],

  computed: {
    /**
     * Преобразует дату из формата ISO 8601 в d.m.Y H:i:S
     */
    formattedDate() {
      if (!this.field.value) return ''

      try {
        const date = new Date(this.field.value) // Преобразуем в объект Date

        // Форматируем в нужный вид
        const pad = (num) => String(num).padStart(2, '0')

        const day = pad(date.getDate())
        const month = pad(date.getMonth() + 1)
        const year = date.getFullYear()
        const hours = pad(date.getHours())
        const minutes = pad(date.getMinutes())
        const seconds = pad(date.getSeconds())

        return `${day}.${month}.${year} ${hours}:${minutes}:${seconds}`
      } catch (error) {
        console.error('Ошибка преобразования даты:', error)
        return this.field.value // На случай ошибки выводим как есть
      }
    }
  }
}
</script>
