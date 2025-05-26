<template>
  <div
    class="relative inline-block group"
    v-if="
      sourcedValue &&
      getSourcedSource(sourcedValue) &&
      getSourcedSource(sourcedValue) !== 'N/A'
    "
  >
    <NuxtLink
      :to="getSourcedSource(sourcedValue)"
      target="_blank"
      class="inline-flex items-center"
    >
      <span
        class="text-xs bg-slate-100 hover:bg-slate-200 transition-colors rounded-full px-2 py-1"
      >
        {{ getSourcedSourceName(sourcedValue) }}
      </span>
    </NuxtLink>

    <!-- Tooltip qui s'affiche au hover -->
    <div
      class="absolute bottom-full mb-2 left-1/2 transform -translate-x-1/2 opacity-0 group-hover:opacity-100 transition-opacity duration-200 pointer-events-none z-10"
    >
      <div class="bg-gray-800 text-white text-xs rounded p-4 whitespace-nowrap">
        {{ getSourcedSource(sourcedValue) }}
      </div>
      <!-- Petite flèche du tooltip -->
      <div
        class="w-2 h-2 bg-gray-800 transform rotate-45 absolute -bottom-1 left-1/2 -translate-x-1/2"
      ></div>
    </div>
  </div>
  <div v-if="source">
    <NuxtLink
      :to="source"
      target="_blank"
      class="inline-flex items-center"
    >
      <span
        class="text-xs bg-slate-100 hover:bg-slate-200 transition-colors rounded-full px-2 py-1"
      >
        {{ source }}
      </span>
    </NuxtLink>
  </div>
</template>

<script lang="ts" setup>
interface Props {
  sourcedValue?: any
  source?: string
}

defineProps<Props>()

const { getSourcedSource, getSourcedSourceName } = useCompanyData()
</script>
