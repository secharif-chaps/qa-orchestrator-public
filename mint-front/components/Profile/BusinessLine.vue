<template>
  <Card class="grow">
    <div class="flex flex-col gap-4 ">
      <div>Line of business</div>
      <!-- Business line - individual property loading -->
      <div
        class="text-secondary"
      >
        {{ getSourcedValue(company?.profile?.businessLine) ?? 'Not found' }}
        <Source :sourced-value="company?.profile?.businessLine" />
      </div>
   
    </div>
  </Card>
</template>

<script lang="ts" setup>
const { company, getSourcedValue } = useCompanyData()
</script>
