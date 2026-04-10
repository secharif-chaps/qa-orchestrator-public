<template>
  <div class="flex flex-col gap-4">
    <!-- Loading State -->
    <SectionLoadingState
      v-if="company && (task?.status === 'pending' || task?.status === 'running')"
    />

    <!-- Error State -->
    <SectionErrorState v-else-if="company && task?.status === 'error'" :task="task" />

    <!-- No Data State -->
    <NoData v-else-if="!hasProductsData">
      <p class="text-neutral-black-font text-lg font-medium">
        {{ $t('screen.profile.sections.products.noData') }}
      </p>
    </NoData>

    <!-- Main content -->
    <div v-else class="space-y-6">
      <!-- AI Insights Section -->
      <ChapseAlert
        v-if="productsInsights"
        variant="mage"
        :title="$t('screen.products.insights.title')"
      >
        {{ productsInsights }}
      </ChapseAlert>

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
      <div v-if="viewMode === 'grid'" class="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3">
        <ProductGridItem
          v-for="(productList, category) in filteredProducts"
          :key="category"
          :category="category"
          :product-list="productList"
          @toggle-show-all="toggleShowAllProducts"
        />
      </div>

      <!-- Products List View -->
      <div v-else-if="viewMode === 'list'" class="rounded-sm bg-white p-4">
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
      <NoData v-if="Object.keys(filteredProducts).length === 0 && searchQuery">
        <p class="text-neutral-black-font text-lg font-medium">
          {{ $t('screen.products.noResults') }}
        </p>
      </NoData>
    </div>
  </div>
</template>

<script lang="ts" setup>
import ProductGridItem from '@/components/company/products/ProductGridItem.vue'
import ProductListItem from '@/components/company/products/ProductListItem.vue'
import ProductsHeader from '@/components/company/products/ProductsHeader.vue'
import SectionErrorState from '@/components/company/SectionErrorState.vue'
import SectionLoadingState from '@/components/company/SectionLoadingState.vue'
import ChapseAlert from '@/components/ui/ChapseAlert.vue'
import NoData from '@/components/ui/NoData.vue'
import { companyByIdQuery } from '@/queries/companies'
import { companyTasksQuery } from '@/queries/tasks'
import { useQuery } from '@pinia/colada'
import { computed, inject, ref, type Ref } from 'vue'
import { useRoute } from 'vue-router'

const route = useRoute('/folders/[folderId]/companies/[companyId]/products')

const companyId = computed(() => route.params.companyId)

const { data: tasks } = useQuery(() =>
  companyTasksQuery({
    companyId: companyId.value,
  }),
)

const task = computed(() => tasks.value?.find((t) => t.type === 'products'))

const selectedLanguage = inject<Ref<string | undefined>>('selectedLanguage', ref(undefined))
// Use the company data composable
const { data: company } = useQuery(
  // Task data is kept fresh via SSE (Server-Sent Events) in useTaskEvents composable.
  // No polling needed - cache is invalidated automatically when tasks update.
  () =>
    companyByIdQuery({
      id: companyId.value,
      language: selectedLanguage.value,
    }),
)

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

const hasProductsData = computed(() => {
  const productsData = company.value?.products
  if (!productsData) return false

  // Check if there's any meaningful content
  return !!(
    productsData.insights ||
    (productsData.categories && Object.keys(productsData.categories).length > 0)
  )
})
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
