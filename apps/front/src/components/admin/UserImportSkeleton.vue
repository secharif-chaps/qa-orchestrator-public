<template>
  <div class="border-primary-lighter-stroke rounded-card border bg-white p-6" aria-hidden="true">
    <div class="flex flex-col gap-6">
      <!-- Step indicator -->
      <div class="flex items-center gap-3">
        <div v-for="i in 3" :key="i" class="flex items-center gap-3">
          <div class="h-8 w-8 animate-pulse rounded-full bg-gray-200"></div>
          <div class="h-3 w-20 animate-pulse rounded bg-gray-200"></div>
          <div v-if="i < 3" class="h-px w-12 animate-pulse bg-gray-200"></div>
        </div>
      </div>
      <!-- Content area -->
      <div class="flex flex-col gap-4">
        <div class="h-5 w-48 animate-pulse rounded bg-gray-200"></div>
        <div class="h-3 w-full animate-pulse rounded bg-gray-200"></div>
        <div class="h-3 w-3/4 animate-pulse rounded bg-gray-200"></div>
        <div class="mt-2 h-32 w-full animate-pulse rounded-lg bg-gray-200"></div>
      </div>
    </div>
  </div>
</template>
