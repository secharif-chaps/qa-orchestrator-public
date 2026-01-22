<template>
  <div class="flex items-center p-4 gap-4 border border-base-300 rounded-xl">
    <div
      class="w-8 h-8 rounded-lg bg-base-300 flex items-center justify-center flex-shrink-0"
    >
      <i class="text-secondary text-md" :class="icon"></i>
    </div>
    <div class="min-w-0">
      <p class="text-xs text-secondary/70">{{ label }}</p>
      <div v-if="loading" class="bg-base-300 rounded-full h-4 w-20 animate-pulse"></div>
      <p v-else-if="value" class="text-sm font-medium text-secondary">
        {{ value }}
      </p>
      <p v-else class="text-sm font-medium text-secondary/70">{{ placeholder }}</p>
    </div>
  </div>
</template>

<script setup lang="ts">
interface Props {
  icon: string
  label: string
  value?: string | null
  loading?: boolean
  placeholder?: string
}

withDefaults(defineProps<Props>(), {
  loading: false,
  placeholder: 'Unknown',
})
</script>
