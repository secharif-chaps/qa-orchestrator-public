<template>
  <div class="p-xs bg-sage-100 space-y-3xs rounded-md">
    <div class="gap-2xs flex items-center">
      <h5 class="text-base font-semibold">
        {{ title }}
      </h5>
      <Source :sourced-value="sourcedValue" />
    </div>
    <p>
      {{ getSourcedValue(sourcedValue) }}
    </p>
  </div>
</template>

<script lang="ts" setup>
import Source from '@/components/company/Source.vue'
import { getSourcedValue } from '@/components/helpers/sourcedValues'
import type { SourcedValue } from '@/types/company'

defineProps<{
  title: string
  sourcedValue?: SourcedValue<string>
}>()
</script>
