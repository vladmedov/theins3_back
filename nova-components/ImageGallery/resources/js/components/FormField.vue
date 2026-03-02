<template>
  <DefaultField
    :field="field"
    :errors="errors"
    :show-help-text="showHelpText"
    :full-width-content="fullWidthContent"
  >
    <template #field>
      <!-- Упрощённая форма загрузки -->
      <div class="upload-box" @click="triggerFileInput">
        <span>Выбрать файлы</span>
      </div>
      <input
        ref="fileInput"
        type="file"
        multiple
        accept="image/*"
        @change="handleFileUpload"
        class="hidden-file-input"
      />

      <!-- Список изображений -->
      <div class="image-gallery-list">
        <div
          v-for="(image, index) in value"
          :key="image.link"
          class="image-gallery-item"
          draggable="true"
          @dragstart="dragStart(index, $event)"
          @dragover.prevent
          @drop="drop(index)"
        >
          <!-- Перетаскивание -->
          <div class="drag-handle">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <line x1="7" y1="5" x2="17" y2="5"></line>
              <line x1="7" y1="9" x2="17" y2="9"></line>
              <line x1="7" y1="13" x2="17" y2="13"></line>
              <line x1="7" y1="17" x2="17" y2="17"></line>
            </svg>
          </div>

          <!-- Изображение слева -->
          <div class="image-gallery-container">
            <img :src="imageSrc(image.link)" class="image-gallery-preview" />
          </div>

          <!-- Данные справа -->
          <div class="image-gallery-details">
            <div class="input-container">
              <input type="text" v-model="image.description" placeholder="Описание" class="image-gallery-input" />
              <input type="text" v-model="image.author" placeholder="Автор" class="image-gallery-input" />
            </div>
            <button @click="removeImage(index)" class="image-gallery-btn-danger" title="Удалить">
              <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M3 6h18"></path>
                <path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"></path>
                <path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"></path>
              </svg>
            </button>
          </div>
        </div>
      </div>
    </template>
  </DefaultField>
</template>

<script>
import { FormField, HandlesValidationErrors } from "laravel-nova";
import axios from "axios";

export default {
  mixins: [FormField, HandlesValidationErrors],

  props: ["resourceName", "resourceId", "field"],

  data() {
    return {
      value: [], // [{ link, description, author }]
      draggedIndex: null, // Индекс перетаскиваемого элемента
    };
  },

  mounted() {
    console.log("🚀 Компонент загружен, value:", this.value);
    this.setInitialValue();
  },

  watch: {
    field: {
      immediate: true,
      deep: true,
      handler(newField) {
        if (newField.value) {
          this.setInitialValue();
        }
      }
    }
  },

  methods: {
    setInitialValue() {
      if (this.field.value) {
        if (typeof this.field.value === 'object') {
          this.value = this.field.value;
        } else {
          try {
            this.value = JSON.parse(this.field.value) || [];
          } catch (error) {
            console.error("Ошибка парсинга значения:", error);
            this.value = [];
          }
        }
      }
    },

    triggerFileInput() {
      this.$refs.fileInput.click();
    },

    async handleFileUpload(event) {
      const files = event.target.files;
      for (let file of files) {
        const formData = new FormData();
        formData.append("file", file);
        formData.append("_token", document.querySelector('meta[name="csrf-token"]').getAttribute("content"));

        try {
          const response = await axios.post("/nova-vendor/medov/image-gallery/upload-image", formData);
          this.value.push({
            id: response.data.id,
            link: response.data.link,
            author: "",
            description: "",
          });
        } catch (error) {
          console.error("Ошибка загрузки файла:", error);
        }
      }
      this.$refs.fileInput.value = "";
    },

    imageSrc(link) {
      if (!link) return '';
      if (link.startsWith('http')) return link;
      return '/storage/' + link;
    },

    removeImage(index) {
      this.value.splice(index, 1);
    },

    dragStart(index, event) {
      this.draggedIndex = index;
      event.dataTransfer.effectAllowed = "move";
    },

    drop(index) {
      if (this.draggedIndex === null) return;

      const movedItem = this.value.splice(this.draggedIndex, 1)[0];
      this.value.splice(index, 0, movedItem);

      this.draggedIndex = null;
    },

    fill(formData) {
      if (typeof this.value === 'object' && this.value !== null) {
        Object.entries(this.value).forEach(([key, val]) => {
          if (typeof val === 'object' && val !== null) {
            Object.entries(val).forEach(([subKey, subVal]) => {
                formData.append(`${this.field.attribute}[${key}][${subKey}]`, subVal ?? '');
            });
          } else {
            formData.append(`${this.field.attribute}[${key}]`, val ?? '');
          }
        });
      } else {
        formData.append(this.field.attribute, this.value ?? '');
      }
    }
  }
};
</script>

<style scoped>
.upload-box {
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 10px;
  border-radius: 4px;
  font-weight: bold;
  color: #1976d2;
  cursor: pointer;
  transition: border 0.3s;
  border: 2px solid #1976d2;
  width: 200px;
  text-align: center;
}

.upload-box:hover {
  border-color: #1565c0;
}

.hidden-file-input {
  display: none;
}

.image-gallery-list {
  display: flex;
  flex-direction: column;
  gap: 15px;
  margin-top: 10px;
}

.image-gallery-item {
  display: flex;
  align-items: center;
  border: 1px solid #ccc;
  padding: 10px;
  border-radius: 6px;
  gap: 15px;
  background: white;
}

.image-gallery-container {
  width: 200px;
  align-self: flex-start;
}

.image-gallery-preview {
  width: 100%;
  max-width: 200px;
  border-radius: 4px;
  border: 1px solid #ccc;
}

.image-gallery-details {
  display: flex;
  flex: 1;
  gap: 8px;
  align-items: flex-start;
  min-height: 88px;
}

.input-container {
  display: flex;
  flex-direction: column;
  flex: 1;
  gap: 8px;
}

.image-gallery-input {
  padding: 8px;
  border: 1px solid #ccc;
  border-radius: 4px;
  font-size: 14px;
  width: 100%;
}

.image-gallery-btn-danger {
  padding: 8px;
  background-color: #e54839;
  color: white;
  border: none;
  border-radius: 4px;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  width: 40px;
  height: 40px;
  transition: background-color 0.3s;
  flex-shrink: 0;
}

.image-gallery-btn-danger:hover {
  background-color: #c03d31;
}

.drag-handle {
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: grab;
  padding: 8px 12px;
  user-select: none;
  color: #666;
  margin: 0 2px;
}

.drag-handle svg {
  width: 24px;
  height: 24px;
}
</style>