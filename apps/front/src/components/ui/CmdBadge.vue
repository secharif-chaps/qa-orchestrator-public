<template>
  <div :class="badgeClass" class="flex size-4.5 items-center justify-center rounded p-px">
    <Icon :icon="icon" class="text-sm" />
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { Icon } from '@owlint/feathers-vue'

export type CmdBadgeColor = 'indigo' | 'cherry' | 'yellow' | 'cyan' | 'disabled'

interface Props {
  icon: string
  color: CmdBadgeColor
}

const { color } = defineProps<Props>()

const BADGE_CLASSES: Record<CmdBadgeColor, string> = {
  indigo: 'bg-indigo-200',
  cherry: 'bg-cherry-200',
  yellow: 'bg-yellow-200',
  cyan: 'bg-cyan-200',
  disabled: 'bg-gray-200',
}

const badgeClass = computed(() => BADGE_CLASSES[color])
</script>
