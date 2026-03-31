<template>
  <div class="bg-base-100 border-primary-stroke rounded-lg border">
    <div class="border-primary-stroke border-b px-6 py-4">
      <h2 class="text-lg font-semibold">{{ $t('settings.appearance.accent.title') }}</h2>
      <p class="text-secondary mt-1 text-sm">
        {{ $t('settings.appearance.accent.description') }}
      </p>
    </div>
    <div class="px-6 py-6">
      <div class="space-y-6">
        <!-- Color Picker Grid -->
        <div class="grid grid-cols-3 gap-4 sm:grid-cols-6 lg:grid-cols-7">
          <div
            v-for="colorOption in accentColors"
            :key="colorOption.name"
            class="group border-primary-stroke relative flex flex-col items-center justify-center rounded-lg border p-4"
          >
            <!-- Color Circle Button -->
            <button
              @click="handleAccentChange(colorOption.name)"
              class="focus:ring-opacity-50 relative h-12 w-12 transform rounded-full transition-all duration-200 group-hover:scale-110 group-hover:shadow-lg focus:ring-4 focus:outline-none"
              :class="[
                colorOption.bgClass,
                colorOption.focusRingClass,
                currentAccent === colorOption.name
                  ? 'ring-opacity-70 scale-105 shadow-lg ring-4'
                  : 'shadow-md hover:shadow-lg',
              ]"
              :title="colorOption.label"
            >
              <!-- Selected Indicator -->
              <div
                v-if="currentAccent === colorOption.name"
                class="absolute inset-0 flex items-center justify-center"
              >
                <i class="fas fa-check text-lg text-white drop-shadow-lg"></i>
              </div>

              <!-- Gradient Overlay for Premium Feel -->
              <div
                class="absolute inset-0 rounded-full bg-gradient-to-br from-white/20 to-transparent opacity-50"
              ></div>
            </button>

            <!-- Color Name Label -->
            <div class="mt-2 text-center">
              <span
                class="text-secondary group-hover:text-secondary text-xs font-medium transition-colors"
              >
                {{ colorOption.label }}
              </span>
            </div>
          </div>
        </div>

        <!-- Current Selection Preview -->
        <div
          class="border-primary-stroke rounded-lg border bg-gradient-to-br from-slate-50 to-slate-100 p-4 dark:from-slate-800 dark:to-slate-900"
        >
          <div class="flex items-center justify-between">
            <div class="flex items-center gap-4">
              <div class="h-8 w-8 rounded-full shadow-md" :class="getCurrentAccentBgClass()"></div>
              <div>
                <h4 class="text-sm font-medium">
                  {{ $t('settings.appearance.accent.currentColor') }}
                </h4>
                <p class="text-secondary text-xs">
                  {{ getCurrentAccentLabel() }}
                </p>
              </div>
            </div>
            <div class="flex items-center gap-2">
              <Tag
                :label="$t('settings.appearance.accent.active')"
                variant="primary"
                class="animate-pulse"
              />
            </div>
          </div>
        </div>

        <!-- Color Customization Info -->
        <div class="bg-primary/5 border-primary/20 rounded-lg border p-4">
          <div class="flex items-start gap-3">
            <div class="flex-shrink-0">
              <i class="fas fa-palette text-secondary"></i>
            </div>
            <div>
              <h4 class="text-secondary text-sm font-medium">
                {{ $t('settings.appearance.accent.personalizeTitle') }}
              </h4>
              <p class="text-secondary mt-1 text-xs">
                {{
                  $t(
                    'settings.appearance.accent.personalizeDescription',
                    'Your accent color affects buttons, links, highlights, and interactive elements throughout the application.',
                  )
                }}
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
import Tag from '@/components/ui/Tag.vue'
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
