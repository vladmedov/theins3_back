<template>
  <PanelItem :field="field">
    <template #value>
      <div v-if="parsedValue.length" class="image-gallery-list">
        <div v-for="(image, index) in parsedValue" :key="index" class="image-gallery-item">
          <!-- Изображение слева -->
          <div class="image-gallery-container">
            <img :src="image.link" class="image-gallery-preview" />
          </div>

          <!-- Данные справа -->
          <div class="image-gallery-details">
            <p v-if="image.author"><strong>Автор:</strong> {{ image.author }}</p>
            <p v-if="image.description"><strong>Описание:</strong> {{ image.description }}</p>
          </div>
        </div>
      </div>
      <p v-else class="no-images">Нет изображений</p>
    </template>
  </PanelItem>
</template>

<script>
export default {
  props: ["resource", "resourceName", "resourceId", "field"],

  computed: {
    parsedValue() {
      try {
        return JSON.parse(this.field.value) || [];
      } catch (error) {
        console.error("Ошибка парсинга JSON в DetailField:", error);
        return [];
      }
    }
  }
};
</script>

<style scoped>
/* Список изображений */
.image-gallery-list {
  display: flex;
  flex-direction: column;
  gap: 10px;
  margin-top: 10px;
}

/* Табличный стиль */
.image-gallery-item {
  display: flex;
  align-items: flex-start;
  border: 1px solid #ccc;
  padding: 10px;
  border-radius: 6px;
  gap: 15px;
  background: white;
}

/* Контейнер для изображения */
.image-gallery-container {
  width: 150px;
  display: flex;
  align-items: flex-start;
  justify-content: center;
}

/* Само изображение */
.image-gallery-preview {
  width: 100%;
  max-width: 150px;
  border-radius: 4px;
  border: 1px solid #ccc;
}

/* Блок данных */
.image-gallery-details {
  display: flex;
  flex-direction: column;
  flex: 1;
  font-size: 14px;
}

.no-images {
  color: #777;
  font-size: 14px;
  font-style: italic;
}
</style>
