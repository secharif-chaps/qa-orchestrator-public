import { globalIgnores } from 'eslint/config'
import { defineConfigWithVueTs, vueTsConfigs } from '@vue/eslint-config-typescript'
import pluginVue from 'eslint-plugin-vue'
import pluginVueI18n from '@intlify/eslint-plugin-vue-i18n'
import skipFormatting from '@vue/eslint-config-prettier/skip-formatting'

// To allow more languages other than `ts` in `.vue` files, uncomment the following lines:
// import { configureVueProject } from '@vue/eslint-config-typescript'
// configureVueProject({ scriptLangs: ['ts', 'tsx'] })
// More info at https://github.com/vuejs/eslint-config-typescript/#advanced-setup

export default defineConfigWithVueTs(
  {
    name: 'app/files-to-lint',
    files: ['**/*.{ts,mts,tsx,vue}'],
  },

  globalIgnores(['**/dist/**', '**/dist-ssr/**', '**/coverage/**']),

  pluginVue.configs['flat/essential'],
  vueTsConfigs.recommended,
  skipFormatting,
  {
    rules: {
      'vue/multi-word-component-names': 'off',
      'vue/no-v-html': 'error',
    },
  },
  // eslint-disable-next-line @typescript-eslint/no-explicit-any -- flat config types are incompatible
  ...(pluginVueI18n.configs['flat/recommended'] as any[]),
  {
    // Disable i18n linting for YAML files (e.g. .gitlab-ci.yml, route meta blocks)
    files: ['**/*.yaml', '**/*.yml'],
    rules: {
      '@intlify/vue-i18n/no-html-messages': 'off',
      '@intlify/vue-i18n/no-missing-keys': 'off',
      '@intlify/vue-i18n/no-unused-keys': 'off',
    },
  },
  {
    // Exclude non-locale YAML files from jsonc/yaml parsing added by vue-i18n plugin
    ignores: ['**/*.yaml', '**/*.yml', '!src/i18n/**'],
  },
  {
    rules: {
      '@intlify/vue-i18n/no-missing-keys': 'error',
      '@intlify/vue-i18n/no-raw-text': 'warn',
      '@intlify/vue-i18n/no-unused-keys': [
        'warn',
        {
          enableFix: false,
          extensions: ['.js', '.ts', '.vue'],
        },
      ],
      '@intlify/vue-i18n/no-duplicate-keys-in-locale': 'error',
      '@intlify/vue-i18n/no-html-messages': 'warn',
      // Disabled: plugin parser does not support ICU MessageFormat (plural/select syntax)
      // ICU validation is handled by eslint-plugin-i18n-json below
      '@intlify/vue-i18n/valid-message-syntax': 'off',
    },
    settings: {
      'vue-i18n': {
        localeDir: './src/i18n/locales/*.json',
        messageSyntaxVersion: '^11.0.0',
      },
    },
  },
)
