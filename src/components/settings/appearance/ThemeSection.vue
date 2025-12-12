<template>
  <div class="bg-base-100 border border-primary-stroke rounded-card">
    <div class="px-6 py-4 border-b border-primary-stroke">
      <h2 class="text-lg font-semibold">{{ $t('settings.appearance.theme.title') }}</h2>
      <p class="text-sm text-secondary mt-1">
        {{ $t('settings.appearance.theme.description') }}
      </p>
    </div>
    <div class="px-6 py-6">
      <div class="flex flex-col gap-4">
        <div
          v-for="themeOption in themeOptions"
          :key="themeOption.value"
          class="flex items-center justify-between p-4 border border-primary-stroke rounded-lg hover:border-primary/70 transition-colors cursor-pointer"
          :class="{ 'border-primary bg-primary-light/30': currentTheme === themeOption.value }"
          @click="handleThemeChange(themeOption.value)"
        >
          <div class="flex items-center gap-4">
            <div
              class="w-10 h-10 rounded-lg flex items-center justify-center"
              :class="
                currentTheme === themeOption.value
                  ? 'bg-rose-100 text-rose-600 dark:bg-rose-900/30 dark:text-rose-400'
                  : 'bg-base-200 text-secondary'
              "
            >
              <i :class="themeOption.icon" class="text-lg"></i>
            </div>
            <div>
              <h3 class="text-sm font-medium">
                {{ $t(`settings.appearance.theme.options.${themeOption.value}.title`) }}
              </h3>
              <p class="text-sm text-secondary">
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
