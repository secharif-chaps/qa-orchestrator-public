<template>
  <div
    class="relative overflow-hidden rounded-block bg-bg2 border border-border-2 p-6"
    role="alert"
    :aria-labelledby="title ? 'chapse-alert-title' : undefined"
  >
    <div class="flex items-center gap-8">
      <!-- Chapse Image (LEFT) -->
      <div class="flex-shrink-0 ml-6 flex flex-col items-center">
        <img
          :src="imageSource"
          :alt="imageAlt"
          class="animate-float h-32 w-auto object-contain"
          loading="lazy"
        />
        <img
          :src="shadow"
          :alt="imageAlt"
          class="h-3 w-auto object-contain relative -bottom-2 -left-0.5"
          loading="lazy"
        />
      </div>
      <!-- Content Section (RIGHT) -->
      <div class="flex-1 min-w-0">
        <!-- Title -->
        <h3
          id="chapse-alert-title"
          class="text-lg font-semibold text-black dark:text-white mb-3 flex items-center gap-2"
        >
          <i class="fa-solid fa-wand-sparkles"></i>
          <span>
            {{ title || $t('profile.sections.csr.insights.title') }}
          </span>
        </h3>

        <!-- Default slot for content -->
        <div v-if="$slots.default" class="text-sm text-sage-700 dark:text-sage-300 leading-relaxed">
          <slot />
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import shadow from '@/assets/chapse/shadow.svg'

type ChapseVariant = 'default' | 'head' | 'mage'

interface Props {
  /** Determines which Chapse character image to display */
  variant?: ChapseVariant
  /** Title text for the alert */
  title?: string
}

const props = withDefaults(defineProps<Props>(), {
  variant: 'default',
})

// Map variant to image source
const imageSource = computed(() => {
  const images = {
    default: new URL('@/assets/chapse/default.svg', import.meta.url).href,
    head: new URL('@/assets/chapse/head.svg', import.meta.url).href,
    mage: new URL('@/assets/chapse/mage.svg', import.meta.url).href,
  }
  return images[props.variant]
})

// Accessible alt text based on variant
const imageAlt = computed(() => {
  const altTexts = {
    default: 'Chapse character',
    head: 'Chapse head',
    mage: 'Chapse mage character',
  }
  return altTexts[props.variant]
})
</script>
