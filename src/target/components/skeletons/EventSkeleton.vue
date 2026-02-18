<template>
  <div class="w-full space-y-8">
    <template v-for="index in 2" :key="index">
      <div class="space-y-4">
        <!-- Date Tag -->
        <div class="h-6 w-48 animate-pulse rounded bg-gray-200"></div>
        <div class="space-y-3 text-sm">
          <!-- Description -->
          <div>
            <div class="space-y-2">
              <div class="h-3.5 w-full animate-pulse rounded bg-gray-200"></div>
              <div class="h-3.5 w-5/6 animate-pulse rounded bg-gray-200"></div>
              <div class="h-3.5 w-4/5 animate-pulse rounded bg-gray-200"></div>
            </div>
          </div>
          <!-- Actors section -->
          <div>
            <div class="mb-2 h-4 w-32 animate-pulse rounded bg-gray-200"></div>
            <ul class="mt-1 list-inside list-disc space-y-1">
              <li v-for="actorIndex in 3" :key="actorIndex" class="flex items-center gap-1.5">
                <div class="h-3.5 w-32 animate-pulse rounded bg-gray-200"></div>
              </li>
            </ul>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>

<script setup lang="ts"></script>
