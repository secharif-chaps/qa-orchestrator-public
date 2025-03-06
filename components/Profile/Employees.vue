<template>
  <Card>
    <div class="text-center space-y-2">
      <div
        class="bg-orange-100 lg:w-1/2 text-orange-500 mx-auto px-2 py-2 rounded"
      >
        <span v-if="hasPropertyBeenUpdated('profile.employee_count')">
          {{ company?.profile?.employee_count }}
        </span>
        <span
          v-else
          class="text-gray-500"
          >...</span
        >
      </div>
      <div>Number of employees</div>
    </div>
  </Card>
</template>

<script lang="ts" setup>
const { company, hasPropertyBeenUpdated } = useCompanyData()
</script>
