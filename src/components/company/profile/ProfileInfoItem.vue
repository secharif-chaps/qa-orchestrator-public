<template>
  <div class="border-base-300 flex items-center gap-4 rounded-xl border p-4">
    <div class="bg-base-300 flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-lg">
      <i class="text-secondary text-md" :class="icon"></i>
    </div>
    <div class="min-w-0">
      <p class="text-secondary/70 text-xs">{{ label }}</p>
      <div v-if="loading" class="bg-base-300 h-4 w-20 animate-pulse rounded-full"></div>
      <p v-else-if="value" class="text-secondary text-sm font-medium">
        {{ value }}
      </p>
      <p v-else class="text-secondary/70 text-sm font-medium">{{ placeholder }}</p>
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
