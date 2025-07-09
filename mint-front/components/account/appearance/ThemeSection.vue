<template>
  <div class="bg-bg1 border border-border-2 rounded-lg">
    <div class="px-6 py-4 border-b border-border-2">
      <h2 class="text-lg font-semibold text-secondary">{{ $t('account.appearance.theme.title') }}</h2>
      <p class="text-sm text-secondary mt-1">{{ $t('account.appearance.theme.description') }}</p>
    </div>
    <div class="px-6 py-6">
      <div class="space-y-4">
        <div 
          v-for="themeOption in themeOptions" 
          :key="themeOption.value" 
          class="flex items-center justify-between p-4 border border-border-2 rounded-lg hover:border-primary/70 transition-colors"
        >
          <div class="flex items-center space-x-4">
            <div class="flex-shrink-0">
              <i :class="themeOption.icon" class="text-lg text-secondary"></i>
            </div>
            <div>
              <h3 class="text-sm font-medium text-secondary">
                {{ $t(`account.appearance.theme.options.${themeOption.value}.title`) }}
              </h3>
              <p class="text-sm text-secondary">
                {{ $t(`account.appearance.theme.options.${themeOption.value}.description`) }}
              </p>
            </div>
          </div>
          <div>
            <Switch.Root 
              :model-value="currentTheme === themeOption.value"
              @update:model-value="(checked) => checked && handleThemeChange(themeOption.value)"
              class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2"
              :class="currentTheme === themeOption.value ? 'bg-primary' : 'bg-slate-200 dark:bg-slate-600'"
            >
              <Switch.Thumb 
                class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform"
                :class="currentTheme === themeOption.value ? 'translate-x-6' : 'translate-x-1'" 
              />
            </Switch.Root>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { Switch } from 'reka-ui/namespaced'

interface Props {
  currentTheme: string
}

const props = defineProps<Props>()

const emit = defineEmits<{
  themeChange: [theme: string]
}>()

const themeOptions = [
  { value: 'light', icon: 'fas fa-sun' },
  { value: 'dark', icon: 'fas fa-moon' },
  { value: 'system', icon: 'fas fa-desktop' }
]

const handleThemeChange = (themeValue: string) => {
  emit('themeChange', themeValue)
}
</script>