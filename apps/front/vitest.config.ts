import { defineConfig } from 'vitest/config'
import vue from '@vitejs/plugin-vue'
import { fileURLToPath } from 'url'

export default defineConfig({
  plugins: [vue()],
  test: {
    environment: 'jsdom',
    globals: true,
    include: ['src/**/*.{test,spec}.{js,ts}'],
    // Type-only files that contain no test suites (just TS type assertions)
    exclude: ['src/types/*.spec.ts'],
    setupFiles: ['./src/tests/setup.ts'],
  },
  resolve: {
    alias: {
      '@': fileURLToPath(new URL('./src', import.meta.url)),
      '@target': fileURLToPath(new URL('./src/target', import.meta.url)),
    },
  },
})
