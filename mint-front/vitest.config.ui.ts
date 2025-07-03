import { defineConfig } from 'vitest/config'
import { resolve } from 'path'

export default defineConfig({
  resolve: {
    alias: [
      { find: "@", replacement: resolve(__dirname, ".") }, 
      { find: "~", replacement: resolve(__dirname, ".") }
    ]
  },
  test: {
    globals: true,
    environment: 'jsdom',
    setupFiles: ['./tests/setup.ui.ts'],
    include: [
      './tests/unit/**/*.ui.test.{js,mjs,cjs,ts,mts,cts,jsx,tsx}'
    ],
    coverage: {
      provider: 'v8',
      reporter: ['text', 'json', 'html'],
      exclude: [
        'node_modules/',
        'tests/',
        '**/*.d.ts',
        '**/*.config.*',
        'dist/',
        '.nuxt/',
        'coverage/',
        'cypress/',
        '**/{karma,rollup,webpack,vite,vitest,jest,ava,babel,nyc,cypress}.config.*'
      ]
    },
    testTimeout: 10000,
    hookTimeout: 10000
  }
})