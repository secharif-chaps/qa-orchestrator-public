<template>
  <div
    class="relative inline-block group"
    v-if="getSourcedSource(item) && getSourcedSource(item) !== 'N/A'"
  >
    <NuxtLink
      :to="getSourcedSource(item)"
      target="_blank"
      class="inline-flex items-center"
    >
      <span
        class="text-xs bg-slate-100 hover:bg-slate-200 transition-colors rounded-full px-2 py-1"
      >
        {{ getSourcedSourceName(item) }}
      </span>
    </NuxtLink>

    <!-- Tooltip qui s'affiche au hover -->
    <div
      class="absolute bottom-full mb-2 left-1/2 transform -translate-x-1/2 opacity-0 group-hover:opacity-100 transition-opacity duration-200 pointer-events-none z-10"
    >
      <div class="bg-gray-800 text-white text-xs rounded p-4 whitespace-nowrap">
        {{ getSourcedSource(item) }}
      </div>
      <!-- Petite flèche du tooltip -->
      <div
        class="w-2 h-2 bg-gray-800 transform rotate-45 absolute -bottom-1 left-1/2 -translate-x-1/2"
      ></div>
    </div>
  </div>
</template>

<script lang="ts" setup>
defineProps<{
  item: any
}>()

const { getSourcedSource, getSourcedSourceName } = useCompanyData()
</script>
