<template>
  <DefaultField
    :field="field"
    :errors="errors"
    :full-width-content="true"
    :show-help-text="false"
  >
    <template #field>
      <div class="upload-controls-row mb-2">
        <div
          class="upload-box"
          :class="{ 'upload-box--disabled': false }"
          role="button"
          tabindex="0"
          @click="triggerFileInput"
          @keydown.enter.prevent="triggerFileInput"
          @keydown.space.prevent="triggerFileInput"
        >
          <span>{{ __("Choose image") }}</span>
        </div>

        <button
          v-if="!imgSrc && hasExistingImage && isDeletable"
          type="button"
          class="image-viewer-delete-btn"
          @click="openExistingImageDeleteModal"
          :title="__('Delete')"
          :aria-label="__('Delete')"
        >
          <svg
            xmlns="http://www.w3.org/2000/svg"
            width="18"
            height="18"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
            stroke-linecap="round"
            stroke-linejoin="round"
            aria-hidden="true"
          >
            <path d="M3 6h18"></path>
            <path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"></path>
            <path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"></path>
          </svg>
        </button>
      </div>

      <input
        ref="fileField"
        :dusk="field.attribute"
        class="hidden-file-input"
        type="file"
        :id="idAttr"
        name="name"
        :accept="field.acceptedTypes"
        @change="onInputFileChange"
      />

      <div class="text-gray-500 text-sm mt-2 mb-4">
        {{ currentLabel }}
      </div>

      <div
        class="upload-dropzone"
        :class="{ 'upload-dropzone--dragover': isDraggingOverDropzone }"
        @dragenter.prevent="onDropzoneDragEnter"
        @dragover.prevent="onDropzoneDragOver"
        @dragleave.prevent="onDropzoneDragLeave"
        @drop.prevent="onDropzoneDrop"
      >
        <div class="upload-dropzone__title">
          {{
            isDraggingOverDropzone
              ? __("Release the image to upload")
              : __("Drag an image here or choose via button above")
          }}
        </div>
      </div>

      <ImageViewer
        ref="existingImageViewer"
        :key="existingImageViewerKey"
        @image-deleted="imageDeleted"
        @image-delete-failed="imageDeleteFailed"
        v-show="!imgSrc && !existingImageDeleted"
        :field="effectiveField"
        :resourceId="resourceId"
        :resourceName="resourceName"
        :relatedResourceId="relatedResourceId"
        :relatedResourceName="relatedResourceName"
        :viaRelationship="viaRelationship"
      />

      <img
        v-if="imgSrc && skipCrop"
        :src="imgSrc"
        class="mb-4 max-w-full rounded"
        style="max-height: 300px;"
      />

      <VueCropper
        v-if="field.croppable"
        v-show="imgSrc && !skipCrop"
        class="mb-2"
        ref="cropper"
        :view-mode="1"
        :aspect-ratio="field.aspectRatio || NaN"
        :auto-crop-area="1"
        :src="imgSrc"
        @cropend="onCropEnd"
        @ready="onCropReady"
      />

      <div v-if="imgSrc" class="cancel-upload-row mb-6">
        <div v-if="selectedImageMetaText" class="selected-image-meta">
          {{ selectedImageMetaText }}
          <div
            v-if="dimensionsBelowMinimum && belowMinimumCropLineText"
            class="selected-crop-meta text-red-500"
          >
            {{ belowMinimumCropLineText }}
          </div>
          <div v-else-if="selectedCropMetaText" class="selected-crop-meta">
            {{ selectedCropMetaText }}
          </div>
        </div>

        <button
          type="button"
          class="cancel-upload-btn"
          @click="cancel"
          :title="__('Cancel image selection')"
          :aria-label="__('Cancel image selection')"
        >
          <svg
            xmlns="http://www.w3.org/2000/svg"
            width="16"
            height="16"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
            stroke-linecap="round"
            stroke-linejoin="round"
            aria-hidden="true"
          >
            <line x1="18" y1="6" x2="6" y2="18"></line>
            <line x1="6" y1="6" x2="18" y2="18"></line>
          </svg>
          <span>{{ __("Cancel image selection") }}</span>
        </button>
      </div>

      <p
        v-if="field.helpText"
        class="mt-2 text-xs"
        :class="
          imageSizeError || (!skipCrop && !cropDimensionsValid)
            ? 'text-red-500'
            : 'text-gray-400'
        "
      >
        {{ field.helpText }}
        <span v-if="imageSizeError"> ({{ imageSizeError }})</span>
      </p>

      <div class="ignore-dimensions-row mt-3 mb-6">
        <label class="ignore-dimensions-label flex cursor-pointer m-0 w-full">
          <div class="ignore-dimensions-checkbox-wrap shrink-0">
            <input
              type="checkbox"
              v-model="ignoreDimensionRequirements"
              class="ignore-dimensions-checkbox"
            />
          </div>
          <div class="ignore-dimensions-copy min-w-0 flex-1 pl-1">
            <div
              class="ignore-dimensions-title font-medium text-gray-900 dark:text-gray-100"
            >
              {{ __("Ignore image dimension requirements") }}
            </div>
            <div
              class="ignore-dimensions-help mt-1.5 text-xs leading-normal text-gray-400 dark:text-gray-500"
            >
              <span class="font-extralight">{{
                __(
                  "Use this option only if no high-quality publication image is available. Low-quality images reduce the overall quality of the site."
                )
              }}</span>
              <span class="font-light">{{
                " " +
                __(
                  "If enabled, minimum image dimensions (900x600) will not be validated."
                )
              }}</span>
            </div>
          </div>
        </label>
      </div>

      <p v-if="hasError" class="text-xs mt-2 text-danger">
        {{ firstError }}
      </p>
    </template>
  </DefaultField>
