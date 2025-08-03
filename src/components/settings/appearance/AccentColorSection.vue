<template>
  <div class="bg-bg1 border border-border-2 rounded-lg">
    <div class="px-6 py-4 border-b border-border-2">
      <h2 class="text-lg font-semibold">{{ $t('settings.appearance.accent.title') }}</h2>
      <p class="text-sm text-secondary mt-1">{{ $t('settings.appearance.accent.description') }}</p>
    </div>
    <div class="px-6 py-6">
      <div class="space-y-6">
        <!-- Color Picker Grid -->
        <div class="grid grid-cols-3 sm:grid-cols-6 lg:grid-cols-7 gap-4">
          <div
            v-for="colorOption in accentColors"
            :key="colorOption.name"
            class="group relative border border-border-2 rounded-lg flex flex-col items-center justify-center p-4"
          >
            <!-- Color Circle Button -->
            <button
              @click="handleAccentChange(colorOption.name)"
              class="relative w-12 h-12 rounded-full transition-all duration-200 transform group-hover:scale-110 group-hover:shadow-lg focus:outline-none focus:ring-4 focus:ring-opacity-50"
              :class="[
                colorOption.bgClass,
                colorOption.focusRingClass,
                currentAccent === colorOption.name
                  ? 'ring-4 ring-opacity-70 scale-105 shadow-lg'
                  : 'shadow-md hover:shadow-lg',
              ]"
              :title="colorOption.label"
            >
              <!-- Selected Indicator -->
              <div
                v-if="currentAccent === colorOption.name"
                class="absolute inset-0 flex items-center justify-center"
              >
                <i class="fas fa-check text-white text-lg drop-shadow-lg"></i>
              </div>

              <!-- Gradient Overlay for Premium Feel -->
              <div
                class="absolute inset-0 rounded-full bg-gradient-to-br from-white/20 to-transparent opacity-50"
              ></div>
            </button>

            <!-- Color Name Label -->
            <div class="mt-2 text-center">
              <span
                class="text-xs font-medium text-secondary group-hover:text-primary transition-colors"
              >
                {{ colorOption.label }}
              </span>
            </div>
          </div>
        </div>

        <!-- Current Selection Preview -->
        <div
          class="border border-border-2 rounded-lg p-4 bg-gradient-to-br from-slate-50 to-slate-100 dark:from-slate-800 dark:to-slate-900"
        >
          <div class="flex items-center justify-between">
            <div class="flex items-center gap-4">
              <div class="w-8 h-8 rounded-full shadow-md" :class="getCurrentAccentBgClass()"></div>
              <div>
                <h4 class="text-sm font-medium">Current Accent Color</h4>
                <p class="text-xs text-secondary">{{ getCurrentAccentLabel() }}</p>
              </div>
            </div>
            <div class="flex items-center gap-2">
              <OBadge :text="'Active'" class="animate-pulse" />
            </div>
          </div>
        </div>

        <!-- Color Customization Info -->
        <div class="bg-primary/5 border border-primary/20 rounded-lg p-4">
          <div class="flex items-start gap-3">
            <div class="flex-shrink-0">
              <i class="fas fa-palette text-primary"></i>
            </div>
            <div>
              <h4 class="text-sm font-medium text-primary">Personalize Your Experience</h4>
              <p class="text-xs text-secondary mt-1">
                Your accent color affects buttons, links, highlights, and interactive elements
                throughout the application.
              </p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { useAuth } from '@/composables/useAuth'
import { OBadge } from '@owlint/feathers-vue'
import { computed } from 'vue'

interface Props {
  currentAccent: string
}

const props = defineProps<Props>()

const emit = defineEmits<{
  accentChange: [accent: string]
}>()

const { user } = useAuth()

const accentColors = computed(() => {
  const colors = [
    {
      name: 'emerald',
      label: 'Emerald',
      bgClass: 'bg-emerald-500 ring-emerald-500/20',
      focusRingClass: 'focus:ring-emerald-500',
    },
    {
      name: 'boston',
      label: 'Chaps',
      bgClass: 'bg-boston-500 ring-boston-500/20',
      focusRingClass: 'focus:ring-boston-500',
    },
    {
      name: 'indigo',
      label: 'Indigo',
      bgClass: 'bg-indigo-500 ring-indigo-500/20',
      focusRingClass: 'focus:ring-indigo-500',
    },
    {
      name: 'pink',
      label: 'Pink',
      bgClass: 'bg-pink-500 ring-pink-500/20',
      focusRingClass: 'focus:ring-pink-500',
    },
    {
      name: 'rose',
      label: 'Rose',
      bgClass: 'bg-rose-500 ring-rose-500/20',
      focusRingClass: 'focus:ring-rose-500',
    },
    {
      name: 'orange',
      label: 'Orange',
      bgClass: 'bg-orange-500 ring-orange-500/20',
      focusRingClass: 'focus:ring-orange-500',
    },
  ]
  if (user?.profile.preferred_username === 'nmr') {
    colors.push({
      name: 'sage',
      label: 'Sage',
      bgClass: 'bg-sage-500 ring-sage-500/20',
      focusRingClass: 'focus:ring-sage-500',
    })
  }

  return colors
})

const handleAccentChange = (accentValue: string) => {
  emit('accentChange', accentValue)
}

const getCurrentAccentBgClass = () => {
  const currentColor = accentColors.value.find((color) => color.name === props.currentAccent)
  return currentColor?.bgClass || 'bg-indigo-500'
}

const getCurrentAccentLabel = () => {
  const currentColor = accentColors.value.find((color) => color.name === props.currentAccent)
  return currentColor?.label || 'Indigo'
}
</script>
