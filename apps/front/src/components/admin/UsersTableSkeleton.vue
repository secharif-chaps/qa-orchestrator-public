<template>
  <div
    class="border-primary-lighter-stroke overflow-hidden rounded-sm border bg-white shadow-sm"
    aria-hidden="true"
  >
    <!-- Table header -->
    <div class="bg-primary-lightest border-primary-lighter-stroke border-b px-4 py-3">
      <div class="grid grid-cols-12 gap-4">
        <div class="col-span-2">
          <div class="h-3 w-20 animate-pulse rounded bg-gray-200"></div>
        </div>
        <div class="col-span-3">
          <div class="h-3 w-16 animate-pulse rounded bg-gray-200"></div>
        </div>
        <div class="col-span-2">
          <div class="h-3 w-14 animate-pulse rounded bg-gray-200"></div>
        </div>
        <div class="col-span-2">
          <div class="h-3 w-20 animate-pulse rounded bg-gray-200"></div>
        </div>
        <div class="col-span-2">
          <div class="h-3 w-24 animate-pulse rounded bg-gray-200"></div>
        </div>
        <div class="col-span-1">
          <div class="h-3 w-12 animate-pulse rounded bg-gray-200"></div>
        </div>
      </div>
    </div>

    <!-- Table rows -->
    <div class="divide-primary-stroke divide-y">
      <div v-for="i in rowCount" :key="i" class="px-4 py-3">
        <div class="grid grid-cols-12 items-center gap-4">
          <!-- Username -->
          <div class="col-span-2">
            <div
              class="h-4 animate-pulse rounded bg-gray-200"
              :style="{ width: rowWidths[i % rowWidths.length] + '%' }"
            ></div>
          </div>
          <!-- Email -->
          <div class="col-span-3">
            <div class="h-3 w-[85%] animate-pulse rounded bg-gray-200"></div>
          </div>
          <!-- Name -->
          <div class="col-span-2">
            <div class="h-3 w-3/4 animate-pulse rounded bg-gray-200"></div>
          </div>
          <!-- Permission badge -->
          <div class="col-span-2">
            <div class="h-5 w-20 animate-pulse rounded-full bg-gray-200"></div>
          </div>
          <!-- Organization -->
          <div class="col-span-2">
            <div class="h-3 w-3/4 animate-pulse rounded bg-gray-200"></div>
          </div>
          <!-- Status + actions -->
          <div class="col-span-1 flex items-center gap-2">
            <div class="h-5 w-14 animate-pulse rounded-full bg-gray-200"></div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
withDefaults(defineProps<{ rowCount?: number }>(), { rowCount: 10 })

const rowWidths = [65, 80, 55, 70, 60, 75]
</script>
