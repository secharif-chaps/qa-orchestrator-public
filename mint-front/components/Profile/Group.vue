<template>
  <Card class="grow">
    <div class="flex flex-col gap-2">
      <h4>Group</h4>
      <!-- Group name - individual property loading -->
      <div>
        <p class="text-secondary">
          {{ getSourcedValue(company?.profile?.groupName) ?? 'Not found' }}
        </p>
      </div>
      <Source :sourced-value="company?.profile?.groupName" />
    </div>
  </Card>
</template>

<script lang="ts" setup>
const { company, getSourcedValue } = useCompanyData()
</script>