</template>

<script>
import "cropperjs/dist/cropper.css";
import VueCropper from "vue-cropperjs";
import { FormField, HandlesValidationErrors, Errors } from "laravel-nova";

import ImageViewer from "@/components/Image/ImageViewer";

export default {
  props: [
    "field",
    "resourceId",
    "resourceName",
    "relatedResourceId",
    "relatedResourceName",
    "viaRelationship",
  ],

  mixins: [HandlesValidationErrors, FormField],

  components: { VueCropper, ImageViewer },

  data: () => ({
    imgSrc: "",
    file: null,
    fileName: "",
    uploadErrors: new Errors(),
    cropWidth: null,
    cropHeight: null,
    actualWidth: null,
    actualHeight: null,
    fileSizeBytes: null,
    skipCrop: false,
    imageSizeError: null,
    isDraggingOverDropzone: false,
    existingImageDeleted: false,
    existingImageViewerKey: 0,
    persistedPreviewUrl: null,
    persistedFieldValue: null,
    saveSyncTimeout: null,
    ignoreDimensionRequirements: false,
  }),

  mounted() {
    this.onSaveWithoutReloadSuccess = this.onSaveWithoutReloadSuccess.bind(this);
    this.onResourceUpdated = this.onResourceUpdated.bind(this);
    window.addEventListener(
      "nova:save-without-reload:success",
      this.onSaveWithoutReloadSuccess
    );
    if (typeof Nova !== "undefined" && Nova.$on) {
      Nova.$on("resource-updated", this.onResourceUpdated);
    }
  },

  beforeUnmount() {
    if (this.onSaveWithoutReloadSuccess) {
      window.removeEventListener(
        "nova:save-without-reload:success",
        this.onSaveWithoutReloadSuccess
      );
    }
    if (typeof Nova !== "undefined" && Nova.$off && this.onResourceUpdated) {
      Nova.$off("resource-updated", this.onResourceUpdated);
    }
    if (this.saveSyncTimeout) {
      clearTimeout(this.saveSyncTimeout);
      this.saveSyncTimeout = null;
    }
  },

  watch: {
    "field.previewUrl"(next, prev) {
      if (next === prev || this.file || this.imgSrc) {
        return;
      }
      this.persistedPreviewUrl = null;
      this.persistedFieldValue = null;
      this.existingImageViewerKey += 1;
    },
    "field.value"(next, prev) {
      if (next === prev || this.file || this.imgSrc) {
        return;
      }
      this.persistedPreviewUrl = null;
      this.persistedFieldValue = null;
      this.existingImageViewerKey += 1;
    },
    ignoreDimensionRequirements(val, oldVal) {
      const hadSkipCropBeforeApply = this.skipCrop;
      this.applyDimensionRulesToImage();

      // Включаем игнор: не трогаем cropper.replace — сохраняем выбранную область.
      // Исключение: раньше был skipCrop (мелкое фото), теперь нужен кроппер — один раз подставляем картинку.
      if (val === true && oldVal === false) {
        if (hadSkipCropBeforeApply && !this.skipCrop) {
          this.$nextTick(() => {
            if (this.imgSrc && this.field.croppable && this.$refs.cropper) {
              this.$refs.cropper.replace(this.imgSrc);
            }
          });
        }
        return;
      }

      // Выключаем игнор: сбрасываем область кадрирования к дефолту пакета
      if (val === false && oldVal === true) {
        this.$nextTick(() => {
          if (
            this.imgSrc &&
            this.field.croppable &&
            !this.skipCrop &&
            this.$refs.cropper
          ) {
            this.$refs.cropper.replace(this.imgSrc);
          }
        });
      }
    },
  },

  methods: {
    fill(formData) {
      formData.append(
        "ignore_image_dimension_requirements",
        this.ignoreDimensionRequirements ? "1" : "0"
      );
      if (this.file) {
        if (
          this.field.croppable &&
          this.effectiveMinWidth &&
          this.effectiveMinHeight &&
          !this.skipCrop &&
          !this.cropDimensionsValid
        ) {
          Nova.$toasted.show(
            this.__("Crop area :current is too small. Minimum: :min px.", {
              current: `${Math.round(this.cropWidth)}x${Math.round(this.cropHeight)}`,
              min: `${this.effectiveMinWidth}x${this.effectiveMinHeight}`,
            }),
            { type: "error", duration: 4000 }
          );
          // fill() бросает до axios: нет markRequestResult. stayAction использует saveWithoutReload (state.active),
          // не submitResource — прежний unlockSimpleSubmit не снимал «Сохранить…».
          if (
            typeof window !== "undefined" &&
            window.NovaFormActionBar &&
            typeof window.NovaFormActionBar.unlockSimpleSubmit === "function"
          ) {
            window.NovaFormActionBar.unlockSimpleSubmit();
          }
          throw new Error("crop-dimensions-invalid");
        }
        formData.append(this.field.attribute, this.file, this.fileName);
        if (this.field.croppable) {
          const cropData = this.skipCrop
            ? {
                x: 0,
                y: 0,
                width: this.actualWidth || 0,
                height: this.actualHeight || 0,
              }
            : this.$refs.cropper.getData(true);
          formData.append(
            this.field.attribute + "_data",
            JSON.stringify(cropData)
          );
        }
      }
    },

    cancel() {
      this.clearPendingSelection();
      this.existingImageDeleted = false;
      if (this.uploadErrors?.clear) {
        this.uploadErrors.clear(this.fieldAttribute);
      }
      if (this.errors?.clear) {
        this.errors.clear(this.fieldAttribute);
      }
    },

    clearPendingSelection() {
      this.imgSrc = "";
      this.file = null;
      this.fileName = "";
      this.cropWidth = null;
      this.cropHeight = null;
      this.actualWidth = null;
      this.actualHeight = null;
      this.fileSizeBytes = null;
      this.skipCrop = false;
      this.imageSizeError = null;
      this.isDraggingOverDropzone = false;
      this.ignoreDimensionRequirements = false;
      if (this.$refs.fileField) {
        this.$refs.fileField.value = "";
      }
    },

    onSaveWithoutReloadSuccess() {
      if (this.saveSyncTimeout) {
        clearTimeout(this.saveSyncTimeout);
      }
      this.saveSyncTimeout = setTimeout(() => {
        this.saveSyncTimeout = null;
        this.syncAfterResourceSave();
      }, 0);
    },

    onResourceUpdated(payload) {
      if (
        payload?.resourceName !== this.resourceName ||
        String(payload?.resourceId) !== String(this.resourceId)
      ) {
        return;
      }
      this.onSaveWithoutReloadSuccess();
    },

    async syncAfterResourceSave() {
      if (this.file || this.imgSrc) {
        this.promotePendingUploadToExisting();
      }
      await this.refreshFieldFromServer();
    },

    promotePendingUploadToExisting() {
      if (!this.file && !this.imgSrc) {
        return;
      }

      this.persistedPreviewUrl = this.imgSrc;
      this.persistedFieldValue = this.fileName || String(Date.now());

      this.clearPendingSelection();
      this.existingImageDeleted = false;
      this.existingImageViewerKey += 1;
    },

    async refreshFieldFromServer() {
      if (!this.resourceName || !this.resourceId) {
        return;
      }

      try {
        const params = {
          editing: true,
          editMode: "update",
        };

        const { data } = await Nova.request().get(
          `/nova-api/${this.resourceName}/${this.resourceId}/update-fields`,
          { params }
        );

        const fields = Array.isArray(data?.fields) ? data.fields : [];
        const imageField = fields.find(
          (item) => item?.attribute === this.field?.attribute
        );
        if (!imageField) {
          return;
        }

        this.persistedPreviewUrl = imageField.previewUrl || null;
        this.persistedFieldValue = imageField.value || null;
        this.clearPendingSelection();
        this.existingImageDeleted = false;
        this.existingImageViewerKey += 1;
      } catch (error) {
        // Keep local promote fallback when refetch fails.
      }
    },

    triggerFileInput() {
      this.$refs.fileField?.click();
    },

    openExistingImageDeleteModal() {
      if (!this.isDeletable) {
        return;
      }
      this.$refs.existingImageViewer?.confirmRemoval?.();
    },

    async onInputFileChange(event) {
      const file = event?.target?.files?.[0];
      await this.handleSelectedFile(file);
    },

    onDropzoneDragEnter() {
      this.isDraggingOverDropzone = true;
    },

    onDropzoneDragOver() {
      this.isDraggingOverDropzone = true;
    },

    onDropzoneDragLeave(event) {
      if (!event?.currentTarget?.contains(event?.relatedTarget)) {
        this.isDraggingOverDropzone = false;
      }
    },

    async onDropzoneDrop(event) {
      this.isDraggingOverDropzone = false;
      const file = event?.dataTransfer?.files?.[0];
      await this.handleSelectedFile(file);
    },

    async getImageSize(file) {
      if (typeof createImageBitmap === "function") {
        const bitmap = await createImageBitmap(file);
        const width = bitmap.width;
        const height = bitmap.height;
        bitmap.close();
        return { width, height };
      }

      return new Promise((resolve, reject) => {
        const image = new Image();
        image.onload = () =>
          resolve({ width: image.naturalWidth, height: image.naturalHeight });
        image.onerror = reject;
        image.src = URL.createObjectURL(file);
      });
    },

    async handleSelectedFile(file) {
      if (!file) return;

      const fileName = file.name || "";

      if (!this.hasAllowedExtension(file)) {
        this.cancel();
        alert(this.__("Please select a file of an allowed image format"));
        return;
      }

      if (!this.isImageFile(file)) {
        this.cancel();
        alert(this.__("Please select an image file"));
        return;
      }

      this.fileName = fileName;
      this.file = file;
      this.fileSizeBytes = file.size || null;

      this.skipCrop = false;
      this.cropWidth = null;
      this.cropHeight = null;
      this.imageSizeError = null;

      if (typeof FileReader !== "function") {
        alert(this.__("Sorry, FileReader API not supported"));
        return;
      }

      const reader = new FileReader();
      reader.onload = async (loadEvent) => {
        const dataUrl = loadEvent.target.result;

        try {
          const { width, height } = await this.getImageSize(file);
          this.actualWidth = width;
          this.actualHeight = height;
        } catch (e) {
          this.actualWidth = null;
          this.actualHeight = null;
        }

        this.applyDimensionRulesToImage();

        this.imgSrc = dataUrl;
        if (this.field.croppable && !this.skipCrop && this.$refs.cropper) {
          this.$refs.cropper.replace(dataUrl);
        }
      };
      reader.readAsDataURL(file);
    },

    onCropEnd() {
      this.updateCropDimensions();
    },

    onCropReady() {
      this.updateCropDimensions();
    },

    updateCropDimensions() {
      if (this.$refs.cropper) {
        const data = this.$refs.cropper.getData(true);
        this.cropWidth = data.width;
        this.cropHeight = data.height;
      }
    },

    imageDeleted() {
      this.existingImageDeleted = true;
      this.persistedPreviewUrl = null;
      this.persistedFieldValue = null;
      if (this.field) {
        this.field.previewUrl = null;
        this.field.thumbnailUrl = null;
        this.field.value = null;
      }
      this.$emit("file-deleted");
    },

    imageDeleteFailed() {
      this.existingImageDeleted = false;
    },

    isImageFile(file) {
      if (!file) return false;
      const mimeType = (file.type || "").toLowerCase();
      return mimeType.startsWith("image/");
    },

    hasAllowedExtension(file) {
      const fileName = (file.name || "").toLowerCase();
      const lastDotIndex = fileName.lastIndexOf(".");
      if (lastDotIndex === -1) return false;

      const extension = fileName.slice(lastDotIndex + 1);
      const allowedExtensions = new Set(["jpeg", "jpg", "png", "webp"]);

      return allowedExtensions.has(extension);
    },

    applyDimensionRulesToImage() {
      const minW = this.effectiveMinWidth;
      const minH = this.effectiveMinHeight;

      if (!this.field.croppable || !minW || !minH) {
        this.imageSizeError = null;
        this.skipCrop = false;
        return;
      }

      try {
        const width = this.actualWidth;
        const height = this.actualHeight;
        if (!width || !height) {
          throw new Error("image-size-unavailable");
        }
        if (width < minW || height < minH) {
          this.imageSizeError = `${width}x${height}px`;
          this.skipCrop = true;
        } else {
          this.imageSizeError = null;
          this.skipCrop = width === minW && height === minH;
        }
      } catch (e) {
        this.imageSizeError = null;
      }
    },
  },

  computed: {
    effectiveMinWidth() {
      if (this.ignoreDimensionRequirements) {
        return 0;
      }
      return this.field.minWidth || 0;
    },

    effectiveMinHeight() {
      if (this.ignoreDimensionRequirements) {
        return 0;
      }
      return this.field.minHeight || 0;
    },

    hasError() {
      return this.uploadErrors.has(this.fieldAttribute);
    },

    firstError() {
      if (this.hasError) {
        return this.uploadErrors.first(this.fieldAttribute);
      }
      return null;
    },

    currentLabel() {
      return this.fileName || this.__("no file selected");
    },

    idAttr() {
      return this.labelFor;
    },

    labelFor() {
      return `advanced-image-${this.field.attribute}`;
    },

    selectedImageMetaText() {
      if (!this.file) return null;

      let dimensions = this.__("Image dimensions could not be determined");
      if (this.actualWidth && this.actualHeight) {
        dimensions = `${this.actualWidth}x${this.actualHeight} px`;
      }

      let sizeText = this.__("File size is unknown");
      if (this.fileSizeBytes) {
        if (this.fileSizeBytes < 1024 * 1024) {
          sizeText = `${(this.fileSizeBytes / 1024).toFixed(1)} KB`;
        } else {
          sizeText = `${(this.fileSizeBytes / (1024 * 1024)).toFixed(2)} MB`;
        }
      }

      return `${this.__("Selected image details")}: ${dimensions} · ${sizeText}`;
    },

    selectedCropMetaText() {
      if (this.skipCrop) return null;
      if (!this.cropWidth || !this.cropHeight) return null;

      return `${this.__("Selected crop area")}: ${Math.round(
        this.cropWidth
      )}x${Math.round(this.cropHeight)} px`;
    },

    dimensionsBelowMinimum() {
      if (this.ignoreDimensionRequirements) {
        return false;
      }
      if (this.imageSizeError) {
        return true;
      }
      if (
        !this.skipCrop &&
        this.cropWidth &&
        this.cropHeight &&
        !this.cropDimensionsValid
      ) {
        return true;
      }
      return false;
    },

    /** Same wording as valid crop line, red — for under-minimum crop or whole image when too small. */
    belowMinimumCropLineText() {
      if (!this.dimensionsBelowMinimum) {
        return null;
      }
      if (!this.skipCrop && this.cropWidth && this.cropHeight) {
        return `${this.__("Selected crop area")}: ${Math.round(
          this.cropWidth
        )}x${Math.round(this.cropHeight)} px`;
      }
      if (this.actualWidth && this.actualHeight) {
        return `${this.__("Selected crop area")}: ${this.actualWidth}x${this.actualHeight} px`;
      }
      return null;
    },

    hasExistingImage() {
      if (this.existingImageDeleted) return false;
      const field = this.effectiveField;
      return Boolean(field?.previewUrl || field?.value);
    },

    effectiveField() {
      const base = this.field || {};
      const previewUrl = this.persistedPreviewUrl ?? base.previewUrl ?? null;

      return {
        ...base,
        value: this.persistedFieldValue ?? base.value ?? null,
        previewUrl,
        thumbnailUrl: previewUrl ?? base.thumbnailUrl ?? null,
      };
    },

    isDeletable() {
      return this.effectiveField?.deletable !== false;
    },

    cropDimensionsValid() {
      if (!this.cropWidth || !this.cropHeight) {
        return true;
      }
      const minWidth = this.effectiveMinWidth;
      const minHeight = this.effectiveMinHeight;
      return this.cropWidth >= minWidth && this.cropHeight >= minHeight;
    },
  },
};
</script>

