<template>
  <Card class="grow">
    <div class="grid grid-cols-2">
      <div>Group</div>
      <!-- Group name - individual property loading -->
      <div

        class="text-secondary"
      >
        {{ getSourcedValue(company?.profile?.groupName) ?? 'Not found' }}
        <Source :sourced-value="company?.profile?.groupName" />
      </div>
     
    </div>
  </Card>
</template>

<script lang="ts" setup>
const { company, getSourcedValue } = useCompanyData()
</script>
