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
    <template v-else>
      <!-- AI Insights Section -->
      <ChapseAlert
        v-if="productsInsights"
        variant="mage"
        :title="$t('screen.products.insights.title')"
      >
        {{ productsInsights }}
      </ChapseAlert>

      <!-- Products Card -->
      <div class="shadow-2 p-xl border-grey-200 space-y-md rounded-xl border">
        <ProductsHeader
          v-model:search-query="searchQuery"
          :total-product-count="totalProductCount"
          :category-count="categoryCount"
        />

        <!-- Accordion or No Results -->
        <template v-if="paginatedCategories.length > 0">
          <ProductCategoryAccordion
            :categories="paginatedCategories"
            :partner-brands="company?.products?.partnerBrands"
            :private-labels="company?.products?.privateLabels"
          />

          <PaginationComponent
            v-if="paginationMeta && paginationMeta.last_page > 1"
            v-model:current-page="currentPage"
            :meta="paginationMeta"
            item-name="categories"
            @update-per-page="perPage = $event"
          />
        </template>

        <NoData v-else-if="searchQuery">
          <p class="text-neutral-black-font text-lg font-medium">
            {{ $t('screen.products.noResults') }}
          </p>
        </NoData>
      </div>
    </template>
  </div>
</template>

<script lang="ts" setup>
import ProductCategoryAccordion from '@/components/company/products/ProductCategoryAccordion.vue'
import ProductsHeader from '@/components/company/products/ProductsHeader.vue'
import SectionErrorState from '@/components/company/SectionErrorState.vue'
import SectionLoadingState from '@/components/company/SectionLoadingState.vue'
import ChapseAlert from '@/components/ui/ChapseAlert.vue'
import NoData from '@/components/ui/NoData.vue'
import PaginationComponent from '@/components/ui/Pagination.vue'
import { companyByIdQuery } from '@/queries/companies'
import { companyTasksQuery } from '@/queries/tasks'
import type { PaginationMeta } from '@/types/pagination'
import { useQuery } from '@pinia/colada'
import { computed, inject, ref, watch, type Ref } from 'vue'
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
const { data: company } = useQuery(() =>
  companyByIdQuery({
    id: companyId.value,
    language: selectedLanguage.value,
  }),
)

// Reactive state
const searchQuery = ref('')
const currentPage = ref(1)
const perPage = ref(10)

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

const categoryCount = computed(() => Object.keys(products.value).length)

const productsInsights = computed(() => {
  return company.value?.products?.insights
})

const filteredCategories = computed((): [string, string[]][] => {
  const entries = Object.entries(products.value)

  if (!searchQuery.value.trim()) return entries

  const query = searchQuery.value.toLowerCase().trim()
  const result: [string, string[]][] = []

  for (const [category, productList] of entries) {
    const matchingProducts = (productList as string[]).filter(
      (product: string) =>
        product.toLowerCase().includes(query) || category.toLowerCase().includes(query),
    )
    if (matchingProducts.length > 0) {
      result.push([category, matchingProducts])
    }
  }

  return result
})

// Reset page when search or page size changes
watch([searchQuery, perPage], () => {
  currentPage.value = 1
})

const paginatedCategories = computed((): [string, string[]][] => {
  const start = (currentPage.value - 1) * perPage.value
  const end = start + perPage.value
  return filteredCategories.value.slice(start, end)
})

const paginationMeta = computed((): PaginationMeta | null => {
  const total = filteredCategories.value.length
  if (total === 0) return null
  return {
    total,
    per_page: perPage.value,
    current_page: currentPage.value,
    last_page: Math.ceil(total / perPage.value),
  }
})

const hasProductsData = computed(() => {
  const productsData = company.value?.products
  if (!productsData) return false

  return !!(
    productsData.insights ||
    (productsData.categories && Object.keys(productsData.categories).length > 0)
  )
})
</script>
