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
  website?: string
  alt?: string
  name?: string
  width?: number
  height?: number
}

const {
  domain = '',
  website = undefined,
  alt = '',
  name = undefined,
  width = 32,
  height = 32,
} = defineProps<Props>()

const effectiveDomain = computed(() => {
  if (domain) return domain
  if (!website?.trim()) return ''
  return website
    .replace(/^https?:\/\//, '')
    .replace(/^www\./, '')
    .split('/')[0]
})

const shouldShowFallback = ref(false)

const logo = computed(() => {
  return `${endpoints.value.apiUrl}/logo/${effectiveDomain.value}`
})

const fallbackInitials = computed(() => {
  if (name) {
    return name
      .split(/\s+|-/)
      .map((word) => {
        const letters = word.match(/[A-Za-z]/g)
        return letters?.[0] || ''
      })
      .filter((char) => char !== '')
      .join('')
      .toUpperCase()
      .slice(0, 2)
  }

  if (!effectiveDomain.value) {
    return '?'
  }

  return effectiveDomain.value
    .split('.')
    .slice(0, -1)
    .map((part) => {
      const letters = part.match(/[A-Za-z]/g)
      return letters?.[0] || ''
    })
    .filter((char) => char !== '')
    .join('')
    .toUpperCase()
    .slice(0, 2)
})

watchEffect(() => {
  shouldShowFallback.value = !effectiveDomain.value
})

const handleImageError = () => {
  shouldShowFallback.value = true
}
</script>
