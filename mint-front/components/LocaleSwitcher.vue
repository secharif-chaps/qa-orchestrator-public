<template>
  <div class="locale-switcher">

    <label for="locale-select" class="block text-sm font-medium text-gray-700 mb-2">Language</label>
    <div class="mt-2 grid grid-cols-1">
      <select id="locale-select" name="locale-select" v-model="currentLocale" @change="changeLocale" class="col-start-1 row-start-1 w-full appearance-none rounded-md bg-bg3 py-1.5 pl-3 pr-8 text-base text-gray-900 outline outline-1 -outline-offset-1 outline-gray-300 focus:outline focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600 sm:text-sm/6">
        <option value="en-US">English</option>
        <option value="fr-FR">Français</option>
      </select>
      <i class="fas fa-chevron-down pointer-events-none col-start-1 row-start-1 mr-2 size-5 self-center justify-self-end text-gray-500 sm:size-4" aria-hidden="true"></i>
    </div>

  </div>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import {
  SelectContent,
  SelectIcon,
  SelectItem,
  SelectItemIndicator,
  SelectItemText,
  SelectPortal,
  SelectRoot,
  SelectTrigger,
  SelectValue,
  SelectViewport,
} from 'reka-ui'

const { locale } = useI18n()
const currentLocale = ref(locale.value)

const STORAGE_KEY = 'user-locale'

const changeLocale = () => {
  locale.value = currentLocale.value
  localStorage.setItem(STORAGE_KEY, currentLocale.value)
}

// Keep the select in sync with the current locale
watch(() => locale.value, (newLocale) => {
  currentLocale.value = newLocale
})
</script>

