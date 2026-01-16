<template>
  <div
    class="rounded-xl border p-4"
    :class="[
      forecast.enabled ? [moduleConfig.cardBg, moduleConfig.cardBorder] : 'bg-base-100 border-base-300',
      { 'opacity-60': !forecast.enabled }
    ]"
  >
    <div class="flex flex-col gap-3">
      <!-- Header with icon and module name -->
      <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
          <div
            class="w-10 h-10 rounded-lg flex items-center justify-center"
            :class="forecast.enabled ? moduleConfig.iconBg : 'bg-base-200'"
          >
            <i
              :class="[moduleConfig.icon, forecast.enabled ? moduleConfig.iconText : 'text-secondary']"
            ></i>
          </div>
          <h4 class="font-semibold text-base">{{ moduleLabel }}</h4>
        </div>
        <!-- Refresh icon for enabled, disabled badge for disabled -->
        <button v-if="forecast.enabled" class="text-secondary hover:text-primary transition-colors">
          <i class="fa fa-rotate-right"></i>
        </button>
        <Tag v-else variant="secondary" size="sm" :label="$t('credits.module.disabled')" />
      </div>

      <!-- Enabled state with count -->
      <template v-if="forecast.enabled">
        <!-- Cost per item info -->
        <div v-if="forecast.cost" class="flex items-center gap-2 text-sm text-secondary">
          <i class="fa fa-circle-info"></i>
          <span>{{ $t('credits.module.costPerItem', {
            cost: forecast.cost,
            item: itemLabel
          }) }}</span>
        </div>

        <!-- Remaining info -->
        <div class="text-sm text-secondary">
          {{ $t('credits.module.canCreate') }} :
        </div>

        <!-- Remaining count pill - smaller with white background -->
        <div
          class="inline-flex items-center px-3 py-1.5 rounded-full w-fit bg-white border border-base-300 shadow-sm"
        >
          <span class="font-medium text-sm text-base-content">
            {{ formattedCount }} {{ itemPluralLabel }}
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
import { useI18n } from 'vue-i18n'
import { Tag } from '@owlint/feathers-vue'
import type { ModuleForecast, ModuleName } from '@/types/credits'

const { t } = useI18n()

interface Props {
  forecast: ModuleForecast
}

const props = defineProps<Props>()

// Tailwind safelist: bg-indigo-50/50 bg-rose-50/50 bg-almond-50/50
// border-indigo-200 border-rose-200 border-almond-200
// bg-indigo-100 bg-rose-100 bg-almond-100
// text-indigo-600 text-rose-600 text-almond-600

// Module-specific configurations matching the appbar badges
// screen = indigo, target = cherry (rose in tailwind), explore = almond
const MODULE_CONFIG: Record<ModuleName, {
  cardBg: string
  cardBorder: string
  iconBg: string
  iconText: string
  icon: string
  labelKey: string
  itemKey: string
  itemPluralKey: string
}> = {
  screen: {
    cardBg: 'bg-indigo-50/50',
    cardBorder: 'border-indigo-200',
    iconBg: 'bg-indigo-100',
    iconText: 'text-indigo-600',
    icon: 'fa-solid fa-building',
    labelKey: 'credits.module.screen.label',
    itemKey: 'credits.module.screen.item',
    itemPluralKey: 'credits.module.screen.itemPlural',
  },
  target: {
    cardBg: 'bg-rose-50/50',
    cardBorder: 'border-rose-200',
    iconBg: 'bg-rose-100',
    iconText: 'text-rose-600',
    icon: 'fa-solid fa-bullseye',
    labelKey: 'credits.module.target.label',
    itemKey: 'credits.module.target.item',
    itemPluralKey: 'credits.module.target.itemPlural',
  },
  explore: {
    cardBg: 'bg-almond-50/50',
    cardBorder: 'border-almond-200',
    iconBg: 'bg-almond-100',
    iconText: 'text-almond-600',
    icon: 'fa-solid fa-project-diagram',
    labelKey: 'credits.module.explore.label',
    itemKey: 'credits.module.explore.item',
    itemPluralKey: 'credits.module.explore.itemPlural',
  },
}

const moduleConfig = computed(() => {
  return MODULE_CONFIG[props.forecast.module] || MODULE_CONFIG.screen
})

// Translated labels using i18n keys
const moduleLabel = computed(() => t(moduleConfig.value.labelKey))
const itemLabel = computed(() => t(moduleConfig.value.itemKey))
const itemPluralLabel = computed(() => t(moduleConfig.value.itemPluralKey))

const formattedCount = computed(() => {
  if (!props.forecast.enabled || props.forecast.remainingCount === null) {
    return '-'
  }
  return props.forecast.remainingCount.toLocaleString()
})
</script>
