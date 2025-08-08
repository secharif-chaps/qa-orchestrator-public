<template>
  <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
    <ActionCard
      title="Search Companies"
      description="Find and explore companies"
      icon="search"
      color="blue"
      to="/search"
    />

    <ActionCard
      title="All Companies"
      description="View companies database"
      icon="building"
      color="green"
      to="/companies"
    />

    <ActionCard
      title="Settings"
      description="Configure preferences"
      icon="cog"
      color="orange"
      to="/account/appearance"
    />
  </div>
</template>

<script setup lang="ts">
import ActionCard from './ActionCard.vue'

// This component provides a standardized set of quick actions for the dashboard
// It can be easily extended by adding more ActionCard components
</script>
