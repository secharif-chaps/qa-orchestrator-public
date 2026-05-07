<template>
  <div class="flex flex-col gap-2" aria-hidden="true">
    <div
      v-for="i in itemCount"
      :key="i"
      class="-mx-2 flex items-center gap-2 rounded-md px-2 py-1.5"
    >
      <!-- Avatar circle -->
      <div class="h-8 w-8 shrink-0 animate-pulse rounded-full bg-gray-200"></div>

      <!-- Text content -->
      <div class="flex min-w-0 flex-1 flex-col gap-1.5">
        <div
          class="h-4 animate-pulse rounded bg-gray-200"
          :style="{ width: nameWidths[i % nameWidths.length] + '%' }"
        ></div>
        <div class="h-3 w-[55%] animate-pulse rounded bg-gray-200"></div>
        <div class="h-3 w-20 animate-pulse rounded bg-gray-200"></div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
withDefaults(defineProps<{ itemCount?: number }>(), { itemCount: 5 })

const nameWidths = [75, 60, 85, 50, 70]
</script>
