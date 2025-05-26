<template>
  <div class="space-y-6">
    <div>
      <OButton
        type="secondary"
        icon="fa-arrow-left"
        @click="$router.push(`/cards/${companyName}`)"
      >
        Back
      </OButton>
    </div>
    <div class="flex items-center justify-between">
      <div class="flex space-x-4 items-center">
        <OIcon
          icon="fa-chart-line"
          type="secondary"
        ></OIcon>
        <h1 class="text-3xl">Financials</h1>
      </div>
    </div>
    
    <span>todo</span>
  </div>
</template>

<script lang="ts" setup>
import { OButton, OIcon } from '@owlint/feathers-vue'

// Set page metadata
useHead({
  title: 'Mint - Financials',
  meta: [{ name: 'description', content: 'Company Financial Information' }],
})

const { companyName, fetchCompany } = useCompanyData()

onMounted(async () => {
  await fetchCompany()
})
</script>
