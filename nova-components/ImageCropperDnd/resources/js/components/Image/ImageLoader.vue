<template>
  <LoadingCard
    ref="card"
    :loading="loading"
    class="card relative overflow-hidden px-0 py-0"
  >
    <div v-if="loading" style="height: 100px" />

    <div class="missing p-8" v-if="missing">
      <p class="text-center leading-normal">
        <a :href="src" class="text-primary dim" target="_blank">{{
          __("This image")
        }}</a>
        {{ __("could not be found.") }}
      </p>
    </div>
  </LoadingCard>
</template>

<script>
import Minimum from "@/minimum";

export default {
  props: {
    src: String,
  },

  data: () => ({
    loading: true,
    missing: false,
  }),

  mounted() {
    Minimum(
      new Promise((resolve, reject) => {
        const image = new Image();
        image.addEventListener("load", () => resolve(image));
        image.addEventListener("error", () => reject());
        image.src = this.src;
      })
    )
      .then((image) => {
        image.className = "image-loader-preview";
        image.draggable = false;
        this.$refs.card.$el.appendChild(image);
        this.loading = false;
      })
      .catch(() => {
        this.missing = true;
        this.$emit("missing", true);
        this.loading = false;
      });
  },
};
</script>

<style scoped>
.card {
  padding: 0 !important;
  width: 100%;
  background-color: #c9c9c9;
  background-image:
    linear-gradient(45deg, #b6b6b6 25%, transparent 25%),
    linear-gradient(-45deg, #b6b6b6 25%, transparent 25%),
    linear-gradient(45deg, transparent 75%, #b6b6b6 75%),
    linear-gradient(-45deg, transparent 75%, #b6b6b6 75%);
  background-size: 20px 20px;
  background-position: 0 0, 0 10px, 10px -10px, -10px 0;
  border: none !important;
  box-shadow: none !important;
  border-radius: 0 !important;
}

.card :deep(.rounded),
.card :deep([class*="rounded"]) {
  border-radius: 0 !important;
}

.card :deep(.shadow),
.card :deep([class*="shadow"]) {
  box-shadow: none !important;
}

.card :deep(img.image-loader-preview) {
  display: block;
  width: 100%;
  height: 360px;
  object-fit: contain;
  object-position: center;
  background: transparent;
}
</style>
