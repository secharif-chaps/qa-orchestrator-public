import { defineConfig } from 'vitest/config'
import { resolve } from 'path'

export default defineConfig({
  test: {
    globals: true,
    environment: 'jsdom',
    setupFiles: ['./tests/setup.e2e.ts'],
    include: [
      './tests/e2e/**/*.{test,spec}.{js,mjs,cjs,ts,mts,cts,jsx,tsx}'
    ],
    testTimeout: 60000,
    hookTimeout: 60000,
    // Run tests sequentially for e2e to avoid conflicts
    pool: 'threads',
    poolOptions: {
      threads: {
        singleThread: true
      }
    },
    env: {
      NODE_ENV: 'test'
    }
  },
  resolve: {
    alias: [
      { find: "@", replacement: resolve(__dirname, ".") }, 
      { find: "~", replacement: resolve(__dirname, ".") }
    ]
  },
})