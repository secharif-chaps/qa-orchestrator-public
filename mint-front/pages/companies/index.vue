<template>
  <div class="max-w-7xl mx-auto space-y-6">
    <!-- Companies Header -->
    <CompaniesHeader
      :search-query="searchQuery"
      :view-mode="viewMode"
      :companies-count="paginationMeta?.total || companies.length"
      @update-search="searchQuery = $event"
      @toggle-view="toggleViewMode"
    />

    <!-- Empty State when no companies -->
    <CompaniesEmptyState
      v-if="status === 'success' && !error && companies.length === 0"
      type="no-data"
    />

    <!-- Main Content -->
    <div v-else-if="!error">
      <!-- Companies Grid View -->
      <div v-if="viewMode === 'grid'" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <CompanyCardItem
          v-for="company in filteredCompanies"
          :key="company.id"
          :company="company"
          @view-company="$router.push(`/companies/${$event}`)"
          @delete-company="confirmDelete"
        />
      </div>

      <!-- Companies Table View -->
      <CompanyTableView
        v-else
        :companies="companies"
        :filtered-companies="filteredCompanies"
        :loading="status === 'pending'"
        :error="error"
        @view-company="$router.push(`/companies/${$event}`)"
        @delete-company="confirmDelete"
      />

      <!-- No Results State -->
      <CompaniesEmptyState
        v-if="filteredCompanies.length === 0 && searchQuery"
        type="no-results"
        :search-query="searchQuery"
      />
      
      <!-- Pagination -->
      <div v-if="paginationMeta && paginationMeta.last_page > 1" class="flex justify-center mt-8">
        <Pagination.Root 
          v-model:page="currentPage"
          :total="paginationMeta.total"
          :items-per-page="paginationMeta.per_page"
          :sibling-count="1"
          :show-edges="true"
          class="mx-auto"
        >
          <Pagination.List v-slot="{ items }" class="flex items-center gap-2">
            <Pagination.First class="h-10 w-10 p-0 bg-bg1 hover:bg-bg2 disabled:opacity-50 hover:ring-2 disabled:hover:ring-0 disabled:hover:ring-offset-0 ring-primary ring-offset-2 ring-offset-bg1 disabled:cursor-not-allowed rounded-md flex items-center justify-center text-secondary hover:text-primary transition-colors">
              <i class="fas fa-chevron-double-left text-sm"></i>
            </Pagination.First>
            <Pagination.Prev class="h-10 w-10 p-0 bg-bg1 hover:bg-bg2 disabled:opacity-50 hover:ring-2 disabled:hover:ring-0 disabled:hover:ring-offset-0 ring-primary ring-offset-2 ring-offset-bg1 disabled:cursor-not-allowed rounded-md flex items-center justify-center text-secondary hover:text-primary transition-colors">
              <i class="fas fa-chevron-left text-sm"></i>
            </Pagination.Prev>

            <template v-for="(item, index) in items" :key="index">
              <Pagination.Ellipsis v-if="item.type === 'ellipsis'" class="flex h-10 w-10 items-center justify-center text-secondary hover:ring-2 hover:ring-primary hover:ring-offset-2 hover:ring-offset-bg1">
                <i class="fas fa-ellipsis-h text-sm"></i>
              </Pagination.Ellipsis>

              <Pagination.ListItem
                v-else
                class="h-10 w-10 p-0 hover:bg-bg2 rounded-md flex items-center justify-center cursor-pointer transition-colors hover:ring-2 hover:ring-primary hover:ring-offset-2 hover:ring-offset-bg1"
                :class="{ 'bg-primary/10 border-primary text-primary': item.value === currentPage, 'bg-bg1 text-secondary hover:text-primary': item.value !== currentPage }"
                :value="item.value" 
              >
                {{ item.value }}
              </Pagination.ListItem>
            </template>

            <Pagination.Next class="h-10 w-10 p-0 bg-bg1 hover:bg-bg2 disabled:opacity-50 hover:ring-2 disabled:hover:ring-0 disabled:hover:ring-offset-0 ring-primary ring-offset-2 ring-offset-bg1 disabled:cursor-not-allowed rounded-md flex items-center justify-center text-secondary hover:text-primary transition-colors">
              <i class="fas fa-chevron-right text-sm"></i>
            </Pagination.Next>
            <Pagination.Last class="h-10 w-10 p-0 bg-bg1 hover:bg-bg2 disabled:opacity-50 hover:ring-2 disabled:hover:ring-0 disabled:hover:ring-offset-0 ring-primary ring-offset-2 ring-offset-bg1 disabled:cursor-not-allowed rounded-md flex items-center justify-center text-secondary hover:text-primary transition-colors">
              <i class="fas fa-chevron-double-right text-sm"></i>
            </Pagination.Last>
          </Pagination.List>
        </Pagination.Root>
      </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <CompanyDeleteModal
      v-model="showDeleteModal"
      :company-to-delete="companyToDelete"
      @delete-company="handleDeleteCompany"
    />
  </div>
</template>

