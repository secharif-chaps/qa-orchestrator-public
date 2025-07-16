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
       <CompaniesPagination :meta="paginationMeta" />
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
import CompanyCardItem from '~/components/companies/CompanyCardItem.vue'
import CompanyTableView from '~/components/companies/CompanyTableView.vue'
import CompanyDeleteModal from '~/components/companies/CompanyDeleteModal.vue'
import type {  Company, PaginationMeta, PaginationParams } from '~/types/company'
import  { SortOrder } from '~/types/company'

const router = useRouter()
const route = useRoute()
const { user } = useAuth()
const nuxtApp = useNuxtApp()

const companyStore = useCompanyStore()

// View state
const viewMode = ref<'table' | 'grid'>('table')
const searchQuery = ref('')

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
  page: companyStore.currentPage,
  per_page: companyStore.perPage,
  sort: companyStore.sortField,
  order: companyStore.sortOrder
}))

watch(query, () => {
  localStorage.setItem('companies-query', JSON.stringify(query.value))
})

onMounted(() => {
  const queryString = localStorage.getItem('companies-query')
  const queryObject = JSON.parse(queryString || '{}')
  companyStore.currentPage = queryObject?.page || 1
  companyStore.perPage = queryObject?.per_page || 10
  companyStore.sortField = queryObject?.sort || 'created_at'
  companyStore.sortOrder = queryObject?.order || SortOrder.DESC
})

// Cache key for current sort/order combination (invalidates when sort changes)
const sortCacheKey = computed(() => 
  `companies-sort-${user.value?.profile.preferred_username || 'anonymous'}-${companyStore.sortField}-${companyStore.sortOrder}`
)

// Cache key for specific page with current sort
const pageCacheKey = computed(() => 
  `${sortCacheKey.value}-page-${companyStore.currentPage}-${companyStore.perPage}`
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
  
  if (companyStore.currentPage !== 1) query.page = companyStore.currentPage.toString()
  if (companyStore.perPage !== 10) query.per_page = companyStore.perPage.toString()
  if (companyStore.sortField !== 'created_at') query.sort = companyStore.sortField
  if (companyStore.sortOrder !== SortOrder.DESC) query.order = companyStore.sortOrder
  
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
watch([companyStore.currentPage, companyStore.perPage], () => {
  updateURL()
})

watch(() => route.query, (newQuery) => {
  companyStore.currentPage = parseInt(newQuery.page as string) || 1
  companyStore.perPage = parseInt(newQuery.per_page as string) || 10
  companyStore.sortField = newQuery.sort as string || 'created_at'
  companyStore.sortOrder = (newQuery.order as SortOrder) || SortOrder.DESC
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
