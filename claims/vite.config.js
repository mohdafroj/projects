import { defineConfig, loadEnv } from "vite";
import path from "path";
import Vue from "@vitejs/plugin-vue";
//import ViteImages from "vite-plugin-vue-images";

// https://vitejs.dev/config/
export default defineConfig(({ mode }) => {
  const env = loadEnv(mode, process.cwd());
  return {
    base: env.VITE_BASE_PATH || '/',
    plugins: [
      Vue(),
      // ViteImages({
      //   dirs: ["src/assets/images"],
      // }),
    ],
    resolve: {
      extensions: [".mjs", ".js", ".ts", ".jsx", ".tsx", ".json", ".vue", ".css"],
      alias: {
        "@": path.resolve(__dirname, "./src"),
      },
    },
    define: {
      // __VUE_OPTIONS_API__: true,
      __VUE_PROD_HYDRATION_MISMATCH_DETAILS__: false, // Fixes the hydration mismatch warning
    },
    server: {
      port: 5174,
      // host: '0.0.0.0',
      //   strictPort: false
    }
  };
});
