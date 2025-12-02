<template>
  <div
    @click="$emit('click')"
    class="bg-base-100 rounded-md p-2 border border-primary-stroke h-16 flex items-center hover:ring-2 ring-primary/50 ring-offset-bg2 cursor-pointer"
  >
    <div class="flex items-center gap-2 min-w-0">
      <!-- Company Logo -->
      <div
        class="w-10 h-10 rounded bg-white ring-1 ring-primary-stroke overflow-hidden flex items-center flex-shrink-0"
      >
        <div
          v-if="showFallbackIcon"
          class="w-full h-full flex items-center justify-center bg-sage-100 dark:bg-sage-800"
        >
          <i class="fas fa-building text-secondary text-xl"></i>
        </div>
        <img
          v-if="companyDomain && !showFallbackIcon"
          :src="logoUrl"
          :alt="`${name} logo`"
          class="w-full h-full object-contain p-0.5"
          @error="handleImageError"
        />
      </div>

      <!-- Company Name -->
      <div class="flex-1 min-w-0">
        <div class="text-sm font-medium truncate">{{ name }}</div>
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
