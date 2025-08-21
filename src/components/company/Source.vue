<template>
  <!-- Source from sourced value -->
  <div
    class="relative inline-block group"
    v-if="
      sourcedValue && getSourcedSource(sourcedValue) && getSourcedSource(sourcedValue) !== 'N/A'
    "
  >
    <a
      :href="getSourcedSource(sourcedValue) || ''"
      target="_blank"
      class="text-secondary/50 hover:text-secondary transition-colors duration-200"
    >
      <i class="fa fa-info-circle text-sm"></i>
    </a>

    <div
      class="absolute bottom-full mb-2 left-1/2 transform -translate-x-1/2 opacity-0 group-hover:opacity-100 transition-opacity duration-200 pointer-events-none z-10 whitespace-nowrap"
    >
      <div
        class="bg-gray-800 text-white text-xs rounded px-2 py-1 max-w-xs break-all overflow-hidden text-ellipsis"
      >
        {{ getSourcedSource(sourcedValue) }}
      </div>
      <div
        class="w-2 h-2 bg-gray-800 transform rotate-45 absolute -bottom-1 left-1/2 -translate-x-1/2"
      ></div>
    </div>
  </div>

  <!-- Direct source prop -->
  <div class="relative inline-block group" v-else-if="source">
    <a
      :href="source"
      target="_blank"
      class="text-secondary/50 hover:text-secondary transition-colors duration-200"
    >
      <i class="fa fa-info-circle text-xs"></i>
    </a>

    <div
      class="absolute bottom-full mb-2 left-1/2 transform -translate-x-1/2 opacity-0 group-hover:opacity-100 transition-opacity duration-200 pointer-events-none z-10 whitespace-nowrap"
    >
      <div
        class="bg-gray-800 text-white text-xs rounded px-2 py-1 max-w-xs break-all overflow-hidden text-ellipsis"
      >
        {{ source }}
      </div>
      <div
        class="w-2 h-2 bg-gray-800 transform rotate-45 absolute -bottom-1 left-1/2 -translate-x-1/2"
      ></div>
    </div>
  </div>
</template>

<script lang="ts" setup>
import { getSourcedSource } from '@/components/helpers/sourcedValues'
import type { SourcedValue } from '@/types/company'

interface Props {
  sourcedValue?: SourcedValue<string>
  source?: string
}

defineProps<Props>()
</script>
