<template>
  <!-- Grid view skeleton -->
  <div
    v-if="viewMode === 'grid'"
    class="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-2 xl:grid-cols-3"
    aria-hidden="true"
  >
    <div
      v-for="i in itemCount"
      :key="i"
      class="border-primary-lighter-stroke flex flex-col gap-3 rounded-sm border bg-white p-5 shadow-sm"
    >
      <!-- Logo + name -->
      <div class="flex items-center gap-3">
        <div
          class="ring-primary-stroke h-10 w-10 shrink-0 animate-pulse rounded-sm bg-gray-200 ring-1"
        ></div>
        <div class="flex flex-1 flex-col gap-1.5">
          <div
            class="h-4 animate-pulse rounded bg-gray-200"
            :style="{ width: titleWidths[i % titleWidths.length] + '%' }"
          ></div>
          <div class="h-3 w-16 animate-pulse rounded-full bg-gray-200"></div>
        </div>
      </div>
      <!-- Meta line -->
      <div class="flex items-center gap-2">
        <div class="h-3 w-24 animate-pulse rounded bg-gray-200"></div>
        <div class="h-3 w-3 animate-pulse rounded-full bg-gray-200"></div>
        <div class="h-3 w-16 animate-pulse rounded bg-gray-200"></div>
      </div>
      <!-- Action row -->
      <div class="mt-1 flex items-center justify-end gap-2">
        <div class="h-7 w-7 animate-pulse rounded-sm bg-gray-200"></div>
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
        <div class="col-span-2 flex justify-end">
          <div class="h-3 w-16 animate-pulse rounded bg-gray-200"></div>
        </div>
      </div>
    </div>
    <!-- Rows -->
    <div class="divide-primary-stroke divide-y">
      <div v-for="i in itemCount" :key="i" class="px-6 py-4">
        <div class="grid grid-cols-12 items-center gap-4">
          <!-- Name + logo -->
          <div class="col-span-4 flex items-center gap-3">
            <div
              class="ring-primary-stroke h-10 w-10 shrink-0 animate-pulse rounded-sm bg-gray-200 ring-1"
            ></div>
            <div
              class="h-4 animate-pulse rounded bg-gray-200"
              :style="{ width: titleWidths[i % titleWidths.length] + '%' }"
            ></div>
          </div>
          <!-- Type -->
          <div class="col-span-2">
            <div class="h-5 w-14 animate-pulse rounded-full bg-gray-200"></div>
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

const titleWidths = [60, 75, 50, 80, 65, 70]
</script>
