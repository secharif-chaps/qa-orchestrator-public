<template>
  <div
    v-if="sourcedValue?.value"
    class="bg-primary-lighter rounded-card border-primary-lighter-stroke border p-4"
  >
    <div class="relative flex items-center gap-4">
      <div class="bg-base-300 flex size-10 shrink-0 items-center justify-center rounded-lg">
        <Icon :icon="icon" class="text-neutral-black-font text-lg" />
      </div>

      <div class="min-w-0 flex-1">
        <div class="flex items-center gap-2">
          <h3 class="text-lg font-semibold">
            {{ sourcedValue.value }}
          </h3>
          <Tag
            v-if="growthTag"
            :intent="growthPositive ? 'success' : 'danger'"
            :label="growthTag"
            size="xs"
            :icon="growthPositive ? 'fa-arrow-up' : 'fa-arrow-down'"
          />
        </div>
        <p v-if="sourcedValue.context" class="text-neutral-black-font text-xs">
          {{ sourcedValue.context }}
        </p>
        <p class="text-neutral-black-font/70 text-sm">{{ label }}</p>
      </div>

      <div class="absolute top-0 right-0">
        <Source :sourced-value="sourcedValue" />
      </div>
    </div>
  </div>
</template>

<script lang="ts" setup>
import Source from '@/components/company/Source.vue'
import type { SourcedValue } from '@/types/company'
import { Icon, Tag } from '@owlint/feathers-vue'

interface Props {
  icon: string
  label: string
  sourcedValue: SourcedValue<string> | null
  growthTag?: string
  growthPositive?: boolean
}

const { icon, label, sourcedValue, growthTag, growthPositive } = defineProps<Props>()
</script>
