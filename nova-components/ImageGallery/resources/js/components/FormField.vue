<template>
  <DefaultField
    :field="field"
    :errors="errors"
    :show-help-text="showHelpText"
    :full-width-content="fullWidthContent"
  >
    <template #field>
      <!-- Упрощённая форма загрузки -->
      <div
        class="upload-box"
        :class="{ 'upload-box--disabled': isUploading }"
        role="button"
        :tabindex="isUploading ? -1 : 0"
        :aria-disabled="isUploading ? 'true' : 'false'"
        @click="onUploadButtonClick"
        @keydown.enter.prevent="onUploadButtonClick"
        @keydown.space.prevent="onUploadButtonClick"
      >
        <span>Выбрать файлы</span>
      </div>
      <input
        ref="fileInput"
        type="file"
        multiple
        accept="image/*"
        :disabled="isUploading"
        @change="handleFileUpload"
        class="hidden-file-input"
      />

      <!-- Большая зона drag&drop -->
      <div
        class="upload-dropzone"
        :class="{
          'upload-dropzone--dragover': isDraggingOverDropzone,
          'upload-dropzone--uploading': isUploading
        }"
        @dragenter.prevent="onDropzoneDragEnter"
        @dragover.prevent="onDropzoneDragOver"
        @dragleave.prevent="onDropzoneDragLeave"
        @drop.prevent="onDropzoneDrop"
      >
        <template v-if="isUploading">
          <span class="upload-dropzone__loader upload-dropzone__loader--yellow" aria-hidden="true" />
          <div class="upload-dropzone__loading-title">Загрузка</div>
        </template>
        <template v-else>
          <div class="upload-dropzone__title">
            {{
              isDraggingOverDropzone
                ? 'Отпустите файлы — начнём загрузку'
                : 'Для загрузки перетащите файлы сюда или выберите их через кнопку выше'
            }}
          </div>
          <div class="upload-dropzone__subtitle">Поддерживается мультизагрузка</div>
        </template>
      </div>

      <textarea
        :name="field.attribute + '__gallery_state'"
        :value="galleryStateJson"
        class="image-gallery-state-field"
        aria-hidden="true"
        tabindex="-1"
      ></textarea>

      <!-- Список изображений -->
      <div class="image-gallery-list">
        <div
          v-for="(image, index) in value"
          :key="image.link"
          class="image-gallery-item"
          :class="{ 'image-gallery-item--dragging': draggedIndex === index }"
          @dragover.prevent
          @drop="drop(index)"
        >
          <!-- Перетаскивание только с ручки — иначе draggable на строке ломает выделение в полях -->
          <div
            class="drag-handle"
            draggable="true"
            @dragstart="dragStart(index, $event)"
            @dragend="dragEnd"
          >
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
              <input type="text" v-model="image.description" :name="field.attribute + '_desc_' + index" placeholder="Описание" class="image-gallery-input" />
              <input type="text" v-model="image.author" :name="field.attribute + '_author_' + index" placeholder="Автор" class="image-gallery-input" />
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
import debounce from "lodash/debounce";

