<template>
  <!-- Grid view skeleton -->
  <div
    v-if="viewMode === 'grid'"
    class="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3"
    aria-hidden="true"
  >
    <div
      v-for="i in itemCount"
      :key="i"
      class="border-primary-lighter-stroke flex min-h-[280px] flex-col gap-4 rounded-sm border bg-white p-6 shadow-sm"
    >
      <!-- Icon + title row -->
      <div class="flex items-start gap-3">
        <div class="h-12 w-12 animate-pulse rounded-sm bg-gray-200"></div>
        <div class="flex flex-1 flex-col gap-2">
          <div
            class="h-5 animate-pulse rounded bg-gray-200"
            :style="{
              width: cardTitleWidths[i % cardTitleWidths.length] + '%',
            }"
          ></div>
          <div class="h-3 w-20 animate-pulse rounded bg-gray-200"></div>
        </div>
      </div>
      <!-- Description lines -->
      <div class="flex flex-col gap-2">
        <div class="h-3 w-full animate-pulse rounded bg-gray-200"></div>
        <div class="h-3 w-4/5 animate-pulse rounded bg-gray-200"></div>
      </div>
      <!-- Footer row -->
      <div class="mt-auto flex items-center justify-between">
        <div class="h-5 w-16 animate-pulse rounded-full bg-gray-200"></div>
        <div class="h-7 w-7 animate-pulse rounded-sm bg-gray-200"></div>
      </div>
    </div>
  </div>

  <!-- Table view skeleton -->
  <div
    v-else
    class="border-primary-lighter-stroke overflow-hidden rounded-sm border bg-white shadow-sm"
    aria-hidden="true"
  >
    <!-- Header -->
    <div class="border-primary-lighter-stroke bg-primary-lightest border-b px-6 py-4">
      <div class="grid grid-cols-12 gap-4">
        <div class="col-span-4">
          <div class="h-3 w-16 animate-pulse rounded bg-gray-200"></div>
        </div>
        <div class="col-span-2">
          <div class="h-3 w-12 animate-pulse rounded bg-gray-200"></div>
        </div>
        <div class="col-span-2">
          <div class="h-3 w-14 animate-pulse rounded bg-gray-200"></div>
        </div>
        <div class="col-span-2">
          <div class="h-3 w-14 animate-pulse rounded bg-gray-200"></div>
        </div>
        <div class="col-span-2">
          <div class="ml-auto h-3 w-16 animate-pulse rounded bg-gray-200"></div>
        </div>
      </div>
    </div>
    <!-- Rows -->
    <div class="divide-primary-stroke divide-y">
      <div v-for="i in itemCount" :key="i" class="px-6 py-4">
        <div class="grid grid-cols-12 items-center gap-4">
          <!-- Name + icon -->
          <div class="col-span-4 flex items-center gap-3">
            <div class="h-10 w-10 shrink-0 animate-pulse rounded-sm bg-gray-200"></div>
            <div
              class="h-4 animate-pulse rounded bg-gray-200"
              :style="{
                width: cardTitleWidths[i % cardTitleWidths.length] + '%',
              }"
            ></div>
          </div>
          <!-- Type badge -->
          <div class="col-span-2">
            <div class="h-5 w-16 animate-pulse rounded-full bg-gray-200"></div>
          </div>
          <!-- Date -->
          <div class="col-span-2">
            <div class="h-3 w-24 animate-pulse rounded bg-gray-200"></div>
          </div>
          <!-- Owner -->
          <div class="col-span-2">
            <div class="h-3 w-20 animate-pulse rounded bg-gray-200"></div>
          </div>
          <!-- Actions -->
          <div class="col-span-2 flex justify-end gap-2">
            <div class="h-7 w-7 animate-pulse rounded-sm bg-gray-200"></div>
            <div class="h-7 w-7 animate-pulse rounded-sm bg-gray-200"></div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
withDefaults(
  defineProps<{
    viewMode?: 'grid' | 'table'
    itemCount?: number
  }>(),
  { viewMode: 'grid', itemCount: 6 },
)

const cardTitleWidths = [60, 75, 50, 80, 65, 70]
</script>
