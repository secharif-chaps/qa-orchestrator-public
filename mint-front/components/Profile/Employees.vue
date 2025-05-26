<template>
  <Card>
    <div class="text-center space-y-2">
      <div
        class="bg-orange-100 lg:w-2/3 text-orange-500 mx-auto px-2 py-2 rounded"
      >
        <span >
          {{ getSourcedValue(company?.profile?.employeeCount) ?? 'Not found' }}
        </span>
      </div>
      <div>Number of employees</div>
      <Source
        v-if="company?.profile?.employeeCount"
        :sourced-value="company?.profile?.employeeCount"
      />
    </div>
  </Card>
</template>

<script lang="ts" setup>
const { company, getSourcedValue } = useCompanyData()
</script>
