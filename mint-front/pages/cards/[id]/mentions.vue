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
          icon="fa-quote-left"
          type="secondary"
        ></OIcon>
        <h1 class="text-3xl">Mentions</h1>
      </div>
    </div>
    
    <span>todo</span>
  </div>
</template>

<script lang="ts" setup>
import { OButton, OIcon } from '@owlint/feathers-vue'

// Set page metadata
useHead({
  title: 'Mint - Mentions',
  meta: [{ name: 'description', content: 'Company Mentions and References' }],
})

const { companyName, fetchCompany } = useCompanyData()

onMounted(async () => {
  await fetchCompany()
})
</script>
