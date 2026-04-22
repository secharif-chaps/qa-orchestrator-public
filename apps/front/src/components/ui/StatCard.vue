<template>
  <div
    class="gap-md p-xs flex min-w-60 flex-1 items-center rounded-lg border"
    :class="colorClasses"
  >
    <Icon :icon="icon" class="text-base" />
    <div class="flex flex-1 items-center justify-between">
      <span class="truncate font-bold">
        <slot>{{ label }}</slot>
      </span>
      <span class="shrink-0 text-sm">{{ value }}</span>
    </div>
  </div>
</template>

<script lang="ts" setup>
import type { StatCardColor } from '@/types/ui'
import { Icon } from '@owlint/feathers-vue'
import { computed } from 'vue'

interface Props {
  icon: string
  label?: string
  value: string | number
  color?: StatCardColor
}

const { color = 'sage' } = defineProps<Props>()

const colorClasses = computed(() => {
  const map: Record<StatCardColor, string> = {
    sage: 'bg-sage-100 border-sage-500 dark:bg-sage-400/20 dark:border-sage-400/30',
    indigo: 'bg-indigo-100 border-indigo-500 dark:bg-indigo-400/20 dark:border-indigo-400/30',
    blue: 'bg-blue-100 border-blue-500 dark:bg-blue-400/20 dark:border-blue-400/30',
    cherry: 'bg-cherry-100 border-cherry-500 dark:bg-cherry-400/20 dark:border-cherry-400/30',
    almond: 'bg-almond-100 border-almond-500 dark:bg-almond-400/20 dark:border-almond-400/30',
    rose: 'bg-rose-100 border-rose-500 dark:bg-rose-400/20 dark:border-rose-400/30',
  }
  return map[color]
})
</script>
