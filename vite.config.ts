import path from 'path'
import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import vueDevTools from 'vite-plugin-vue-devtools'

// https://vite.dev/config/
export default defineConfig({
  plugins: [
    vue(),
    vueDevTools(),
  ],
    build: {
    outDir: "build",
    assetsDir: "media",
    sourcemap: true,
    minify: "terser",
    target: "es2020",
    cssCodeSplit: true,
    chunkSizeWarningLimit: 500,
    rollupOptions: {
      output: {
        manualChunks(id) {
          if (id.includes("node_modules")) {
            const pkgName = id.match(
              /node_modules\/((@[^\/]+\/[^\/]+)|[^\/]+)/,
            )?.[1];
            if (pkgName) return `vendor-${pkgName.replace("@", "")}`;
          }
        },
      },
    },
  },
  resolve: {
    alias: {
      "@utils": path.resolve(__dirname, "./src/utils"),
      "@components": path.resolve(__dirname, "./src/components"),
      "@pages": path.resolve(__dirname, "./src/pages"),
      "@styles": path.resolve(__dirname, "./src/styles"),
      "@config": path.resolve(__dirname,"./src/config")
    },
  },
})
