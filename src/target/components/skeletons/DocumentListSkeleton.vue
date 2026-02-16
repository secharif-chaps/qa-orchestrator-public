<template>
  <div>
    <div
      v-for="index in props.skeletonCount"
      :key="`skeleton-${index}`"
      class="px-6 py-2"
    >
      <div class="flex items-center gap-4">
        <div class="flex items-center gap-3">
          <div class="h-4 w-4 animate-pulse rounded bg-gray-200"></div>
          <div class="h-8 w-8 animate-pulse rounded-full bg-gray-200"></div>
        </div>

        <div class="min-w-0 flex-1">
          <div
            class="mb-2 h-4 animate-pulse rounded bg-gray-200"
            style="width: 60%"
          ></div>
          <div class="flex items-center gap-2">
            <div
              class="h-3 animate-pulse rounded bg-gray-200"
              style="width: 10%"
            ></div>
            <div class="h-1 w-1 rounded-full bg-gray-200"></div>
            <div
              class="h-3 animate-pulse rounded bg-gray-200"
              style="width: 20%"
            ></div>
          </div>
        </div>

        <div class="flex items-center">
          <div class="mr-4 flex border border-gray-200">
            <div class="h-8 w-8 animate-pulse bg-gray-200"></div>
            <div class="h-8 w-8 animate-pulse bg-gray-200"></div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
const props = withDefaults(
  defineProps<{
    skeletonCount?: number;
  }>(),
  {
    skeletonCount: 10,
  },
);
</script>