export default {
  mixins: [FormField, HandlesValidationErrors],

  props: ["resourceName", "resourceId", "field"],

  data() {
    return {
      value: [], // [{ link, description, author }]
      draggedIndex: null, // Индекс перетаскиваемого элемента
      isDraggingOverDropzone: false,
      uploadingCount: 0,
      /** Не триггерить автосохранение при setInitialValue / подстановке с сервера */
      galleryAutosaveWatchPaused: true,
    };
  },

  computed: {
    isUploading() {
      return this.uploadingCount > 0;
    },
    galleryStateJson() {
      return JSON.stringify((this.value || []).map(function (img) {
        return img.link || '';
      }));
    },
  },

  created() {
    this.scheduleGalleryAutosave = debounce(this.runGalleryAutosave.bind(this), 400);
  },

  mounted() {
    this.setInitialValue();
  },

  watch: {
    value: {
      deep: true,
      handler() {
        if (this.scheduleGalleryAutosave) {
          this.scheduleGalleryAutosave();
        }
      },
    },
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
    runGalleryAutosave() {
      if (this.galleryAutosaveWatchPaused) {
        return;
      }
      if (typeof this.emitFieldValueChange === "function") {
        this.emitFieldValueChange(this.fieldAttribute, this.value);
      }
      this.notifyAutosaveChange("image-gallery");
    },

    notifyAutosaveChange(source) {
      if (typeof document === "undefined") return;

      document.dispatchEvent(new CustomEvent("nova-autosave:change", {
        detail: {
          attribute: this.field.attribute,
          source: source || "image-gallery",
        },
      }));
    },

    setInitialValue() {
      this.galleryAutosaveWatchPaused = true;
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
      this.$nextTick(() => {
        this.galleryAutosaveWatchPaused = false;
      });
    },

    triggerFileInput() {
      this.$refs.fileInput.click();
    },

    onUploadButtonClick() {
      if (this.isUploading) return;
      this.triggerFileInput();
    },

    async handleFileUpload(event) {
      if (this.isUploading) {
        if (this.$refs.fileInput) this.$refs.fileInput.value = "";
        return;
      }
      const files = event?.target?.files;
      await this.uploadFiles(files);
      if (this.$refs.fileInput) this.$refs.fileInput.value = "";
    },

    onDropzoneDragEnter() {
      if (this.isUploading) return;
      this.isDraggingOverDropzone = true;
    },

    onDropzoneDragOver() {
      if (this.isUploading) return;
      this.isDraggingOverDropzone = true;
    },

    onDropzoneDragLeave(event) {
      if (this.isUploading) return;
      // Снимаем подсветку только когда курсор реально ушёл из зоны
      if (!event?.currentTarget?.contains(event?.relatedTarget)) {
        this.isDraggingOverDropzone = false;
      }
    },

    async onDropzoneDrop(event) {
      if (this.isUploading) return;
      this.isDraggingOverDropzone = false;
      const files = event?.dataTransfer?.files;
      await this.uploadFiles(files);
    },

    async uploadFiles(files) {
      if (!files || !files.length) return;

      const csrf = document
        .querySelector('meta[name="csrf-token"]')
        ?.getAttribute("content");

      const candidates = Array.from(files).filter((f) => {
        if (!f) return false;
        if (f.type && f.type.startsWith("image/")) return true;
        return /\.(png|jpe?g|webp|gif)$/i.test(f.name || "");
      });

      this.uploadingCount += candidates.length;

      await Promise.allSettled(
        candidates.map(async (file) => {
          const formData = new FormData();
          formData.append("file", file);
          if (this.field?.storageDisk) {
            formData.append("storage_disk", this.field.storageDisk);
          }
          if (this.field?.imageType) {
            formData.append("image_type", this.field.imageType);
          }
          if (csrf) formData.append("_token", csrf);

          try {
            const response = await axios.post(
              "/nova-vendor/medov/image-gallery/upload-image",
              formData
            );
            this.value.push({
              id: response.data.id,
              link: response.data.link,
              width: Number(response.data.width),
              height: Number(response.data.height),
              author: "",
              description: "",
            });
          } catch (error) {
            console.error("Ошибка загрузки файла:", error);
          } finally {
            this.uploadingCount = Math.max(0, this.uploadingCount - 1);
          }
        })
      );
    },

    imageSrc(link) {
      if (!link) return '';
      if (link.startsWith('http')) return link;
      const base = (this.field.storageUrl || '/storage').replace(/\/$/, '');
      return base + '/' + link;
    },

    removeImage(index) {
      this.value.splice(index, 1);
    },

    dragStart(index, event) {
      this.draggedIndex = index;
      const dt = event.dataTransfer;
      if (dt) {
        dt.effectAllowed = "move";
        dt.setData("text/plain", String(index));
      }

      const handle = event.currentTarget;
      const row = handle && handle.closest && handle.closest(".image-gallery-item");
      if (!row || !dt || typeof dt.setDragImage !== "function") {
        return;
      }

      const clone = row.cloneNode(true);
      clone.classList.add("image-gallery-item--drag-ghost");
      const cloneHandle = clone.querySelector(".drag-handle");
      if (cloneHandle) {
        cloneHandle.removeAttribute("draggable");
      }

      clone.style.cssText =
        "position:absolute;left:-9999px;top:0;width:" +
        row.offsetWidth +
        "px;z-index:10000;margin:0;pointer-events:none;";
      document.body.appendChild(clone);

      const rowRect = row.getBoundingClientRect();
      const x = Math.max(0, Math.round(event.clientX - rowRect.left));
      const y = Math.max(0, Math.round(event.clientY - rowRect.top));

      try {
        dt.setDragImage(clone, x, y);
      } catch (e) {
        clone.remove();
        return;
      }

      this._dragGhostEl = clone;
    },

    dragEnd() {
      if (this._dragGhostEl && this._dragGhostEl.parentNode) {
        this._dragGhostEl.parentNode.removeChild(this._dragGhostEl);
      }
      this._dragGhostEl = null;
      this.draggedIndex = null;
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

.upload-box--disabled {
  opacity: 0.55;
  cursor: not-allowed;
  pointer-events: none;
}

.hidden-file-input {
  display: none;
}

.upload-dropzone {
  margin-top: 10px;
  width: 100%;
  min-height: 140px;
  border-radius: 10px;
  border: 2px dashed #c7c7c7;
  background: #f3f4f6;
  color: #6b7280;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  text-align: center;
  transition: border-color 0.15s ease, background 0.15s ease, color 0.15s ease;
  user-select: none;
}

.upload-dropzone--dragover {
  border-color: #1976d2;
  background: rgba(25, 118, 210, 0.08);
  color: #1976d2;
}

.upload-dropzone__title {
  font-weight: 700;
  line-height: 1.2;
}

.upload-dropzone__subtitle {
  margin-top: 6px;
  font-size: 13px;
  font-weight: 500;
  opacity: 0.9;
}

.upload-dropzone__loader {
  width: 28px;
  height: 28px;
  border-radius: 999px;
  border: 3px solid currentColor;
  border-top-color: transparent;
  display: inline-block;
  animation: upload-dropzone-spin 0.8s linear infinite;
}

.upload-dropzone__loader--yellow {
  color: #f5c542;
}

@keyframes upload-dropzone-spin {
  to {
    transform: rotate(360deg);
  }
}

.upload-dropzone__loading-title {
  margin-top: 10px;
  font-weight: 700;
  color: #6b5a45;
}

.upload-dropzone--uploading {
  cursor: progress;
  border-style: solid;
  border-color: #ddc8a4;
  background: linear-gradient(180deg, #faf6ee 0%, #f5eddc 100%);
  color: #7a654b;
  pointer-events: none;
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

.image-gallery-item--dragging {
  opacity: 0.38;
  outline: 2px dashed #1976d2;
  outline-offset: 2px;
}

.image-gallery-item--drag-ghost {
  box-shadow: 0 10px 28px rgba(0, 0, 0, 0.2);
  border-radius: 6px;
  background: #fff;
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

.image-gallery-state-field {
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

@media (max-width: 768px) {
  .image-gallery-item {
    position: relative;
    flex-wrap: wrap;
  }

  .image-gallery-details {
    flex-basis: 100%;
    width: 100%;
  }

  .image-gallery-btn-danger {
    position: absolute;
    top: 10px;
    right: 10px;
  }
}
</style>