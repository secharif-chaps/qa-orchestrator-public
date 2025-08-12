<template>
  <div class="flex flex-col gap-4">
    <!-- No products state -->
    <ProductsEmptyState v-if="!products || Object.keys(products).length === 0" type="no-data" />

    <!-- Main content -->
    <div v-else class="space-y-6">
      <!-- AI Insights Section -->
      <div v-if="productsInsights" class="relative bg-gradient-to-br from-primary/5 via-primary/3 to-primary/5 rounded-lg p-6 border border-primary/20 shadow-sm">
        <!-- AI Badge -->
        <div class="absolute top-4 right-4">
          <Badge
            variant="primary"
            icon="fa fa-sparkles"
            label="AI"
            size="xs"
            rounded
          />
        </div>
        
        <h2 class="text-xl font-semibold mb-4 flex items-center gap-2 text-primary">
          <i class="fa fa-brain"></i>
          <span>{{ $t('products.insights.title', 'Product Insights') }}</span>
        </h2>
        
        <div class="bg-white/50 dark:bg-slate-800/30 rounded-lg p-4 backdrop-blur-sm border border-primary/10">
          <div class="prose prose-sm max-w-none dark:prose-invert text-slate-700 dark:text-slate-300 leading-relaxed">
            {{ productsInsights }}
          </div>
        </div>
      </div>

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
      <div v-else-if="viewMode === 'list'" class="bg-bg1 rounded-lg p-4">
        <div class="space-y-4">
          <ProductListItem
            v-for="(productList, category) in filteredProducts"
            :key="category"
            :category="category"
            :product-list="productList"
          />
        </div>
      </div>

      <!-- No Results State -->
      <ProductsEmptyState
        v-if="Object.keys(filteredProducts).length === 0 && searchQuery"
        type="no-results"
        :search-query="searchQuery"
      />
    </div>
  </div>
</template>

<script lang="ts" setup>
import ProductsHeader from '@/components/company/products/ProductsHeader.vue'
import ProductGridItem from '@/components/company/products/ProductGridItem.vue'
import ProductListItem from '@/components/company/products/ProductListItem.vue'
import ProductsEmptyState from '@/components/company/products/ProductsEmptyState.vue'
import Badge from '@/components/ui/Badge.vue'
import { useRoute } from 'vue-router'
import { computed, ref } from 'vue'
import { useQuery } from '@pinia/colada'
import { companyByIdQuery } from '@/queries/companies'
import CompanyCard from '@/components/company/CompanyCard.vue'

const route = useRoute()

const companyId = computed(() => route.params.companyId as string)

// Use the company data composable
const { data: company } = useQuery(companyByIdQuery, () => ({
  id: companyId.value,
}))

// Reactive state
const viewMode = ref<'grid' | 'list'>('grid')
const searchQuery = ref('')
const selectedCategory = ref<string | null>(null)
const showAllProducts = ref<Record<string, boolean>>({})

// Computed properties
const products = computed(() => {
  return company.value?.products?.categories || {}
})

const totalProductCount = computed(() => {
  return Object.values(products.value).reduce(
    (total: number, productList: string[]) => total + productList.length,
    0,
  )
})

const productsInsights = computed(() => {
  return company.value?.products?.insights
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
    const result: Record<string, string[]> = {}

    Object.entries(filtered).forEach(([category, productList]: [string, string[]]) => {
      const matchingProducts = productList.filter(
        (product: string) =>
          product.toLowerCase().includes(query) || category.toLowerCase().includes(query),
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
