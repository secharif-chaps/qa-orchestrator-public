<template>
  <div class="flex flex-col gap-2" aria-hidden="true">
    <div
      v-for="i in itemCount"
      :key="i"
      class="-mx-2 flex items-center gap-1.5 rounded-md px-2 py-1.5"
    >
      <!-- Module icon badge -->
      <div class="flex min-w-0 flex-1 items-center gap-3 pl-1">
        <div class="size-4.5 shrink-0 animate-pulse rounded-xs bg-gray-200"></div>

        <!-- Text content -->
        <div class="flex min-w-0 flex-1 flex-col gap-1.5">
          <div
            class="h-4 animate-pulse rounded bg-gray-200"
            :style="{ width: nameWidths[i % nameWidths.length] + '%' }"
          ></div>
          <div class="h-3 w-32 animate-pulse rounded bg-gray-200"></div>
        </div>
      </div>

      <!-- Badge placeholder -->
      <div class="h-5 w-20 animate-pulse rounded-full bg-gray-200"></div>
    </div>
  </div>
</template>

<script setup lang="ts">
withDefaults(defineProps<{ itemCount?: number }>(), { itemCount: 5 })

const nameWidths = [60, 80, 50, 70, 65]
</script>
