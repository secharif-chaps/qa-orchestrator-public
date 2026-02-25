<template>
  <div class="flex w-full flex-1">
    <!-- Vertical line -->
    <div class="absolute top-0 left-6 z-10 w-0.5 bg-gray-200" style="height: 100%"></div>
    <div ref="timelineRef" class="relative w-full pl-10">
      <!-- Simulate 3 days of activities -->
      <template v-for="dayIndex in 2" :key="dayIndex">
        <!-- Date header -->
        <div class="relative mb-6">
          <div
            class="relative left-8 z-20 -ml-10 inline-block -translate-x-1/2 justify-center rounded-md bg-gray-200 px-2 py-1"
          >
            <div class="h-4 w-20 animate-pulse rounded bg-gray-200"></div>
          </div>
        </div>

        <!-- Activities for this day -->
        <template
          v-for="activityIndex in dayIndex === 1 ? 3 : 2"
          :key="`${dayIndex}-${activityIndex}`"
        >
          <div class="relative mb-4 flex gap-2">
            <div
              class="z-10 -ml-10 flex size-8 shrink-0 -translate-x-1/2 items-center justify-center rounded-full border-2 border-white"
            >
              <div class="size-4 animate-pulse rounded bg-gray-300"></div>
            </div>
            <div class="flex w-full flex-col gap-1 pt-1">
              <div>
                <div class="h-4 w-12 animate-pulse rounded bg-gray-200"></div>
              </div>
              <div>
                <div class="space-y-1">
                  <div class="h-4 w-4/5 animate-pulse rounded bg-gray-200"></div>
                  <div class="h-4 w-3/4 animate-pulse rounded bg-gray-200"></div>
                </div>
              </div>
            </div>
          </div>
        </template>
      </template>
    </div>
  </div>
</template>

<script setup lang="ts">
// No props needed for skeleton
</script>
