import { readFileSync } from 'node:fs'
import { fileURLToPath, URL } from 'node:url'

import vue from '@vitejs/plugin-vue'
import VueRouter from 'unplugin-vue-router/vite'
import { defineConfig, loadEnv } from 'vite'
import vueDevTools from 'vite-plugin-vue-devtools'

import tailwindcss from '@tailwindcss/vite'

// https://vite.dev/config/
export default defineConfig(({ mode }) => {
  // Load env from root first, then override with src/target
  const rootEnv = loadEnv(mode, '.', '')
  const targetEnv = loadEnv(mode, './src/target', '')
  const mergedEnv = { ...rootEnv, ...targetEnv }

  // Inject merged env vars into process.env so Vite picks them up
  Object.assign(process.env, mergedEnv)

  return {
    plugins: [
      VueRouter({
        /* options */
      }),
      vue(),
      vueDevTools(),
      tailwindcss(),
    ],
    resolve: {
      alias: {
        '@': fileURLToPath(new URL('./src', import.meta.url)),
        '~': fileURLToPath(new URL('./src/target', import.meta.url)),
      },
    },
    server: {
      port: 3000,
    },
  }
})
