<template>
  <div
    class="relative inline-block group"
    v-if="
      sourcedValue && getSourcedSource(sourcedValue) && getSourcedSource(sourcedValue) !== 'N/A'
    "
  >
    <a
      :href="getSourcedSource(sourcedValue) || ''"
      target="_blank"
      class="inline-flex items-center"
    >
      <Badge
        variant="slate"
        size="xs"
        :label="getSourcedSourceName(sourcedValue)"
        rounded
        :gradient="false"
      />
    </a>

    <div
      class="absolute bottom-full mb-2 left-1/2 transform -translate-x-1/2 opacity-0 group-hover:opacity-100 transition-opacity duration-200 pointer-events-none z-10"
    >
      <div class="bg-gray-800 text-white text-xs rounded p-4 whitespace-nowrap">
        {{ getSourcedSource(sourcedValue) }}
      </div>
      <div
        class="w-2 h-2 bg-gray-800 transform rotate-45 absolute -bottom-1 left-1/2 -translate-x-1/2"
      ></div>
    </div>
  </div>
  <div v-if="source">
    <a :href="source" target="_blank" class="inline-flex items-center">
      <Badge variant="slate" size="xs" :label="source" rounded :gradient="false" />
    </a>
  </div>
</template>

<script lang="ts" setup>
import Badge from '@/components/ui/Badge.vue'
import { getSourcedSource, getSourcedSourceName } from '@/components/helpers/sourcedValues'
import type { SourcedValue } from '@/types/company'

interface Props {
  sourcedValue?: SourcedValue<string>
  source?: string
}

defineProps<Props>()
</script>
