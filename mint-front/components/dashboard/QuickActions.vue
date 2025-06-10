<template>
  <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
    <DashboardActionCard
      title="Search Companies"
      description="Find and explore companies"
      icon="search"
      color="blue"
      to="/search"
    />
    
    <DashboardActionCard
      title="All Companies"
      description="View companies database"
      icon="building"
      color="green"
      to="/companies"
    />
    
    <DashboardActionCard
      title="My Profile"
      description="Manage your account"
      icon="user"
      color="purple"
      to="/profile"
    />
    
    <DashboardActionCard
      title="Settings"
      description="Configure preferences"
      icon="cog"
      color="orange"
      to="/settings"
    />
  </div>
</template>

<script setup>
// This component provides a standardized set of quick actions for the dashboard
// It can be easily extended by adding more ActionCard components
</script>