<template>
  <Card class="grow">
    <div class="grid grid-cols-2">
      <div>Line of business</div>
      <!-- Business line - individual property loading -->
      <div
        v-if="hasPropertyBeenUpdated('profile.business_line')"
        class="text-secondary"
      >
        {{ getSourcedValue(company?.profile?.business_line) ?? 'Not found' }}
        <Source :sourced-value="company?.profile?.business_line" />
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
const { company, hasPropertyBeenUpdated, getSourcedValue } = useCompanyData()
</script>
