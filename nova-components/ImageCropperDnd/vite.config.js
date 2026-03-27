import { defineConfig } from "vite";
import vue from "@vitejs/plugin-vue";
import path from "path";

const novaPath = path.resolve(__dirname, "../../vendor/laravel/nova");

export default defineConfig({
  plugins: [vue()],
  define: {
    "process.env.NODE_ENV": JSON.stringify("production"),
  },
  build: {
    outDir: "dist",
    lib: {
      entry: path.resolve(__dirname, "resources/js/field.js"),
      name: "NovaImageCropperDnd",
      formats: ["iife"],
      fileName: () => "js/nova-image-cropper-dnd.js",
      cssFileName: "nova-image-cropper-dnd",
    },
    rollupOptions: {
      external: ["vue", "axios"],
      output: {
        globals: {
          vue: "Vue",
          axios: "axios",
        },
        assetFileNames: "css/[name].[ext]",
      },
    },
  },
  resolve: {
    extensions: [".mjs", ".js", ".jsx", ".json", ".vue"],
    alias: {
      "@": path.resolve(__dirname, "resources/js"),
      "laravel-nova": path.join(novaPath, "resources/js/mixins/packages.js"),
      vuex: path.resolve(
        __dirname,
        "node_modules/vuex/dist/vuex.esm-browser.js"
      ),
      "uid/single": path.resolve(
        __dirname,
        "node_modules/uid/single/index.mjs"
      ),
    },
  },
});
