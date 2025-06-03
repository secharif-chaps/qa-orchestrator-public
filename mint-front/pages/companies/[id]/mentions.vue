<template>
  <LayoutsCompanyCard title="Mentions" icon="fa-quote-left">
    <div class="flex flex-col gap-4">
    
    <!-- Task state -->
    <TaskState
      v-if="companyId"
      :company-id="companyId"
      :required-task-types="[]"
      loading-title="Not implemented yet"
      loading-description="This feature is not implemented yet."
    />
    <div>
      <h1>comming soon</h1>
    </div>
  </div>
  </LayoutsCompanyCard>
</template>

<script lang="ts" setup>
import TaskState from '~/components/TaskState.vue'

// Set page metadata
useHead({
  title: 'Mint - Mentions',
  meta: [{ name: 'description', content: 'Company Mentions and References' }],
})

const { companyId, fetchCompany } = useCompanyData()

onMounted(async () => {
  await fetchCompany()
})
</script>
