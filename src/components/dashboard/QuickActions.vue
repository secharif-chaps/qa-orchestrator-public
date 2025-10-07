<template>
  <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
    <ActionCard
      :title="$t('dashboard.quickActions.searchCompanies.title', 'Search Companies')"
      :description="$t('dashboard.quickActions.searchCompanies.description', 'Find and explore companies')"
      icon="search"
      color="blue"
      to="/search"
    />

    <ActionCard
      :title="$t('dashboard.quickActions.allCompanies.title', 'All Companies')"
      :description="$t('dashboard.quickActions.allCompanies.description', 'View companies database')"
      icon="building"
      color="green"
      to="/companies"
    />

    <ActionCard
      :title="$t('dashboard.quickActions.settings.title', 'Settings')"
      :description="$t('dashboard.quickActions.settings.description', 'Configure preferences')"
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
