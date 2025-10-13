<template>
  <div class="locale-switcher">
    <label class="block text-sm font-medium text-secondary mb-2">{{
      $t('settings.language.title')
    }}</label>

    <Select.Root v-model="currentLocale" @update:model-value="changeLocale">
      <Select.Trigger
        class="w-full appearance-none rounded-md bg-base-100 py-1.5 pl-3 pr-8 text-base text-secondary outline outline-1 -outline-offset-1 outline-slate-300 dark:outline-slate-600 focus:outline focus:outline-2 focus:-outline-offset-2 focus:outline-primary sm:text-sm/6"
      >
        <Select.Value />
        <Select.Icon class="ml-2">
          <i class="fas fa-chevron-down text-secondary" aria-hidden="true"></i>
        </Select.Icon>
      </Select.Trigger>

      <Select.Portal>
        <Select.Content
          class="bg-base-100 border border-slate-300 dark:border-slate-600 rounded-md shadow-lg"
        >
          <Select.Viewport class="p-1">
            <Select.Item
              value="en-US"
              class="px-3 py-2 text-sm text-secondary hover:bg-base-200 cursor-pointer rounded"
            >
              <Select.ItemText>English</Select.ItemText>
            </Select.Item>
            <Select.Item
              value="fr-FR"
              class="px-3 py-2 text-sm text-secondary hover:bg-base-200 cursor-pointer rounded"
            >
              <Select.ItemText>Français</Select.ItemText>
            </Select.Item>
          </Select.Viewport>
        </Select.Content>
      </Select.Portal>
    </Select.Root>
  </div>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { Select } from 'reka-ui/namespaced'

const { locale } = useI18n()
const currentLocale = ref(locale.value)

const STORAGE_KEY = 'user-locale'

const changeLocale = (value: string) => {
  currentLocale.value = value
  locale.value = value
  localStorage.setItem(STORAGE_KEY, value)
}

// Keep the select in sync with the current locale
watch(
  () => locale.value,
  (newLocale) => {
    currentLocale.value = newLocale
  },
)
</script>
