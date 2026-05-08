<template>
  <div class="border-primary-lighter-stroke rounded-card border bg-white">
    <div class="border-primary-lighter-stroke border-b px-6 py-4">
      <h2 class="text-lg font-semibold">{{ $t('settings.appearance.theme.title') }}</h2>
      <p class="text-neutral-black-font mt-1 text-sm">
        {{ $t('settings.appearance.theme.description') }}
      </p>
    </div>
    <div class="px-6 py-6">
      <div class="flex flex-col gap-4">
        <div
          v-for="themeOption in themeOptions"
          :key="themeOption.value"
          class="border-primary-lighter-stroke hover:border-primary/70 flex cursor-pointer items-center justify-between rounded-sm border p-4 transition-colors"
          :class="{ 'border-primary bg-primary-lightest': currentTheme === themeOption.value }"
          @click="handleThemeChange(themeOption.value)"
        >
          <div class="flex items-center gap-4">
            <div
              class="flex h-10 w-10 items-center justify-center rounded-sm"
              :class="
                currentTheme === themeOption.value
                  ? 'border border-rose-200 bg-rose-100 text-rose-600 dark:bg-rose-900/30 dark:text-rose-400'
                  : 'bg-primary-lightest text-neutral-black-font'
              "
            >
              <i :class="themeOption.icon" class="text-lg"></i>
            </div>
            <div>
              <h3 class="text-sm font-medium">
                {{ themeOptionLabelMap[themeOption.value]?.title }}
              </h3>
              <p class="text-neutral-black-font text-sm">
                {{ themeOptionLabelMap[themeOption.value]?.description }}
              </p>
            </div>
          </div>
          <div>
            <Switch
              :id="`theme-${themeOption.value}`"
              :model-value="currentTheme === themeOption.value"
              @update:model-value="() => handleThemeChange(themeOption.value)"
            />
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { Switch } from '@owlint/feathers-vue'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()

defineProps<{
  currentTheme: string
}>()

const emit = defineEmits<{
  themeChange: [theme: string]
}>()

const themeOptions = [
  { value: 'light', icon: 'fas fa-sun' },
  { value: 'dark', icon: 'fas fa-moon' },
  { value: 'system', icon: 'fas fa-desktop' },
]

const themeOptionLabelMap = computed<Record<string, { title: string; description: string }>>(
  () => ({
    light: {
      title: t('settings.appearance.theme.options.light.title'),
      description: t('settings.appearance.theme.options.light.description'),
    },
    dark: {
      title: t('settings.appearance.theme.options.dark.title'),
      description: t('settings.appearance.theme.options.dark.description'),
    },
    system: {
      title: t('settings.appearance.theme.options.system.title'),
      description: t('settings.appearance.theme.options.system.description'),
    },
  }),
)

function handleThemeChange(themeValue: string) {
  emit('themeChange', themeValue)
}
</script>