<script setup lang="ts">
import { Pagination } from 'reka-ui/namespaced'
import CompaniesHeader from '~/components/companies/CompaniesHeader.vue'
import CompanyCardItem from '~/components/companies/CompanyCardItem.vue'
import CompanyTableView from '~/components/companies/CompanyTableView.vue'
import CompaniesEmptyState from '~/components/companies/CompaniesEmptyState.vue'
import CompanyDeleteModal from '~/components/companies/CompanyDeleteModal.vue'
import type {  Company, PaginationMeta, PaginationParams } from '~/types/company'
import  { SortOrder } from '~/types/company'

const router = useRouter()
const route = useRoute()
const { user } = useAuth()
const nuxtApp = useNuxtApp()

// View state
const viewMode = ref<'table' | 'grid'>('table')
const searchQuery = ref('')

// Initialize pagination state from URL parameters
const currentPage = ref(parseInt(route.query.page as string) || 1)
const perPage = ref(parseInt(route.query.per_page as string) || 10)
const sortField = ref<string>(route.query.sort as string || 'created_at')
const sortOrder = ref<SortOrder>((route.query.order as SortOrder) || SortOrder.DESC)

// Computed properties for filtering
const filteredCompanies = computed(() => {
  if (!searchQuery.value.trim()) {
    return companies.value
  }
  const query = searchQuery.value.toLowerCase().trim()
  return companies.value.filter(company => 
    company.name.toLowerCase().includes(query) ||
    (company.website && company.website.toLowerCase().includes(query))
  )
})

// Computed pagination params
const query = computed((): PaginationParams => ({
  page: currentPage.value,
  per_page: perPage.value,
  sort: sortField.value,
  order: sortOrder.value
}))

watch(query, () => {
  localStorage.setItem('companies-query', JSON.stringify(query.value))
})

onMounted(() => {
  const queryString = localStorage.getItem('companies-query')
  const queryObject = JSON.parse(queryString || '{}')
  currentPage.value = queryObject?.page || 1
  perPage.value = queryObject?.per_page || 10
  sortField.value = queryObject?.sort || 'created_at'
  sortOrder.value = queryObject?.order || SortOrder.DESC
})

// Cache key for current sort/order combination (invalidates when sort changes)
const sortCacheKey = computed(() => 
  `companies-sort-${user.value?.profile.preferred_username || 'anonymous'}-${sortField.value}-${sortOrder.value}`
)

// Cache key for specific page with current sort
const pageCacheKey = computed(() => 
  `${sortCacheKey.value}-page-${currentPage.value}-${perPage.value}`
)

const { getAccessToken } = useAuth()
const accessToken = await getAccessToken()

// Cached pagination data using useAsyncData
const { data: cachedPaginationData, status, error, refresh: refreshCache } = await useFetch(
  `/api/companies`,
  {
    key: pageCacheKey.value,
    watch: [() => query.value],
    default: () => ({ data: [], meta: null }),
    query,
    baseURL: useRuntimeConfig().public.backendApi,
    headers: {
      Authorization: `Bearer ${accessToken}`
    },
    transform: (data: any) => ({
      data: data.data as Company[],
      meta: data.meta as PaginationMeta
    })
  },
)

// Extract companies and pagination meta from cached data
const companies = computed(() => cachedPaginationData.value?.data || [])
const paginationMeta = computed(() => cachedPaginationData.value?.meta || null)

// Update URL with pagination parameters
const updateURL = () => {
  const query: Record<string, string> = {}
  
  if (currentPage.value !== 1) query.page = currentPage.value.toString()
  if (perPage.value !== 10) query.per_page = perPage.value.toString()
  if (sortField.value !== 'created_at') query.sort = sortField.value
  if (sortOrder.value !== SortOrder.DESC) query.order = sortOrder.value
  
  router.push({ query })
}


const clearAllCompanyCache = () => {
  // Clear all company-related cache for current user
  const userPrefix = `companies-sort-${user.value?.profile.preferred_username || 'anonymous'}`
  const keys = Object.keys(nuxtApp.payload.data).filter(key => 
    key.startsWith(userPrefix)
  )
  keys.forEach(key => {
    delete nuxtApp.payload.data[key]
  })
}

// Watch for pagination changes
watch([currentPage, perPage], () => {
  updateURL()
})

watch(() => route.query, (newQuery) => {
  currentPage.value = parseInt(newQuery.page as string) || 1
  perPage.value = parseInt(newQuery.per_page as string) || 10
  sortField.value = newQuery.sort as string || 'created_at'
  sortOrder.value = (newQuery.order as SortOrder) || SortOrder.DESC
}, { deep: true })

// Clear cache on logout
watch(() => user.value, (newUser, oldUser) => {
  if (oldUser && !newUser) {
    // User logged out, clear all cache
    clearAllCompanyCache()
  }
})

// Methods
const toggleViewMode = () => {
  viewMode.value = viewMode.value === 'table' ? 'grid' : 'table'
}


const showDeleteModal = ref(false)
const companyToDelete = ref<Company | null>(null)

const confirmDelete = (company: Company) => {
  companyToDelete.value = company
  showDeleteModal.value = true
}

const handleDeleteCompany = () => {
  clearAllCompanyCache()
  refreshCache()
}
</script>
