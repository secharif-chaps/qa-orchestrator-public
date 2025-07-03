import { defineConfig } from 'vitest/config'
import { defineVitestConfig } from '@nuxt/test-utils/config'
import { resolve } from 'path'

export default defineVitestConfig({
  test: {
    globals: true,
    environment: 'nuxt',
    setupFiles: ['./tests/setup.unit.ts'],
    include: [
      './tests/unit/**/*.{test,spec}.{js,mjs,cjs,ts,mts,cts,jsx,tsx}',
      './composables/**/*.{test,spec}.{js,mjs,cjs,ts,mts,cts,jsx,tsx}',
      './components/**/*.{test,spec}.{js,mjs,cjs,ts,mts,cts,jsx,tsx}'
    ],
    coverage: {
      provider: 'v8',
      reporter: ['text', 'json', 'html'],
      include: [
        'composables/**/*.{js,ts,vue}',
        'components/**/*.{js,ts,vue}',
        'stores/**/*.{js,ts}',
        'utils/**/*.{js,ts}'
      ]
    },
    testTimeout: 10000,
    hookTimeout: 10000
  },
  resolve: {
    alias: [{ find: "@", replacement: resolve(__dirname, "./src") }, { find: "~", replacement: resolve(__dirname, "./src") }]
  },
})