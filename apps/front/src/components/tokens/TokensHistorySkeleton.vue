<template>
  <div class="mx-auto flex max-w-5xl flex-col gap-6" aria-hidden="true">
    <!-- Total credits card -->
    <div class="border-primary-lighter-stroke rounded-lg border bg-white p-6">
      <div class="flex items-center justify-between">
        <div class="flex flex-col gap-2">
          <div class="h-3 w-24 animate-pulse rounded bg-gray-200"></div>
          <div class="h-8 w-32 animate-pulse rounded bg-gray-200"></div>
        </div>
        <div class="h-7 w-20 animate-pulse rounded-full bg-gray-200"></div>
      </div>
    </div>

    <!-- Table skeleton -->
    <div class="border-primary-lighter-stroke overflow-hidden rounded-md border bg-white">
      <!-- Table header -->
      <div class="bg-primary-lightest px-4 py-3">
        <div class="grid grid-cols-12 gap-4">
          <div class="col-span-2">
            <div class="h-3 w-12 animate-pulse rounded bg-gray-200"></div>
          </div>
          <div class="col-span-2">
            <div class="h-3 w-10 animate-pulse rounded bg-gray-200"></div>
          </div>
          <div class="col-span-2">
            <div class="h-3 w-16 animate-pulse rounded bg-gray-200"></div>
          </div>
          <div class="col-span-3">
            <div class="h-3 w-20 animate-pulse rounded bg-gray-200"></div>
          </div>
          <div class="col-span-3 flex justify-end">
            <div class="h-3 w-14 animate-pulse rounded bg-gray-200"></div>
          </div>
        </div>
      </div>
      <!-- Rows -->
      <div class="divide-primary-stroke divide-y">
        <div v-for="i in rowCount" :key="i" class="px-4 py-3">
          <div class="grid grid-cols-12 items-center gap-4">
            <div class="col-span-2">
              <div class="h-3 w-20 animate-pulse rounded bg-gray-200"></div>
            </div>
            <div class="col-span-2">
              <div class="h-5 w-16 animate-pulse rounded-full bg-gray-200"></div>
            </div>
            <div class="col-span-2">
              <div class="h-5 w-18 animate-pulse rounded-full bg-gray-200"></div>
            </div>
            <div class="col-span-3">
              <div
                class="h-3 animate-pulse rounded bg-gray-200"
                :style="{ width: rowWidths[i % rowWidths.length] + '%' }"
              ></div>
            </div>
            <div class="col-span-3 flex justify-end">
              <div class="h-4 w-12 animate-pulse rounded bg-gray-200"></div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
withDefaults(defineProps<{ rowCount?: number }>(), { rowCount: 8 })

const rowWidths = [60, 80, 50, 70, 65, 75, 55, 85]
</script>
