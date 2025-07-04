<template>
  <Card class="grow">
    <div class="flex flex-col gap-4 ">
      <div>Group</div>
      <!-- Group name - individual property loading -->
      <div

        class="text-secondary dark:text-slate-400"
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
