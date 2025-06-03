<template>
  <LayoutsCompanyCard v-if="company" name="main" :title="$t('products.title')" icon="fa-box">
    <div class="flex flex-col gap-4">

    <!-- Task state -->
    <TaskState
      v-if="companyId"
      :company-id="companyId"
      :required-task-types="['products']"
      :loading-title="$t('products.loading.title')"
      :loading-description="$t('products.loading.description')"
    />

       <!-- No products state -->
       <div v-if="!products || products.length === 0" class="card">
        <div class="text-center py-8">
          <div class="text-5xl text-slate-300 mb-4">
            <i class="fa fa-box"></i>
          </div>
          <h3 class="text-xl font-semibold mb-2">{{ $t('products.noData.title') }}</h3>
          <p class="text-slate-500 mb-6">
            <span >
              {{ $t('products.noData.description') }}
            </span>
          </p>
        </div>
     </div>

    <!-- Main content -->
    <div
      v-else
      class="card grid @min-6xl:grid-cols-3 @max-6xl:grid-cols-2 gap-4"
    >
      <div
        v-for="(productList, category) in products"
        :key="category"
        class="bg-bg3 p-4 border border-border-2 rounded-lg "
      >
        <p class="mb-4 capitalize">{{ category }}</p>
        <ul class="list-disc pl-4 text-secondary">
          <li v-for="product in productList" :key="product" class="mb-1">
            {{ product }}
          </li>
        </ul>
      </div>
    </div>
  </div>
  </LayoutsCompanyCard>
</template>

<script lang="ts" setup>
import TaskState from '~/components/TaskState.vue'
import type { TaskResponse } from '~/types/task'

// Set page metadata
useHead({
  title: 'Mint - Company Products',
  meta: [{ name: 'description', content: 'Company Products and Services' }]
})

const { company, companyId, fetchCompany } = useCompanyData()

onMounted(async () => {
  if (!company.value) {
    await fetchCompany()
  }
})

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
