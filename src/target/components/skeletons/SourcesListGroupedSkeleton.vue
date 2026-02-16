<template>
  <div class="flex h-full flex-col">
    <div class="min-h-0 flex-1 overflow-y-auto">
      <div class="space-y-4">
        <div
          v-for="(section, index) in sections"
          :key="`section-${index}`"
          class="mr-4 mb-6 space-y-3"
        >
          <div class="flex items-center justify-between">
            <div class="flex items-center gap-2">
              <div class="h-4 w-32 animate-pulse rounded bg-gray-200" />
              <div class="h-6 w-8 animate-pulse rounded-full bg-gray-200" />
              <div class="h-6 w-6 animate-pulse rounded bg-gray-200" />
            </div>
          </div>
          <div class="grid grid-cols-1 gap-3 md:grid-cols-2 lg:grid-cols-3">
            <div
              v-for="i in section.cardCount"
              :key="`card-${index}-${i}`"
              class="rounded-lg border border-gray-200 bg-white p-3"
            >
              <div class="flex items-center gap-3">
                <div class="h-8 w-8 animate-pulse rounded-full bg-gray-200" />
                <div class="flex-1">
                  <div
                    class="mb-2 h-4 w-3/4 animate-pulse rounded bg-gray-200"
                  />
                  <div class="h-3 w-1/2 animate-pulse rounded bg-gray-200" />
                </div>
                <div class="h-6 w-6 animate-pulse rounded bg-gray-200" />
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue';

const sections = computed(() => {
  return Array.from({ length: 3 }, (_, index) => ({
    cardCount: index % 2 === 0 ? 6 : 5,
  }));
});
</script>
