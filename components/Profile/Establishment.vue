<template>
  <Card>
    <div class="text-center space-y-2">
      <div
        class="bg-emerald-100 lg:w-1/2 text-primary mx-auto px-2 py-2 rounded"
      >
        <span v-if="hasPropertyBeenUpdated('profile.establishment_year')">
          {{ company?.profile?.establishment_year }}
        </span>
        <span
          v-else
          class="text-gray-500"
          >...</span
        >
      </div>
      <div>year of establishment</div>
    </div>
  </Card>
</template>

<script lang="ts" setup>
const { company, hasPropertyBeenUpdated } = useCompanyData()
</script>
