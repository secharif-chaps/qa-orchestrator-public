<template>
  <div class="flex h-full flex-col gap-6 p-6">
    <!-- Main content sections -->
    <div class="flex flex-1 gap-6">
      <!-- Left section -->
      <div class="h-full w-80 space-y-6">
        <!-- Side panel -->
        <div class="h-full space-y-4 rounded-lg border border-gray-200 p-6">
          <div class="h-5 w-32 animate-pulse rounded bg-gray-200"></div>
          <div class="space-y-3">
            <div
              v-for="i in 3"
              :key="i"
              class="h-20 w-full animate-pulse rounded-lg bg-gray-200"
            ></div>
          </div>
        </div>
      </div>

      <!-- Right section -->
      <div class="flex-1 space-y-6">
        <!-- Section 1 -->
        <div class="space-y-4 rounded-lg border border-gray-200 p-6">
          <div class="h-5 w-48 animate-pulse rounded bg-gray-200"></div>
          <div class="space-y-3">
            <div
              v-for="width in textLineWidths"
              :key="width"
              :class="`h-4 ${width} animate-pulse rounded bg-gray-200`"
            ></div>
          </div>
          <div class="flex gap-2">
            <div
              v-for="i in 2"
              :key="i"
              class="h-8 w-24 animate-pulse rounded bg-gray-200"
            ></div>
          </div>
        </div>

        <!-- Section 2 -->
        <div class="space-y-4 rounded-lg border border-gray-200 p-6">
          <div class="h-5 w-40 animate-pulse rounded bg-gray-200"></div>
          <div class="space-y-2">
            <div
              v-for="(item, index) in listItems"
              :key="index"
              class="flex items-center gap-3"
            >
              <div
                class="h-10 w-10 animate-pulse rounded-full bg-gray-200"
              ></div>
              <div class="flex-1 space-y-2">
                <div
                  :class="`h-4 ${item.titleWidth} animate-pulse rounded bg-gray-200`"
                ></div>
                <div
                  :class="`h-3 ${item.subtitleWidth} animate-pulse rounded bg-gray-200`"
                ></div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
const textLineWidths = ['w-full', 'w-5/6', 'w-4/5'];

const listItems = [
  { titleWidth: 'w-3/4', subtitleWidth: 'w-1/2' },
  { titleWidth: 'w-2/3', subtitleWidth: 'w-2/5' },
  { titleWidth: 'w-4/5', subtitleWidth: 'w-3/5' },
];
</script>
