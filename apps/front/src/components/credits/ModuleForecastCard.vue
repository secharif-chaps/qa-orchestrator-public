<template>
  <div
    class="rounded-md border p-4"
    :class="[
      forecast.enabled
        ? [moduleConfig.cardBg, moduleConfig.cardBorder]
        : 'border-primary-lighter-stroke bg-white',
      { 'opacity-60': !forecast.enabled },
    ]"
  >
    <div class="flex flex-col gap-3">
      <!-- Header with icon and module name -->
      <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
          <div
            class="flex h-10 w-10 items-center justify-center rounded-sm"
            :class="forecast.enabled ? moduleConfig.iconBg : 'bg-primary-lightest'"
          >
            <i
              :class="[
                moduleConfig.icon,
                forecast.enabled ? moduleConfig.iconText : 'text-neutral-black-font',
              ]"
            ></i>
          </div>
          <h4 class="text-base font-semibold">{{ moduleLabel }}</h4>
        </div>
        <!-- Refresh icon for enabled, disabled badge for disabled -->
        <Button
          v-if="forecast.enabled"
          variant="tertiary"
          size="sm"
          icon="fa-rotate-right"
          :title="$t('settings.credits.refresh')"
        />
        <Tag v-else variant="secondary" size="sm" :label="$t('settings.credits.module.disabled')" />
      </div>

      <!-- Enabled state with count -->
      <template v-if="forecast.enabled">
        <!-- Cost per item info -->
        <div v-if="forecast.cost" class="text-neutral-black-font flex items-center gap-2 text-sm">
          <i class="fa fa-circle-info"></i>
          <span>{{
            $t('settings.credits.module.costPerItem', {
              cost: forecast.cost,
              item: itemLabel,
            })
          }}</span>
        </div>

        <!-- Remaining info -->
        <div class="text-neutral-black-font text-sm">
          {{
            $t('settings.credits.module.canCreate', {
              count: formattedCount,
              item: itemPluralLabel,
            })
          }}
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
import type { ModuleForecast, ModuleName } from '@/types/credits'
import { Button, Tag } from '@owlint/feathers-vue'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

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
const MODULE_CONFIG: Record<
  ModuleName,
  {
    cardBg: string
    cardBorder: string
    iconBg: string
    iconText: string
    icon: string
  }
> = {
  screen: {
    cardBg: 'bg-indigo-50/50',
    cardBorder: 'border-indigo-200',
    iconBg: 'bg-indigo-100',
    iconText: 'text-indigo-600',
    icon: 'fa-solid fa-building',
  },
  target: {
    cardBg: 'bg-rose-50/50',
    cardBorder: 'border-rose-200',
    iconBg: 'bg-rose-100',
    iconText: 'text-rose-600',
    icon: 'fa-solid fa-bullseye',
  },
  explore: {
    cardBg: 'bg-almond-50/50',
    cardBorder: 'border-almond-200',
    iconBg: 'bg-almond-100',
    iconText: 'text-almond-600',
    icon: 'fa-solid fa-project-diagram',
  },
}

const moduleConfig = computed(() => {
  return MODULE_CONFIG[props.forecast.module] || MODULE_CONFIG.screen
})

const moduleLabelMap = computed<Record<ModuleName, string>>(() => ({
  screen: t('settings.credits.module.screen.label'),
  target: t('settings.credits.module.target.label'),
  explore: t('settings.credits.module.explore.label'),
}))

const itemLabelMap = computed<Record<ModuleName, string>>(() => ({
  screen: t('settings.credits.module.screen.item'),
  target: t('settings.credits.module.target.item'),
  explore: t('settings.credits.module.explore.item'),
}))

const itemPluralLabelMap = computed<Record<ModuleName, string>>(() => ({
  screen: t('settings.credits.module.screen.itemPlural'),
  target: t('settings.credits.module.target.itemPlural'),
  explore: t('settings.credits.module.explore.itemPlural'),
}))

// Translated labels
const moduleLabel = computed(() => moduleLabelMap.value[props.forecast.module])
const itemLabel = computed(() => itemLabelMap.value[props.forecast.module])
const itemPluralLabel = computed(() => itemPluralLabelMap.value[props.forecast.module])

const formattedCount = computed(() => {
  if (!props.forecast.enabled || props.forecast.remainingCount === null) {
    return '-'
  }
  return props.forecast.remainingCount.toLocaleString()
})
</script>
