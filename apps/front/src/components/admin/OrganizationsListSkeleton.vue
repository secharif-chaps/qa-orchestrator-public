<template>
  <div class="flex flex-col gap-3" aria-hidden="true">
    <div
      v-for="i in rowCount"
      :key="i"
      class="bg-primary-lightest border-primary-lighter-stroke rounded-card border p-6"
    >
      <div class="flex items-center justify-between">
        <div class="flex items-center gap-4">
          <div class="h-12 w-12 animate-pulse rounded-full bg-gray-200"></div>
          <div class="flex flex-col gap-2">
            <div
              class="h-4 animate-pulse rounded bg-gray-200"
              :style="{ width: nameWidths[i % nameWidths.length] + 'px' }"
            ></div>
            <div class="h-3 w-48 animate-pulse rounded bg-gray-200"></div>
            <div class="h-3 w-32 animate-pulse rounded bg-gray-200"></div>
          </div>
        </div>
        <div class="flex items-center gap-3">
          <div class="h-6 w-20 animate-pulse rounded-full bg-gray-200"></div>
          <div class="h-4 w-4 animate-pulse rounded bg-gray-200"></div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
withDefaults(defineProps<{ rowCount?: number }>(), { rowCount: 5 })

const nameWidths = [140, 180, 120, 160, 150]
</script>
