<template>
  <div class="space-y-6">
    <!-- Theme Settings -->
    <div class="bg-white dark:bg-slate-800 shadow rounded-lg">
      <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
        <h2 class="text-lg font-semibold text-secondary">{{ $t('account.appearance.theme.title') }}</h2>
        <p class="text-sm text-secondary mt-1">{{ $t('account.appearance.theme.description') }}</p>
      </div>
      <div class="px-6 py-6">
        <div class="space-y-4">
          <div v-for="themeOption in themeOptions" :key="themeOption.value" class="flex items-center justify-between p-4 border border-slate-200 dark:border-slate-700 rounded-lg hover:border-slate-300 dark:hover:border-slate-600 transition-colors">
            <div class="flex items-center space-x-4">
              <div class="flex-shrink-0">
                <i :class="themeOption.icon" class="text-lg text-secondary"></i>
              </div>
              <div>
                <h3 class="text-sm font-medium text-secondary">{{ $t(`account.appearance.theme.options.${themeOption.value}.title`) }}</h3>
                <p class="text-sm text-secondary">{{ $t(`account.appearance.theme.options.${themeOption.value}.description`) }}</p>
              </div>
            </div>
            <div>
              <Switch.Root 
                :model-value="currentTheme === themeOption.value"
                @update:model-value="(checked) => checked && handleThemeChange(themeOption.value)"
                class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2"
                :class="currentTheme === themeOption.value ? 'bg-primary' : 'bg-slate-200 dark:bg-slate-600'"
              >
                <Switch.Thumb class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform"
                  :class="currentTheme === themeOption.value ? 'translate-x-6' : 'translate-x-1'" />
              </Switch.Root>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Language Settings -->
    <div class="bg-white dark:bg-slate-800 shadow rounded-lg">
      <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
        <h2 class="text-lg font-semibold text-secondary">{{ $t('account.appearance.language.title') }}</h2>
        <p class="text-sm text-secondary mt-1">{{ $t('account.appearance.language.description') }}</p>
      </div>
      <div class="px-6 py-6">
        <LocaleSwitcher />
      </div>
    </div>

    <!-- Layout Preferences -->
    <div class="bg-white dark:bg-slate-800 shadow rounded-lg">
      <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
        <h2 class="text-lg font-semibold">{{ $t('account.appearance.layout.title') }}</h2>
        <p class="text-sm text-secondary mt-1">{{ $t('account.appearance.layout.description') }}</p>
      </div>
      <div class="px-6 py-6">
        <div class="space-y-4">
          <!-- Compact Mode -->
          <div class="flex items-center justify-between">
            <div>
              <h3 class="text-sm font-medium">{{ $t('account.appearance.layout.compact.title') }}</h3>
              <p class="text-sm text-secondary">{{ $t('account.appearance.layout.compact.description') }}</p>
            </div>
            <Switch.Root 
              v-model:checked="compactMode"
              class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2"
              :class="compactMode ? 'bg-primary' : 'bg-slate-200 dark:bg-slate-600'"
            >
              <Switch.Thumb class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform"
                :class="compactMode ? 'translate-x-6' : 'translate-x-1'" />
            </Switch.Root>
          </div>

          <!-- Reduced Motion -->
          <div class="flex items-center justify-between">
            <div>
              <h3 class="text-sm font-medium">{{ $t('account.appearance.layout.reducedMotion.title') }}</h3>
              <p class="text-sm text-secondary">{{ $t('account.appearance.layout.reducedMotion.description') }}</p>
            </div>
            <Switch.Root 
              v-model:checked="reducedMotion"
              class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2"
              :class="reducedMotion ? 'bg-primary' : 'bg-slate-200 dark:bg-slate-600'"
            >
              <Switch.Thumb class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform"
                :class="reducedMotion ? 'translate-x-6' : 'translate-x-1'" />
            </Switch.Root>
          </div>
        </div>
      </div>
    </div>

    <!-- Theme Preview -->
    <div class="bg-white dark:bg-slate-800 shadow rounded-lg">
      <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
        <h2 class="text-lg font-semibold">{{ $t('account.appearance.preview.title') }}</h2>
        <p class="text-sm text-secondary mt-1">{{ $t('account.appearance.preview.description') }}</p>
      </div>
      <div class="px-6 py-6">
        <!-- Preview Container -->
        <div class="border border-slate-200 dark:border-slate-700 rounded-lg p-6 bg-slate-50 dark:bg-slate-900">
          <!-- Preview Header -->
          <div class="mb-6">
            <h3 class="text-lg font-semibold mb-2">
              {{ $t('account.appearance.preview.sample') }}
            </h3>
            <p class="text-sm text-secondary">
              Experience how your interface looks with the current theme settings.
            </p>
          </div>

          <!-- Sample Card -->
          <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg p-4 mb-6">
            <div class="flex items-start justify-between mb-4">
              <div>
                <h4 class="font-medium">Sample Card Title</h4>
                <p class="text-sm text-secondary mt-1">This card demonstrates the current theme styling</p>
              </div>
              <div class="flex gap-2">
                <OBadge color="green" :text="$t('account.appearance.preview.tag1')" />
                <OBadge color="blue" :text="$t('account.appearance.preview.tag2')" />
              </div>
            </div>
            
            <!-- Sample Form Elements -->
            <div class="space-y-4">
              <!-- Input Field -->
              <div>
                <label class="block text-sm font-medium mb-2">
                  Sample Input Field
                </label>
                <input 
                  v-model="previewInputValue"
                  type="text" 
                  placeholder="Type something here..."
                  class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md bg-bg1 text-secondary placeholder-slate-400 dark:placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"
                />
              </div>

              <!-- Toggle Switch -->
              <div class="flex items-center justify-between">
                <div>
                  <label class="text-sm font-medium">
                    Sample Toggle
                  </label>
                  <p class="text-sm text-secondary">
                    This toggle demonstrates switch styling
                  </p>
                </div>
                <Switch.Root 
                  v-model:checked="previewToggleValue"
                  class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2"
                  :class="previewToggleValue ? 'bg-primary' : 'bg-slate-200 dark:bg-slate-600'"
                >
                  <Switch.Thumb class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform"
                    :class="previewToggleValue ? 'translate-x-6' : 'translate-x-1'" />
                </Switch.Root>
              </div>

              <!-- Buttons -->
              <div class="flex flex-wrap gap-3">
                <OButton 
                  label="Primary Button"
                  type="primary"
                  color="primary"
                  icon="fas fa-star"
                />
                <OButton 
                  label="Secondary Button"
                  type="secondary"
                  color="slate"
                  icon="fas fa-cog"
                />
                <OButton 
                  label="Danger Button"
                  type="secondary"
                  color="red"
                  icon="fas fa-trash"
                />
              </div>

              <!-- Status Indicators -->
              <div class="flex flex-wrap gap-2">
                <div class="flex items-center gap-2">
                  <div class="h-2 w-2 bg-green-500 rounded-full"></div>
                  <span class="text-sm text-secondary">Active</span>
                </div>
                <div class="flex items-center gap-2">
                  <div class="h-2 w-2 bg-yellow-500 rounded-full"></div>
                  <span class="text-sm text-secondary">Pending</span>
                </div>
                <div class="flex items-center gap-2">
                  <div class="h-2 w-2 bg-red-500 rounded-full"></div>
                  <span class="text-sm text-secondary">Error</span>
                </div>
              </div>
            </div>
          </div>

          <!-- Sample List -->
          <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg overflow-hidden">
            <div class="px-4 py-3 border-b border-slate-200 dark:border-slate-700">
              <h4 class="text-sm font-medium">Sample List Items</h4>
            </div>
            <div class="divide-y divide-slate-200 dark:divide-slate-700">
              <div v-for="(item, index) in previewItems" :key="index" 
                   class="px-4 py-3 hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors">
                <div class="flex items-center justify-between">
                  <div class="flex items-center gap-3">
                    <div class="h-8 w-8 bg-primary/10 rounded-full flex items-center justify-center">
                      <i :class="item.icon" class="text-primary text-sm"></i>
                    </div>
                    <div>
                      <p class="text-sm font-medium">{{ item.title }}</p>
                      <p class="text-xs text-secondary">{{ item.description }}</p>
                    </div>
                  </div>
                  <div class="text-xs text-secondary">{{ item.time }}</div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { Switch } from 'reka-ui/namespaced'
