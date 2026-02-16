<template>
  <div class="h-full">
    <div v-for="i in 3" :key="i" class="space-y-6 border-b border-b-gray-200">
      <div class="my-5 animate-pulse space-y-3 rounded">
        <div class="h-8 w-full rounded bg-gray-200" />
        <div class="space-y-1.5">
          <div class="h-5 w-32 rounded bg-gray-200" />
          <div class="h-5 w-48 rounded bg-gray-200" />
          <div class="h-5 w-64 rounded bg-gray-200" />
          <div class="h-5 w-36 rounded bg-gray-200" />
        </div>
      </div>
    </div>
  </div>
</template>

<script lang="ts" setup></script>
