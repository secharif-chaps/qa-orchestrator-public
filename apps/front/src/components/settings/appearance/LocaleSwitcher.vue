<template>
  <div class="flex flex-col gap-4">
    <div
      v-for="localeOption in localeOptions"
      :key="localeOption.value"
      class="border-primary-lighter-stroke hover:border-primary/70 flex cursor-pointer items-center justify-between rounded-lg border p-4 transition-colors"
      :class="{
        'border-primary bg-primary-lightest': currentLocale === localeOption.value,
        'pointer-events-none opacity-50': isLoading,
      }"
      @click="changeLocale(localeOption.value)"
    >
      <div class="flex items-center gap-4">
        <div
          class="flex h-10 w-10 items-center justify-center rounded-lg"
          :class="
            currentLocale === localeOption.value
              ? 'border border-rose-200 bg-rose-100 text-rose-600 dark:bg-rose-900/30 dark:text-rose-400'
              : 'bg-primary-lightest text-neutral-black-font'
          "
        >
          <i
            :class="
              isLoading && localeOption.value === pendingLocale
                ? 'fas fa-spinner fa-spin'
                : localeOption.icon
            "
            class="text-lg"
          ></i>
        </div>
        <div>
          <h3 class="text-sm font-medium">{{ localeOption.label }}</h3>
          <p class="text-neutral-black-font text-sm">{{ localeOption.description }}</p>
        </div>
      </div>
      <div>
        <Switch
          :id="`locale-${localeOption.value}`"
          :model-value="currentLocale === localeOption.value"
          :disabled="isLoading"
          @update:model-value="() => changeLocale(localeOption.value)"
        />
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { loadLocaleMessages } from '@/i18n'
import { toast } from '@/utils/toast'
import { Switch } from '@owlint/feathers-vue'
import { ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'

const { locale, t } = useI18n()
const currentLocale = ref(locale.value)
const isLoading = ref(false)
const pendingLocale = ref<string | null>(null)

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

const changeLocale = async (value: string) => {
  if (value === currentLocale.value || isLoading.value) return

  isLoading.value = true
  pendingLocale.value = value

  try {
    await loadLocaleMessages(value)
    locale.value = value
    currentLocale.value = value
    localStorage.setItem(STORAGE_KEY, value)
  } catch {
    toast.error(t('settings.appearance.language.loadError'))
  } finally {
    isLoading.value = false
    pendingLocale.value = null
  }
}

// Keep the select in sync with the current locale
watch(
  () => locale.value,
  (newLocale) => {
    currentLocale.value = newLocale
  },
)
</script>
