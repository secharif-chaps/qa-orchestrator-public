<template>
  <Card padding="p-6">
    <h3 class="text-lg font-semibold">
      {{ $t('settings.credits.forecast.title') }}
    </h3>

    <!-- Loading state -->
    <div v-if="loading" class="grid grid-cols-1 gap-4">
      <div v-for="n in 3" :key="n" class="bg-primary-lightest animate-pulse rounded-sm p-4">
        <div class="mb-3 flex items-center gap-3">
          <div class="bg-primary-lighter h-10 w-10 rounded-sm"></div>
          <div class="bg-primary-lighter h-4 w-24 rounded"></div>
        </div>
        <div class="bg-primary-lighter mb-2 h-8 w-20 rounded"></div>
        <div class="bg-primary-lighter h-3 w-32 rounded"></div>
      </div>
    </div>

    <!-- Forecast cards grid -->
    <div v-else class="grid grid-cols-1 gap-4">
      <ModuleForecastCard
        v-for="forecast in forecasts"
        :key="forecast.module"
        :forecast="forecast"
      />
    </div>
  </Card>
</template>

<script setup lang="ts">
/**
 * Grid of module forecast cards showing remaining capacity per module.
 */
import Card from '@/components/ui/Card.vue'
import type { ModuleForecast } from '@/types/credits'
import ModuleForecastCard from './ModuleForecastCard.vue'

interface Props {
  forecasts: ModuleForecast[]
  loading?: boolean
}

withDefaults(defineProps<Props>(), {
  loading: false,
})
</script>
