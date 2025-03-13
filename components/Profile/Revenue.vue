<template>
  <Card>
    <div class="text-center space-y-2">
      <div
        class="bg-purple-100 lg:w-2/3 text-purple-500 mx-auto px-2 py-2 rounded"
      >
        <span v-if="hasPropertyBeenUpdated('profile.revenue')">
          {{ getSourcedValue(company?.profile?.revenue) ?? 'Not found' }}
        </span>

        <span
          v-else
          class="text-gray-500"
          >...</span
        >
      </div>

      <div>Revenue</div>
      <Source
        v-if="hasPropertyBeenUpdated('profile.revenue')"
        :sourced-value="company?.profile?.revenue"
      />
    </div>
  </Card>
</template>

<script lang="ts" setup>
const { company, hasPropertyBeenUpdated, getSourcedValue } = useCompanyData()
</script>
