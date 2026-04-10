<template>
  <div
    @click="$emit('click')"
    class="border-primary-lighter-stroke ring-primary/50 ring-offset-bg2 flex h-16 cursor-pointer items-center rounded-sm border bg-white p-2 hover:ring-2"
  >
    <div class="flex min-w-0 items-center gap-2">
      <!-- Company Logo -->
      <div
        class="ring-primary-stroke flex h-10 w-10 flex-shrink-0 items-center overflow-hidden rounded bg-white ring-1"
      >
        <div
          v-if="showFallbackIcon"
          class="bg-sage-100 dark:bg-sage-800 flex h-full w-full items-center justify-center"
        >
          <i class="fas fa-building text-neutral-black-font text-xl"></i>
        </div>
        <img
          v-if="companyDomain && !showFallbackIcon"
          :src="logoUrl"
          :alt="`${name} logo`"
          class="h-full w-full object-contain p-0.5"
          @error="handleImageError"
        />
      </div>

      <!-- Company Name -->
      <div class="min-w-0 flex-1">
        <div class="truncate text-sm font-medium">{{ name }}</div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'

interface Props {
  name: string
  website?: string
}

const props = defineProps<Props>()

defineEmits<{
  click: []
}>()

const showFallbackIcon = ref(false)

// Extract domain from website URL
const companyDomain = computed(() => {
  if (!props.website) return null
  try {
    let domain = props.website.replace(/^https?:\/\//, '').replace(/^www\./, '')
    domain = domain.split('/')[0]
    return domain
  } catch {
    return null
  }
})

// Generate logo URL from logo.dev
const logoUrl = computed(() => {
  if (!companyDomain.value) return ''
  return `https://img.logo.dev/${companyDomain.value}?token=pk_Buf4yyXmRC2HMagyfO0jrg&retina=true`
})

function handleImageError() {
  showFallbackIcon.value = true
}
</script>
