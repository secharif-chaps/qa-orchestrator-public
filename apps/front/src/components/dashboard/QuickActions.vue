<template>
  <div class="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3">
    <ActionCard
      :title="$t('dashboard.quickActions.searchCompanies.title')"
      :description="$t('dashboard.quickActions.searchCompanies.description')"
      icon="search"
      color="blue"
      to="/search"
    />

    <ActionCard
      :title="$t('dashboard.quickActions.allCompanies.title')"
      :description="$t('dashboard.quickActions.allCompanies.description')"
      icon="building"
      color="green"
      to="/companies"
    />

    <ActionCard
      :title="$t('dashboard.quickActions.settings.title')"
      :description="$t('dashboard.quickActions.settings.description')"
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
