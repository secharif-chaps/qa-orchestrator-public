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
    setupFiles: ['./src/test-utils/setup.ts'],
    reporters: ['default', 'junit'],
    outputFile: {
      junit: './test-results.xml',
    },
    coverage: {
      provider: 'v8',
      reporter: ['text', 'cobertura'],
      include: [
        'src/components/**/*.{ts,vue}',
        'src/composables/**/*.ts',
        'src/stores/**/*.ts',
        'src/utils/**/*.ts',
        'src/target/**/*.{ts,vue}',
      ],
      exclude: [
        '**/__tests__/**',
        '**/*.spec.ts',
        '**/*.test.ts',
        '**/*.d.ts',
        'src/test-utils/**',
        'node_modules/**',
      ],
      thresholds: {
        lines: 5,
        branches: 4,
        functions: 4,
        statements: 5,
      },
    },
  },
  resolve: {
    alias: {
      '@': fileURLToPath(new URL('./src', import.meta.url)),
      '@target': fileURLToPath(new URL('./src/target', import.meta.url)),
    },
  },
})
