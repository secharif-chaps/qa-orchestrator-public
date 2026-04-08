<template>
  <div class="p-xs bg-sage-100 space-y-3xs rounded-md">
    <div class="gap-2xs flex items-center">
      <h5 class="text-base font-semibold">
        {{ service.name }}
      </h5>
      <Source :source />
    </div>
    <p>
      {{ service.description }}
    </p>
  </div>
</template>

<script lang="ts" setup>
import Source from '@/components/company/Source.vue'

defineProps<{
  service: { name: string; description: string }
  source?: string
}>()
</script>
