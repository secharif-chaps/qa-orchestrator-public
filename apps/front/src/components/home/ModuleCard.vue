<template>
  <div
    :class="cardClasses.card"
    class="rounded-card flex min-w-58 flex-1 flex-col gap-3 border p-3"
  >
    <!-- Header: badge + action -->
    <div class="flex items-center justify-between">
      <CmdBadge
        :icon="module.type === 'disabled' ? 'fa-regular fa-ban' : module.icon"
        :color="module.type === 'disabled' ? 'disabled' : module.theme"
      />

      <Button
        v-if="module.type === 'default' && module.actionLabel"
        size="sm"
        variant="primary"
        :label="module.actionLabel"
        icon-right="fa-solid fa-arrow-right"
        @click="emit('action')"
      />
      <span
        v-else-if="module.type === 'disabled' && module.actionLabel"
        class="inline-flex items-center gap-1.5 rounded-full bg-gray-200 px-2 py-0.5 text-xs font-medium text-gray-600"
      >
        {{ module.actionLabel }}
        <Icon icon="fa-regular fa-ban" class="text-xs" />
      </span>
      <Tag
        v-else-if="module.type === 'soon'"
        color="sage"
        size="xs"
        :label="module.comingSoonLabel ?? ''"
      />
    </div>

    <!-- Title + Description -->
    <div class="flex flex-col gap-1">
      <p class="leading-lg text-lg font-bold text-black dark:text-white">
        {{ module.title }}
      </p>
      <p class="text-base leading-5 text-black dark:text-white">
        {{ module.description }}
      </p>
    </div>

    <!-- Stat line -->
    <p v-if="module.statLine" class="leading-sm text-sm text-gray-800 dark:text-gray-400">
      {{ module.statLine }}
    </p>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { Button, Icon, Tag } from '@owlint/feathers-vue'
import CmdBadge from '@/components/ui/CmdBadge.vue'

export type ModuleTheme = 'indigo' | 'cherry' | 'yellow' | 'cyan'
export type ModuleCardType = 'default' | 'soon' | 'disabled'

export interface ModuleCardConfig {
  key: string
  theme: ModuleTheme
  type: ModuleCardType
  icon: string
  title: string
  description: string
  statLine?: string
  actionLabel?: string
  comingSoonLabel?: string
}

interface Props {
  module: ModuleCardConfig
}

const { module } = defineProps<Props>()

const emit = defineEmits<{
  action: []
}>()

const CARD_CLASSES: Record<ModuleTheme, string> = {
  indigo: 'bg-indigo-100 border-indigo-600',
  cherry: 'bg-cherry-100 border-cherry-600',
  yellow: 'bg-yellow-100 border-yellow-700',
  cyan: 'bg-cyan-100 border-cyan-600',
}

const DISABLED_CARD_CLASS = 'bg-gray-100 border-gray-300'

const cardClasses = computed(() => ({
  card: module.type === 'disabled' ? DISABLED_CARD_CLASS : CARD_CLASSES[module.theme],
}))
</script>
