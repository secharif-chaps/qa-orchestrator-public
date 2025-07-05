<template>
  <Card class="grow">
    <div class="flex flex-col gap-2">
      <h4>Line of business</h4>
      <!-- Business line - individual property loading -->
      <div class="text-sm flex flex-col gap-2">
        <p class="text-secondary">
          {{ getSourcedValue(company?.profile?.businessLine) ?? 'Not found' }}
        </p>

      </div>
          <Source :sourced-value="company?.profile?.businessLine" />
   
    </div>
  </Card>
</template>

<script lang="ts" setup>
const { company, getSourcedValue } = useCompanyData()
</script>
