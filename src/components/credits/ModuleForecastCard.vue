<template>
  <div
    class="bg-base-100 rounded-lg p-4 border border-primary-stroke"
    :class="{ 'opacity-60': !forecast.enabled }"
  >
    <div class="flex flex-col gap-3">
      <!-- Header with icon and module name -->
      <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
          <div
            class="w-10 h-10 rounded-lg flex items-center justify-center"
            :class="forecast.enabled ? moduleColors.iconBg : 'bg-base-200'"
          >
            <i
              :class="[forecast.icon, forecast.enabled ? moduleColors.iconText : 'text-secondary']"
            ></i>
          </div>
          <h4 class="font-medium">{{ forecast.label }}</h4>
        </div>
        <!-- Refresh icon for enabled, disabled badge for disabled -->
        <button v-if="forecast.enabled" class="text-secondary hover:text-primary transition-colors">
          <i class="fa fa-rotate-right text-sm"></i>
        </button>
        <Tag v-else variant="secondary" size="sm" :label="$t('credits.module.disabled')" />
      </div>

      <!-- Enabled state with count -->
      <template v-if="forecast.enabled">
        <!-- Cost per item -->
        <div v-if="forecast.cost" class="text-xs text-secondary">
          <i class="fa fa-clock mr-1"></i>
          {{ $t('credits.module.costPerItem', {
            cost: forecast.cost,
            item: forecast.itemLabel
          }) }}
        </div>

        <!-- Remaining info -->
        <div class="text-sm text-secondary">
          {{ $t('credits.module.canCreate', 'Vous pouvez encore créer') }} :
        </div>

        <!-- Remaining count badge -->
        <div
          class="inline-flex items-center gap-2 px-3 py-2 rounded-md w-fit"
          :class="moduleColors.countBg"
        >
          <span class="font-semibold" :class="moduleColors.countText">
            {{ formattedCount }} {{ forecast.itemLabelPlural }}
          </span>
        </div>
      </template>
    </div>
  </div>
</template>

<script setup lang="ts">
/**
 * Single module forecast card showing remaining capacity.
 * Displays how many more items can be created with remaining credits.
 */
import { computed } from 'vue'
import { Tag } from '@owlint/feathers-vue'
import type { ModuleForecast, ModuleName } from '@/types/credits'

interface Props {
  forecast: ModuleForecast
}

const props = defineProps<Props>()

// Module-specific color configurations matching the appbar badge colors
const MODULE_COLORS: Record<ModuleName, {
  iconBg: string
  iconText: string
  countBg: string
  countText: string
}> = {
  screen: {
    iconBg: 'bg-indigo-100',
    iconText: 'text-indigo-600',
    countBg: 'bg-indigo-50',
    countText: 'text-indigo-700',
  },
  target: {
    iconBg: 'bg-rose-100',
    iconText: 'text-rose-600',
    countBg: 'bg-rose-50',
    countText: 'text-rose-700',
  },
  explore: {
    iconBg: 'bg-primary-light',
    iconText: 'text-primary',
    countBg: 'bg-primary-light',
    countText: 'text-primary',
  },
}

const moduleColors = computed(() => {
  return MODULE_COLORS[props.forecast.module] || MODULE_COLORS.screen
})

const formattedCount = computed(() => {
  if (!props.forecast.enabled || props.forecast.remainingCount === null) {
    return '-'
  }
  return props.forecast.remainingCount.toLocaleString()
})
</script>
