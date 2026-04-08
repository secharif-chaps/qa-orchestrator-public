<template>
  <div class="bg-absolute-white gap-md p-xs flex items-center rounded-lg">
    <Icon :icon class="text-lg" />
    <div class="text-neutral-black-font min-w-0">
      <p class="font-bold">{{ label }}</p>
      <div v-if="loading" class="bg-grey-300 h-4 w-20 animate-pulse rounded-full"></div>
      <p v-else-if="value" class="text-sm">
        {{ value }}
      </p>
      <p v-else class="text-sm">{{ placeholder }}</p>
    </div>
  </div>
</template>

<script setup lang="ts">
import { Icon } from '@owlint/feathers-vue'

interface Props {
  icon: string
  label: string
  value?: string | null
  loading?: boolean
  placeholder?: string
}

const { loading = false, placeholder = 'Unknown' } = defineProps<Props>()
</script>
