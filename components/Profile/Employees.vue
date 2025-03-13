<template>
  <Card>
    <div class="text-center space-y-2">
      <div
        class="bg-orange-100 lg:w-2/3 text-orange-500 mx-auto px-2 py-2 rounded"
      >
        <span v-if="hasPropertyBeenUpdated('profile.employee_count')">
          {{ getSourcedValue(company?.profile?.employee_count) ?? 'Not found' }}
        </span>
        <span
          v-else
          class="text-gray-500"
          >...</span
        >
      </div>
      <div>Number of employees</div>
      <Source
        v-if="hasPropertyBeenUpdated('profile.employee_count')"
        :sourced-value="company?.profile?.employee_count"
      />
    </div>
  </Card>
</template>

<script lang="ts" setup>
const { company, hasPropertyBeenUpdated, getSourcedValue } = useCompanyData()
</script>
