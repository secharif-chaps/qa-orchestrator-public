<template>
  <LayoutsCompanyCard name="main" title="Products & Services" icon="fa-box">
    <!-- Actions slot -->
    <template #actions>
      <OButton @click="refreshProducts" type="secondary" icon="fa-refresh">Refresh</OButton>
    </template>

    <!-- Loading slot -->
    <template #loading>
      <div class="flex flex-col gap-2">
        <OAlert
          v-if="company?.pendingStates?.products?.pending"
          message="Loading products information..."
          title="Please wait"
          description="Products information will be displayed here once available."
          icon="fa-spinner fa-spin"
          color="blue"
        >
        </OAlert>

        <OAlert
          v-if="company?.products?.insights"
          title="Products Insights"
          :description="company?.products?.insights"
          icon="fa-magic"
          color="blue"
        >
        </OAlert>

        <OAlert
          v-if="company?.pendingStates?.products?.error"
          title="Oops, something went wrong"
          description="Please try again later or contact support"
          icon="fa-exclamation-triangle"
          color="red"
        >
        </OAlert>
      </div>
    </template>

    <!-- Main content -->
    <div
      v-if="!company?.pendingStates?.products?.pending && products"
      class="card grid @min-6xl:grid-cols-3 @max-6xl:grid-cols-2 gap-4"
    >
      <div
        v-for="(productList, category) in products"
        :key="category"
        class="bg-bg3 p-4 border border-border-2 rounded-lg grid grid-cols-2"
      >
        <p class="mb-4 capitalize">{{ category }}</p>
        <ul class="list-disc pl-4 text-secondary">
          <li v-for="product in productList" :key="product" class="mb-1">
            {{ product }}
          </li>
        </ul>
      </div>
    </div>

    <!-- No products state -->
    <OAlert
      v-else-if="!company?.pendingStates?.products?.pending && !products"
      message="No products information available yet."
      title="No Data"
      description="Products information will be displayed here once available."
      icon="fa-box"
      color="gray"
    >
    </OAlert>
  </LayoutsCompanyCard>
</template>

<script lang="ts" setup>
import { OAlert, OButton } from '@owlint/feathers-vue'
import { useCompanyStore } from '~/stores/company'

// Set page metadata
useHead({
  title: 'Mint - Company Products',
  meta: [{ name: 'description', content: 'Company Products and Services' }]
})

const { company, getSourcedValue } = useCompanyData()

const companyStore = useCompanyStore()

const refreshProducts = () => {
  if (company.value?.name && company.value?.website) {
    companyStore.startQuery(company.value.name, company.value.website, 'products')
  }
}

const products = computed(() => {
  return company.value?.products?.categories || []
})
</script>

<style>
.container {
  container-type: inline-size;
  container-name: products;
}

@container products (max-width: 1200px) {
  .card {
    grid-template-columns: repeat(2, 1fr);
  }
}

@container products (max-width: 768px) {
  .card {
    grid-template-columns: 1fr;
  }
}
</style>
