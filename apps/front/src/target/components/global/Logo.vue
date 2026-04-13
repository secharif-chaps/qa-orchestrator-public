<template>
  <div
    v-if="shouldShowFallback"
    class="flex items-center justify-center rounded bg-gray-800 object-contain text-lg font-bold text-white"
    :style="{
      width: `${width}px`,
      height: `${height}px`,
      fontSize: `${Math.min(width, height) * 0.4}px`,
    }"
    role="img"
    :aria-label="alt"
  >
    {{ fallbackInitials }}
  </div>
  <img
    v-else
    :src="logo"
    :alt="alt"
    class="object-contain"
    :width="width"
    :height="height"
    @error="handleImageError"
  />
</template>

<script setup lang="ts">
import { useEndpointResolver } from '@/composables/useEndpointResolver'
import { computed, ref, watchEffect } from 'vue'

const { endpoints } = useEndpointResolver()

interface Props {
  domain?: string
  alt?: string
  name?: string
  width?: number
  height?: number
}

const { domain = '', alt = '', name = undefined, width = 32, height = 32 } = defineProps<Props>()

const shouldShowFallback = ref(false)

const logo = computed(() => {
  return `${endpoints.value.apiUrl}/logo/${domain}`
})

const fallbackInitials = computed(() => {
  if (name) {
    return name
      .split(/\s+|-/)
      .map((word) => {
        // Filter to only alphabetic characters, then take first character
        const letters = word.match(/[A-Za-z]/g)
        return letters?.[0] || ''
      })
      .filter((char) => char !== '')
      .join('')
      .toUpperCase()
      .slice(0, 2)
  }

  if (!domain || domain.trim() === '') {
    return '?'
  }

  return domain
    .split('.')
    .slice(0, -1)
    .map((part) => {
      // Filter to only alphabetic characters, then take first character
      const letters = part.match(/[A-Za-z]/g)
      return letters?.[0] || ''
    })
    .filter((char) => char !== '')
    .join('')
    .toUpperCase()
    .slice(0, 2)
})

// Show fallback immediately if domain is empty
watchEffect(() => {
  shouldShowFallback.value = !domain || domain.trim() === ''
})

const handleImageError = () => {
  shouldShowFallback.value = true
}
</script>