<style scoped>
.hidden-file-input {
  display: none;
}

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
  width: 220px;
  text-align: center;
}

.upload-box:hover {
  border-color: #1565c0;
}

.upload-controls-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  width: 100%;
  gap: 10px;
}

.upload-box--disabled {
  opacity: 0.55;
  cursor: not-allowed;
  pointer-events: none;
}

.upload-dropzone {
  margin-top: 10px;
  width: 100%;
  min-height: 120px;
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
  padding: 0 12px;
}

.cancel-upload-btn {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  border: 1px solid #d1d5db;
  background: #fff;
  color: #374151;
  border-radius: 8px;
  padding: 8px 12px;
  font-weight: 600;
  transition: all 0.2s ease;
}

.cancel-upload-btn:hover {
  border-color: #9ca3af;
  background: #f9fafb;
}

.cancel-upload-row {
  display: flex;
  align-items: center;
  gap: 12px;
  flex-wrap: wrap;
  justify-content: space-between;
  background: #f3f4f6;
  padding: 10px 12px;
}

.selected-image-meta {
  font-size: 13px;
  color: #4b5563;
  margin-right: auto;
}

.selected-crop-meta {
  margin-top: 4px;
}

.image-viewer-delete-btn {
  padding: 8px;
  background-color: #e54839;
  color: #fff;
  border: none;
  border-radius: 6px;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 38px;
  height: 38px;
  transition: background-color 0.2s ease;
  flex-shrink: 0;
  margin-left: auto;
}

.image-viewer-delete-btn:hover {
  background-color: #c03d31;
}

/* Same visual base as .cancel-upload-row (Nova form field tone) */
.ignore-dimensions-row {
  padding: 12px 14px;
  background: #f3f4f6;
  border-radius: 8px;
}

.ignore-dimensions-label {
  align-items: flex-start;
  gap: 0;
}

.ignore-dimensions-checkbox-wrap {
  width: 1.5rem;
  min-height: 1.25rem;
  padding-right: 12px;
  display: flex;
  align-items: flex-start;
  justify-content: flex-start;
  padding-top: 2px;
}

.ignore-dimensions-checkbox {
  width: 1rem;
  height: 1rem;
  margin: 0;
  cursor: pointer;
  flex-shrink: 0;
}

.dark .ignore-dimensions-row {
  background: rgb(31 41 55 / 0.5);
}
</style>
