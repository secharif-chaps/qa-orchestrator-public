<template>
  <div v-if="stats" class="grid grid-cols-1 md:grid-cols-3 gap-6">
    <StatCard
      :title="$t('dashboard.stats.totalCompanies', 'Total Companies')"
      :value="stats.total ? stats.total : 0"
      icon="building"
      color="indigo"
      :loading="loading"
    />

    <StatCard
      :title="$t('dashboard.stats.activeTasks', 'Active Tasks')"
      :value="stats.activeTasks ? stats.activeTasks : 0"
      icon="tasks"
      color="yellow"
      :loading="loading"
    />

    <StatCard
      :title="$t('dashboard.stats.recentUpdates', 'Recent Updates')"
      :value="stats.recentUpdates ? stats.recentUpdates : 0"
      :subtitle="$t('dashboard.stats.last24Hours', 'Last 24 hours')"
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
