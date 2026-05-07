<template>
  <div class="flex flex-col gap-6" aria-hidden="true">
    <!-- Stats grid: 5 cards -->
    <div class="grid grid-cols-2 gap-4 md:grid-cols-3 lg:grid-cols-5">
      <div
        v-for="i in 5"
        :key="i"
        class="border-primary-lighter-stroke flex flex-col gap-3 rounded-sm border bg-white p-4 shadow-sm"
      >
        <div class="flex items-center justify-between">
          <div class="h-3 w-16 animate-pulse rounded bg-gray-200"></div>
          <div class="h-8 w-8 animate-pulse rounded-sm bg-gray-200"></div>
        </div>
        <div class="h-7 w-12 animate-pulse rounded bg-gray-200"></div>
      </div>
    </div>

    <!-- Filters card skeleton -->
    <div class="border-primary-lighter-stroke rounded-sm border bg-white p-4 shadow-sm">
      <div class="flex flex-wrap gap-3">
        <div class="h-9 w-28 animate-pulse rounded-sm bg-gray-200"></div>
        <div class="h-9 w-32 animate-pulse rounded-sm bg-gray-200"></div>
        <div class="h-9 w-36 animate-pulse rounded-sm bg-gray-200"></div>
        <div class="ml-auto h-9 w-24 animate-pulse rounded-sm bg-gray-200"></div>
      </div>
    </div>

    <!-- Table skeleton -->
    <div class="border-primary-lighter-stroke overflow-hidden rounded-sm border bg-white shadow-sm">
      <!-- Table header -->
      <div class="bg-primary-lightest border-primary-lighter-stroke border-b px-4 py-3">
        <div class="grid grid-cols-12 gap-4">
          <div class="col-span-3">
            <div class="h-3 w-20 animate-pulse rounded bg-gray-200"></div>
          </div>
          <div class="col-span-2">
            <div class="h-3 w-16 animate-pulse rounded bg-gray-200"></div>
          </div>
          <div class="col-span-2">
            <div class="h-3 w-14 animate-pulse rounded bg-gray-200"></div>
          </div>
          <div class="col-span-2">
            <div class="h-3 w-18 animate-pulse rounded bg-gray-200"></div>
          </div>
          <div class="col-span-2">
            <div class="h-3 w-16 animate-pulse rounded bg-gray-200"></div>
          </div>
          <div class="col-span-1"></div>
        </div>
      </div>
      <!-- Table rows -->
      <div class="divide-primary-stroke divide-y">
        <div v-for="i in rowCount" :key="i" class="px-4 py-3">
          <div class="grid grid-cols-12 items-center gap-4">
            <div class="col-span-3">
              <div
                class="h-4 animate-pulse rounded bg-gray-200"
                :style="{ width: rowWidths[i % rowWidths.length] + '%' }"
              ></div>
            </div>
            <div class="col-span-2">
              <div class="h-5 w-16 animate-pulse rounded-full bg-gray-200"></div>
            </div>
            <div class="col-span-2">
              <div class="h-3 w-4/5 animate-pulse rounded bg-gray-200"></div>
            </div>
            <div class="col-span-2">
              <div class="h-3 w-3/4 animate-pulse rounded bg-gray-200"></div>
            </div>
            <div class="col-span-2">
              <div class="h-3 w-3/5 animate-pulse rounded bg-gray-200"></div>
            </div>
            <div class="col-span-1 flex justify-end">
              <div class="h-7 w-7 animate-pulse rounded-sm bg-gray-200"></div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
withDefaults(defineProps<{ rowCount?: number }>(), { rowCount: 10 })

const rowWidths = [65, 80, 55, 70, 60, 75, 50, 85]
</script>
