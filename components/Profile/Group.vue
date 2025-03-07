<template>
  <Card class="grow">
    <div class="grid grid-cols-2">
      <div>Group</div>
      <!-- Group name - individual property loading -->
      <div
        v-if="hasPropertyBeenUpdated('profile.group_name')"
        class="text-secondary"
      >
        {{ getSourcedValue(company?.profile?.group_name) ?? 'Not found' }}
        <Source :item="company?.profile?.group_name" />
      </div>
      <div
        v-else
        class="text-secondary italic"
      >
        Loading...
      </div>
    </div>
  </Card>
</template>

<script lang="ts" setup>
const { company, companyName, hasPropertyBeenUpdated, getSourcedValue } =
  useCompanyData()
</script>
