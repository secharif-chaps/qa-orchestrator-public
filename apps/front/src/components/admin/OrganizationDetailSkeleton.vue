<template>
  <div class="flex flex-col gap-6" aria-hidden="true">
    <!-- Header: title + toggle navigation -->
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
      <div class="flex flex-col gap-2">
        <div class="h-7 w-48 animate-pulse rounded bg-gray-200"></div>
        <div class="h-4 w-64 animate-pulse rounded bg-gray-200"></div>
      </div>
      <div class="bg-primary-lightest flex items-center gap-1 rounded-full p-1">
        <div v-for="i in 4" :key="i" class="h-8 w-20 animate-pulse rounded-full bg-gray-200"></div>
      </div>
    </div>

    <!-- Basic info card -->
    <div class="border-primary-lighter-stroke rounded-card border bg-white p-6">
      <div class="mb-4 h-6 w-36 animate-pulse rounded bg-gray-200"></div>
      <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
        <div v-for="i in 4" :key="i" class="flex flex-col gap-2">
          <div class="h-3 w-24 animate-pulse rounded bg-gray-200"></div>
          <div
            class="h-4 animate-pulse rounded bg-gray-200"
            :style="{ width: fieldWidths[i % fieldWidths.length] + 'px' }"
          ></div>
        </div>
      </div>
    </div>

    <!-- Module status card -->
    <div class="border-primary-lighter-stroke rounded-card border bg-white p-6">
      <div class="mb-4 h-6 w-40 animate-pulse rounded bg-gray-200"></div>
      <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
        <div
          v-for="i in 3"
          :key="i"
          class="border-primary-lighter-stroke rounded-card flex flex-col gap-3 border p-4"
        >
          <div class="h-4 w-20 animate-pulse rounded bg-gray-200"></div>
          <div class="h-6 w-12 animate-pulse rounded-full bg-gray-200"></div>
        </div>
      </div>
    </div>

    <!-- Feature flags card -->
    <div class="border-primary-lighter-stroke rounded-card border bg-white p-6">
      <div class="mb-2 h-6 w-44 animate-pulse rounded bg-gray-200"></div>
      <div class="mb-4 h-3 w-96 animate-pulse rounded bg-gray-200"></div>
      <div class="flex flex-col gap-4">
        <div
          v-for="i in 2"
          :key="i"
          class="border-primary-lighter-stroke rounded-card flex items-center justify-between border p-4"
        >
          <div class="flex flex-col gap-2">
            <div class="h-4 w-32 animate-pulse rounded bg-gray-200"></div>
            <div class="h-3 w-64 animate-pulse rounded bg-gray-200"></div>
          </div>
          <div class="h-6 w-12 animate-pulse rounded-full bg-gray-200"></div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
const fieldWidths = [180, 240, 160, 200]
</script>
