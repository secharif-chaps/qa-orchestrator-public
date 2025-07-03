<template>
  <div v-if="stats" class="grid grid-cols-1 md:grid-cols-3 gap-6">
    <DashboardStatCard
      title="Total Companies"
      :value="stats.total ? stats.total : 0"
      icon="building"
      color="indigo"
      :loading="loading"
    />
    
    <DashboardStatCard

      title="Active Tasks"
      :value="stats.activeTasks ? stats.activeTasks : 0"
      icon="tasks"
      color="yellow"
      :loading="loading"
    />
    
    <DashboardStatCard
        title="Recent Updates"
      :value="stats.recentUpdates ? stats.recentUpdates : 0"
      subtitle="Last 24 hours"
      icon="clock"
      color="green"
      :loading="loading"
    />
  </div>
</template>

<script setup>
const props = defineProps({
  stats: {
    type: Object,
    default: () => ({
      total: 0,
      activeTasks: 0,
      recentUpdates: 0
    })
  },
  loading: {
    type: Boolean,
    default: false
  }
})
</script>