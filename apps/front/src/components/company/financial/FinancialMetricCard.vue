<template>
  <div v-if="sourcedValue?.value" class="bg-base-200 rounded-card border-primary-stroke border p-4">
    <div class="relative flex items-center gap-4">
      <div class="bg-base-300 flex h-10 w-10 shrink-0 items-center justify-center rounded-lg">
        <i :class="icon" class="text-secondary text-lg"></i>
      </div>

      <div class="min-w-0 flex-1">
        <div class="flex items-center gap-2">
          <h3 class="text-lg font-semibold">
            {{ sourcedValue.value }}
          </h3>
          <Tag
            v-if="growthTag"
            :variant="growthPositive ? 'success' : 'error'"
            :label="growthTag"
            size="xs"
            :icon="growthPositive ? 'fa fa-arrow-up' : 'fa fa-arrow-down'"
          />
        </div>
        <p class="text-secondary/70 text-sm">{{ label }}</p>
      </div>

      <div class="absolute top-0 right-0">
        <Source :sourced-value="sourcedValue" />
      </div>
    </div>
  </div>
</template>

<script lang="ts" setup>
import Source from '@/components/company/Source.vue'
import Tag from '@/components/ui/Tag.vue'
import type { SourcedValue } from '@/types/company'

interface Props {
  icon: string
  label: string
  sourcedValue: SourcedValue<string> | null
  growthTag?: string
  growthPositive?: boolean
}

const { icon, label, sourcedValue, growthTag, growthPositive } = defineProps<Props>()
</script>
