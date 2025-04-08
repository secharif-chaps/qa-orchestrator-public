<template>
  <LayoutsCompanyCard
    name="main"
    title="Products & Services"
    icon="fa-box"
  >
    <!-- Actions slot -->
    <template #actions>
      <OButton
        type="secondary"
        icon="fa-refresh"
        :loading="productsPending"
        @click="refreshProducts"
      >
        Refresh Products
      </OButton>
    </template>

    <!-- Loading slot -->
    <template #loading>
      <OAlert
        v-if="productsPending"
        message="Loading products information..."
        title="Please wait"
        description="Products information will be displayed here once available."
        icon="fa-spinner fa-spin"
        color="blue"
      >
      </OAlert>
    </template>

    <!-- Main content -->
    <div
      v-if="!productsPending && hasProducts"
      class="card grid @min-6xl:grid-cols-3 @max-6xl:grid-cols-2 gap-4"
    >
      <div
        v-for="(productList, category) in products"
        :key="category"
        class="bg-bg3 p-4 border border-border-2 rounded-lg grid grid-cols-2"
      >
        <p class="mb-4 capitalize">{{ category }}</p>
        <ul class="list-disc pl-4 text-secondary">
          <li
            v-for="product in productList"
            :key="product"
            class="mb-1"
          >
            {{ product }}
          </li>
        </ul>
      </div>
    </div>

    <!-- No products state -->
    <OAlert
      v-else-if="!productsPending"
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
  meta: [{ name: 'description', content: 'Company Products and Services' }],
})

const { companyName, hasPropertyBeenUpdated } = useCompanyData()
const { productsPending, findProducts } = useAgent()

const companyStore = useCompanyStore()

const hasProducts = computed(() => {
  const products = companyStore.companies[companyName.value]?.products
  return products
})

const products = computed(() => {
  return companyStore.companies[companyName.value]?.products || []
})

// Function to refresh products data
const refreshProducts = async () => {
  if (companyName.value) {
    await findProducts(companyName.value)
  }
}
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
