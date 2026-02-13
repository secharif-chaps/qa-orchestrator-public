<template>
  <div
    v-for="i in 3"
    :key="i"
    class="animate-pulse space-y-2 rounded-lg border border-gray-200 bg-gray-100 p-2 text-gray-900"
  >
    <div class="flex items-center gap-2">
      <div class="size-8 rounded-full bg-gray-200" />
      <div class="h-4 w-32 rounded bg-gray-200" />
    </div>
    <div class="h-24 w-full rounded bg-gray-200" />
  </div>
</template>
