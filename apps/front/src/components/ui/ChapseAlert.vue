<template>
  <Alert :title="title || $t('screen.profile.sections.insights.title')" :icon="icon">
    <template #image>
      <div class="ml-6 flex shrink-0 flex-col items-center">
        <img
          :src="imageSource"
          :alt="imageAlt"
          class="animate-float h-32 w-auto object-contain"
          loading="lazy"
        />
        <img
          :src="shadow"
          :alt="imageAlt"
          class="relative -bottom-2 -left-0.5 h-3 w-auto object-contain"
          loading="lazy"
        />
      </div>
    </template>
    <template v-if="$slots.aside" #aside>
      <slot name="aside" />
    </template>
    <template v-if="$slots.default" #default>
      <slot />
    </template>
  </Alert>
</template>

<script setup lang="ts">
import shadow from '@/assets/chapse/shadow.svg'
import Alert from '@/components/ui/Alert.vue'
import { computed } from 'vue'

type ChapseVariant = 'default' | 'head' | 'mage'

interface Props {
  /** Determines which Chapse character image to display */
  variant?: ChapseVariant
  /** Title text for the alert */
  title?: string
  /** Icon class for the title */
  icon?: string
}

const { variant = 'default', icon = 'fas fa-wand-sparkles' } = defineProps<Props>()

const imageSource = computed(() => {
  const images = {
    default: new URL('@/assets/chapse/default.svg', import.meta.url).href,
    head: new URL('@/assets/chapse/head.svg', import.meta.url).href,
    mage: new URL('@/assets/chapse/mage.svg', import.meta.url).href,
  }
  return images[variant]
})

const imageAlt = computed(() => {
  const altTexts = {
    default: 'Chapse character',
    head: 'Chapse head',
    mage: 'Chapse mage character',
  }
  return altTexts[variant]
})
</script>