import { OBadge, OButton } from '@owlint/feathers-vue'

const { theme: currentTheme, setTheme } = useTheme()

const themeOptions = [
  { value: 'light', icon: 'fas fa-sun' },
  { value: 'dark', icon: 'fas fa-moon' },
  { value: 'system', icon: 'fas fa-desktop' }
]

// Layout preferences with localStorage persistence
const compactMode = ref(false)
const reducedMotion = ref(false)

// Preview component data
const previewInputValue = ref('Sample text input')
const previewToggleValue = ref(true)
const previewItems = ref([
  {
    title: 'New Message Received',
    description: 'You have a new message from John Doe',
    icon: 'fas fa-envelope',
    time: '2 min ago'
  },
  {
    title: 'System Update',
    description: 'Application updated to version 2.1.0',
    icon: 'fas fa-download',
    time: '1 hour ago'
  },
  {
    title: 'Profile Completed',
    description: 'Your profile setup is now complete',
    icon: 'fas fa-check-circle',
    time: '3 hours ago'
  }
])

const handleThemeChange = (themeValue: string) => {
  console.log('Theme change requested:', themeValue)
  setTheme(themeValue as 'light' | 'dark' | 'system')
  console.log('New theme set:', currentTheme.value)
}

// Load layout preferences from localStorage
onMounted(() => {
  if (typeof localStorage !== 'undefined') {
    compactMode.value = localStorage.getItem('layout-compact') === 'true'
    reducedMotion.value = localStorage.getItem('layout-reduced-motion') === 'true'
  }
})

// Watch and persist layout preferences
watch(compactMode, (value) => {
  if (typeof localStorage !== 'undefined') {
    localStorage.setItem('layout-compact', String(value))
  }
  // Apply compact mode CSS class to body
  if (typeof document !== 'undefined') {
    document.body.classList.toggle('compact-mode', value)
  }
})

watch(reducedMotion, (value) => {
  if (typeof localStorage !== 'undefined') {
    localStorage.setItem('layout-reduced-motion', String(value))
  }
  // Apply reduced motion CSS class to body
  if (typeof document !== 'undefined') {
    document.body.classList.toggle('reduced-motion', value)
  }
})

definePageMeta({
  title: 'Appearance Settings'
})
</script>