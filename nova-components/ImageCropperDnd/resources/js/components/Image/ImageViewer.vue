<template>
  <div v-if="hasValue">
    <template v-if="shouldShowLoader">
      <ImageLoader
        :src="field.previewUrl"
        class="w-full"
        @missing="(value) => (missing = value)"
      />
    </template>

    <template v-if="field.value && !field.previewUrl">
      <card
        class="flex item-center relative border border-lg border-50 overflow-hidden p-4"
      >
        {{ field.value }}
      </card>
    </template>

    <ConfirmUploadRemovalModal
      :show="removeModalOpen"
      @confirm="removeFile"
      @close="closeRemoveModal"
    />
  </div>
</template>

<script>
import { Errors } from "laravel-nova";

import ImageLoader from "@/components/Image/ImageLoader";

export default {
  components: { ImageLoader },

  props: [
    "field",
    "resourceId",
    "resourceName",
    "relatedResourceId",
    "relatedResourceName",
    "viaRelationship",
  ],

  data: () => ({
    removeModalOpen: false,
    missing: false,
    deleted: false,
  }),

  methods: {
    confirmRemoval() {
      this.removeModalOpen = true;
    },

    closeRemoveModal() {
      this.removeModalOpen = false;
    },

    async removeFile() {
      this.uploadErrors = new Errors();
      this.closeRemoveModal();
      this.deleted = true;
      this.$emit("image-deleted");

      const {
        resourceName,
        resourceId,
        relatedResourceName,
        relatedResourceId,
        viaRelationship,
      } = this;
      const attribute = this.field.attribute;

      const uri = this.viaRelationship
        ? `/nova-api/${resourceName}/${resourceId}/${relatedResourceName}/${relatedResourceId}/field/${attribute}?viaRelationship=${viaRelationship}`
        : `/nova-api/${resourceName}/${resourceId}/field/${attribute}`;

      try {
        await Nova.request().delete(uri);
      } catch (error) {
        this.deleted = false;
        this.$emit("image-delete-failed");

        if (error?.response?.status === 422) {
          this.uploadErrors = new Errors(error.response.data.errors);
          return;
        }

        Nova?.error?.(this.__("Could not delete the image. Please try again."));
      }
    },
  },

  computed: {
    hasValue() {
      return (
        Boolean(this.field.value || this.field.previewUrl) &&
        !Boolean(this.deleted) &&
        !Boolean(this.missing)
      );
    },

    shouldShowLoader() {
      return !Boolean(this.deleted) && Boolean(this.field.thumbnailUrl);
    },

    shouldShowRemoveButton() {
      return Boolean(this.field.deletable);
    },
  },
};
</script>

<style scoped></style>
