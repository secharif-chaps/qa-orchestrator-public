<template>
  <div v-if="stats" class="grid grid-cols-1 gap-6 md:grid-cols-3">
    <StatCard
      :title="$t('dashboard.stats.totalCompanies')"
      :value="stats.total ? stats.total : 0"
      icon="building"
      color="indigo"
      :loading="loading"
    />

    <StatCard
      :title="$t('dashboard.stats.activeTasks')"
      :value="stats.activeTasks ? stats.activeTasks : 0"
      icon="tasks"
      color="yellow"
      :loading="loading"
    />

    <StatCard
      :title="$t('dashboard.stats.recentUpdates')"
      :value="stats.recentUpdates ? stats.recentUpdates : 0"
      :subtitle="$t('dashboard.stats.last24Hours')"
      icon="clock"
      color="green"
      :loading="loading"
    />
  </div>
</template>

<script setup lang="ts">
import StatCard from './StatCard.vue'

defineProps({
  stats: {
    type: Object,
    default: () => ({
      total: 0,
      activeTasks: 0,
      recentUpdates: 0,
    }),
  },
  loading: {
    type: Boolean,
    default: false,
  },
})
</script>
