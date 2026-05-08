<template>
  <div class="flex flex-1 overflow-hidden">
    <!-- Left Pane - Main Content with Header -->
    <div class="flex flex-1 flex-col overflow-hidden">
      <!-- Header -->
      <div class="shrink-0 px-8 py-6">
        <div class="h-8 w-[90%] animate-pulse rounded bg-gray-200"></div>
      </div>

      <!-- Content Area -->
      <div class="flex-1 overflow-hidden px-8">
        <div class="flex h-full flex-col">
          <div class="flex-1 space-y-4">
            <div class="space-y-3">
              <div
                v-for="i in 3"
                :key="i"
                class="h-4 animate-pulse rounded bg-gray-200"
                :style="{ width: `${Math.random() * 40 + 60}%` }"
              ></div>
            </div>
            <div class="flex gap-4">
              <div
                class="h-30 w-[30%] animate-pulse rounded-lg border border-gray-200 bg-gray-200"
              />
              <div
                class="h-30 w-[30%] animate-pulse rounded-lg border border-gray-200 bg-gray-200"
              />
            </div>
            <div class="space-y-3">
              <div
                v-for="i in 8"
                :key="i"
                class="h-4 animate-pulse rounded bg-gray-200"
                :style="{ width: `${Math.random() * 40 + 60}%` }"
              ></div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Right Pane - Information Sidebar -->
    <div class="scrollable w-[30%] shrink-0 border-l border-gray-200">
      <div class="space-y-6 p-6">
        <div class="animate-pulse rounded-lg border border-gray-200 bg-gray-200 p-4" />
        <div class="h-30 w-full animate-pulse rounded-lg border border-gray-200 bg-gray-200 p-4" />
        <div class="h-30 w-full animate-pulse rounded-lg border border-gray-200 bg-gray-200 p-4" />
        <div class="animate-pulse rounded-lg border border-gray-200 bg-gray-200 p-4" />
      </div>
    </div>
  </div>
</template>

<script setup lang="ts"></script>
