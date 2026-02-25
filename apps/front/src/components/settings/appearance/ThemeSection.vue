<template>
  <div class="bg-base-100 border-primary-stroke rounded-card border">
    <div class="border-primary-stroke border-b px-6 py-4">
      <h2 class="text-lg font-semibold">{{ $t('settings.appearance.theme.title') }}</h2>
      <p class="text-secondary mt-1 text-sm">
        {{ $t('settings.appearance.theme.description') }}
      </p>
    </div>
    <div class="px-6 py-6">
      <div class="flex flex-col gap-4">
        <div
          v-for="themeOption in themeOptions"
          :key="themeOption.value"
          class="border-primary-stroke hover:border-primary/70 flex cursor-pointer items-center justify-between rounded-lg border p-4 transition-colors"
          :class="{ 'border-primary bg-base-200': currentTheme === themeOption.value }"
          @click="handleThemeChange(themeOption.value)"
        >
          <div class="flex items-center gap-4">
            <div
              class="flex h-10 w-10 items-center justify-center rounded-lg"
              :class="
                currentTheme === themeOption.value
                  ? 'border border-rose-200 bg-rose-100 text-rose-600 dark:bg-rose-900/30 dark:text-rose-400'
                  : 'bg-base-200 text-secondary'
              "
            >
              <i :class="themeOption.icon" class="text-lg"></i>
            </div>
            <div>
              <h3 class="text-sm font-medium">
                {{ $t(`settings.appearance.theme.options.${themeOption.value}.title`) }}
              </h3>
              <p class="text-secondary text-sm">
                {{ $t(`settings.appearance.theme.options.${themeOption.value}.description`) }}
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

function handleThemeChange(themeValue: string) {
  emit('themeChange', themeValue)
}
</script>
