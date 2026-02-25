<template>
  <div class="flex flex-col gap-4">
    <div
      v-for="localeOption in localeOptions"
      :key="localeOption.value"
      class="border-primary-stroke hover:border-primary/70 flex cursor-pointer items-center justify-between rounded-lg border p-4 transition-colors"
      :class="{ 'border-primary bg-base-200': currentLocale === localeOption.value }"
      @click="changeLocale(localeOption.value)"
    >
      <div class="flex items-center gap-4">
        <div
          class="flex h-10 w-10 items-center justify-center rounded-lg"
          :class="
            currentLocale === localeOption.value
              ? 'border border-rose-200 bg-rose-100 text-rose-600 dark:bg-rose-900/30 dark:text-rose-400'
              : 'bg-base-200 text-secondary'
          "
        >
          <i :class="localeOption.icon" class="text-lg"></i>
        </div>
        <div>
          <h3 class="text-sm font-medium">{{ localeOption.label }}</h3>
          <p class="text-secondary text-sm">{{ localeOption.description }}</p>
        </div>
      </div>
      <div>
        <Switch
          :id="`locale-${localeOption.value}`"
          :model-value="currentLocale === localeOption.value"
          @update:model-value="() => changeLocale(localeOption.value)"
        />
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { Switch } from '@owlint/feathers-vue'

const { locale } = useI18n()
const currentLocale = ref(locale.value)

const STORAGE_KEY = 'user-locale'

const localeOptions = [
  {
    value: 'en-US',
    label: 'English',
    description: 'Use English language',
    icon: 'fas fa-language',
  },
  {
    value: 'fr-FR',
    label: 'Français',
    description: 'Utiliser la langue française',
    icon: 'fas fa-language',
  },
]

function changeLocale(value: string) {
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
