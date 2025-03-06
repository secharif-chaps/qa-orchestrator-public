<template>
  <Card>
    <div class="text-center space-y-2">
      <div
        class="bg-purple-100 lg:w-1/2 text-purple-500 mx-auto px-2 py-2 rounded"
      >
        <span v-if="hasPropertyBeenUpdated('profile.revenue')">
          {{ company?.profile?.revenue }}
        </span>
        <span
          v-else
          class="text-gray-500"
          >...</span
        >
      </div>
      <div>Revenue</div>
    </div>
  </Card>
</template>

<script lang="ts" setup>
const { company, hasPropertyBeenUpdated } = useCompanyData()
</script>
