<template>
  <Card padding="p-6">
    <h3 class="text-lg font-semibold">
      {{ $t('credits.forecast.title', 'Capacité restante') }}
    </h3>

    <!-- Loading state -->
    <div v-if="loading" class="grid grid-cols-1 gap-4">
      <div
        v-for="n in 3"
        :key="n"
        class="bg-base-200 rounded-lg p-4 animate-pulse"
      >
        <div class="flex items-center gap-3 mb-3">
          <div class="w-10 h-10 rounded-lg bg-base-300"></div>
          <div class="h-4 bg-base-300 rounded w-24"></div>
        </div>
        <div class="h-8 bg-base-300 rounded w-20 mb-2"></div>
        <div class="h-3 bg-base-300 rounded w-32"></div>
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
import type { ModuleForecast } from '@/types/credits'
import Card from '@/components/ui/Card.vue'
import ModuleForecastCard from './ModuleForecastCard.vue'

interface Props {
  forecasts: ModuleForecast[]
  loading?: boolean
}

withDefaults(defineProps<Props>(), {
  loading: false,
})
</script>
