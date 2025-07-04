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
      <ProductsEmptyState 
        v-if="!products || Object.keys(products).length === 0"
        type="no-data"
      />

      <!-- Main content -->
      <div v-else class="space-y-6">
        <!-- Products Overview Header -->
        <ProductsHeader
          :total-product-count="totalProductCount"
          :category-count="Object.keys(products).length"
          :categories="Object.keys(products)"
          :view-mode="viewMode"
          :search-query="searchQuery"
          :selected-category="selectedCategory"
          @toggle-view-mode="toggleViewMode"
          @update-search="searchQuery = $event"
          @select-category="selectedCategory = $event"
        />

        <!-- Products Grid View -->
        <div v-if="viewMode === 'grid'" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
          <ProductGridItem
            v-for="(productList, category) in filteredProducts"
            :key="category"
            :category="category"
            :product-list="productList"
            @toggle-show-all="toggleShowAllProducts"
          />
        </div>

        <!-- Products List View -->
        <Card v-else-if="viewMode === 'list'">
          <div class="space-y-4">
            <ProductListItem
              v-for="(productList, category) in filteredProducts"
              :key="category"
              :category="category"
              :product-list="productList"
            />
          </div>
        </Card>

        <!-- No Results State -->
        <ProductsEmptyState
          v-if="Object.keys(filteredProducts).length === 0 && searchQuery"
          type="no-results"
          :search-query="searchQuery"
        />
      </div>
    </div>
  </LayoutsCompanyCard>
</template>

<script lang="ts" setup>
import TaskState from '~/components/TaskState.vue'
import ProductsHeader from '~/components/products/ProductsHeader.vue'
import ProductGridItem from '~/components/products/ProductGridItem.vue'
import ProductListItem from '~/components/products/ProductListItem.vue'
import ProductsEmptyState from '~/components/products/ProductsEmptyState.vue'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()

// Set page metadata
useHead({
  title: `Mint - ${t('products.title')}`,
  meta: [{ name: 'description', content: t('products.title') }]
})

const { company, companyId, fetchCompany } = useCompanyData()

// Reactive state
const viewMode = ref<'grid' | 'list'>('grid')
const searchQuery = ref('')
const selectedCategory = ref<string | null>(null)
const showAllProducts = ref<Record<string, boolean>>({})

onMounted(async () => {
  if (!company.value) {
    await fetchCompany()
  }
})

// Computed properties
const products = computed(() => {
  return company.value?.products?.categories || {}
})

const totalProductCount = computed(() => {
  return Object.values(products.value).reduce((total: number, productList: any) => total + productList.length, 0)
})

const filteredProducts = computed(() => {
  let filtered = { ...products.value }
  
  // Filter by selected category
  if (selectedCategory.value) {
    filtered = { [selectedCategory.value]: filtered[selectedCategory.value] }
  }
  
  // Filter by search query
  if (searchQuery.value.trim()) {
    const query = searchQuery.value.toLowerCase().trim()
    const result: Record<string, any[]> = {}
    
    Object.entries(filtered).forEach(([category, productList]: [string, any]) => {
      const matchingProducts = productList.filter((product: string) => 
        product.toLowerCase().includes(query) || 
        category.toLowerCase().includes(query)
      )
      if (matchingProducts.length > 0) {
        result[category] = matchingProducts
      }
    })
    
    return result
  }
  
  return filtered
})

// Methods
const toggleViewMode = () => {
  viewMode.value = viewMode.value === 'grid' ? 'list' : 'grid'
}

const toggleShowAllProducts = (category: string) => {
  showAllProducts.value[category] = !showAllProducts.value[category]
}
</script>

<style scoped>
/* Product card animations */
.product-card {
  transition: all 0.3s ease;
}

.product-card:hover {
  transform: translateY(-2px);
}

/* Search and filter animations */
.filter-enter-active,
.filter-leave-active {
  transition: all 0.3s ease;
}

.filter-enter-from,
.filter-leave-to {
  opacity: 0;
  transform: translateY(-10px);
}

/* Category pill animations */
.category-pill {
  transition: all 0.2s ease;
}

.category-pill:hover {
  transform: translateY(-1px);
}
</style>