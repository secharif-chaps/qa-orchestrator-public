<template>
  <div class="space-y-4">
    <div class="flex h-6 w-1/2 items-center gap-4 rounded-full bg-gray-200" />
    <div v-for="i in 3" :key="i" class="space-y-2">
      <div class="p-xs border-sage-200 space-y-md rounded-lg border">
        <!-- Badge and title section -->
        <div class="gap-2xs flex items-center">
          <!-- Badge skeleton -->
          <div class="size-9 animate-pulse rounded-full bg-gray-200"></div>
          <div class="gap-3xs flex flex-col">
            <!-- Title skeleton -->
            <div class="h-5 w-48 animate-pulse rounded bg-gray-200"></div>
            <!-- Date skeleton -->
            <div class="h-4 w-32 animate-pulse rounded bg-gray-200"></div>
          </div>
        </div>
        <!-- Description skeleton -->
        <div class="space-y-2">
          <div class="h-4 w-full animate-pulse rounded bg-gray-200"></div>
          <div class="h-4 w-5/6 animate-pulse rounded bg-gray-200"></div>
          <div class="h-4 w-4/5 animate-pulse rounded bg-gray-200"></div>
        </div>
        <!-- Tag skeleton -->
        <div class="gap-xs flex items-center">
          <div class="h-6 w-24 animate-pulse rounded bg-gray-200"></div>
        </div>
        <!-- Table skeleton -->
        <div>
          <!-- Table rows -->
          <template v-for="rowIndex in 3" :key="rowIndex">
            <div class="flex items-center gap-4 py-3">
              <div class="h-4 w-1/2 animate-pulse rounded bg-gray-200"></div>
              <div class="h-4 w-24 animate-pulse rounded bg-gray-200"></div>
            </div>
          </template>
        </div>
      </div>
    </div>
  </div>
</template>
