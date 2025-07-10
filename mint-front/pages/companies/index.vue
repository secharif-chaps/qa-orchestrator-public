<template>
  <div class="max-w-7xl mx-auto space-y-6">
    <!-- Companies Header -->
    <CompaniesHeader
      :search-query="searchQuery"
      :view-mode="viewMode"
      :companies-count="paginationMeta?.total || companies.length"
      @update-search="searchQuery = $event"
      @toggle-view="toggleViewMode"
      @create-company="showCreateModal = true"
    />


    <!-- Empty State when no companies -->
    <CompaniesEmptyState
      v-if="!loading && !error && companies.length === 0"
      type="no-data"
      @create-company="showCreateModal = true"
    />


    <!-- Main Content -->
    <div v-else-if="!loading && !error">
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
        :loading="loading"
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
            <Pagination.First class="h-10 w-10 p-0  bg-bg1 hover:bg-bg2 disabled:opacity-50 hover:ring-2 disabled:hover:ring-0 disabled:hover:ring-offset-0 ring-primary ring-offset-2 ring-offset-bg1 disabled:cursor-not-allowed rounded-md flex items-center justify-center text-secondary hover:text-primary transition-colors">
              <i class="fas fa-chevron-double-left text-sm"></i>
            </Pagination.First>
            <Pagination.Prev class="h-10 w-10 p-0  bg-bg1 hover:bg-bg2 disabled:opacity-50 hover:ring-2 disabled:hover:ring-0 disabled:hover:ring-offset-0 ring-primary ring-offset-2 ring-offset-bg1 disabled:cursor-not-allowed rounded-md flex items-center justify-center text-secondary hover:text-primary transition-colors">
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

    <!-- Create Company Modal -->
    <OModal 
      v-model="showCreateModal"
      :title="$t('company.list.create.title')"
      size="md"
    >
      <form @submit.prevent="createCompany" class="space-y-4">
        <div>
          <OInput
            v-model="newCompany.name"
            :label="$t('company.list.create.name.label')"
            :placeholder="$t('company.list.create.name.placeholder')"
            type="text"
            required
          />
        </div>
        <div>
          <OInput
            v-model="newCompany.website"
            :label="$t('company.list.create.website.label')"
            :placeholder="$t('company.list.create.website.placeholder')"
            type="url"
            required
          />
        </div>
        <div class="flex justify-end gap-3 pt-4">
          <OButton 
            type="secondary" 
            :label="$t('company.list.create.actions.cancel')"
            @click="showCreateModal = false"
          />
          <OButton 
            type="primary" 
            :label="$t('company.list.create.actions.create')"
            :loading="createLoading" 
            submit
          />
        </div>
      </form>
    </OModal>

    <!-- Delete Confirmation Modal -->
    <OModal 
      v-model="showDeleteModal"
      :display-modal="showDeleteModal"
      :title="$t('company.list.delete.title')"
      size="md"
      icon="fas fa-trash"
      color="red"
    >

    <template #description>
        <p class="text-secondary">
          {{ $t('company.list.delete.confirm') }} <strong class="font-medium text-primary capitalize">{{ companyToDelete.name }}</strong>?
        </p>
        <p class="text-secondary">
          {{ $t('company.list.delete.warning') }} 
        </p>
        </template>

          <template #footer>
        <div class="flex justify-end gap-3">
          <OButton 
            type="secondary" 
            color="primary"
            :label="$t('company.list.delete.actions.cancel')"
            @click="showDeleteModal = false"
          />
          <OButton 
            type="primary"
            color="red" 
            :label="$t('company.list.delete.actions.delete')"
            :loading="deleteLoading" 
            @click="deleteCompany"
          />

        </div>
        </template>

    </OModal>
  </div>
</template>

<script setup lang="ts">
import { OButton, OInput, OModal } from '@owlint/feathers-vue'
import { Pagination } from 'reka-ui/namespaced'
import CompaniesHeader from '~/components/companies/CompaniesHeader.vue'
import CompanyCardItem from '~/components/companies/CompanyCardItem.vue'
import CompanyTableView from '~/components/companies/CompanyTableView.vue'
import CompaniesEmptyState from '~/components/companies/CompaniesEmptyState.vue'
import type {  PaginationParams } from '~/types/company'
import  { SortOrder } from '~/types/company'

const companyStore = useCompanyStore()
const router = useRouter()
const route = useRoute()

// Local state
const showCreateModal = ref(false)
const showDeleteModal = ref(false)
const createLoading = ref(false)
const deleteLoading = ref(false)
const companyToDelete = ref({ id: null, name: '' })

const newCompany = ref({
  name: '',
  website: ''
})

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

// Computed properties
const companies = computed(() => companyStore.companies)
const loading = computed(() => companyStore.loading)
const error = computed(() => companyStore.error)
const paginationMeta = computed(() => companyStore.paginationMeta)

// Computed pagination params
const paginationParams = computed((): PaginationParams => ({
  page: currentPage.value,
  per_page: perPage.value,
  sort: sortField.value,
  order: sortOrder.value
}))


// Update URL with pagination parameters
const updateURL = () => {
  const query: Record<string, string> = {}
  
  if (currentPage.value !== 1) query.page = currentPage.value.toString()
  if (perPage.value !== 10) query.per_page = perPage.value.toString()
  if (sortField.value !== 'created_at') query.sort = sortField.value
  if (sortOrder.value !== SortOrder.DESC) query.order = sortOrder.value
  
  router.push({ query })
}

// Fetch companies with pagination
const fetchCompanies = async () => {
  await companyStore.fetchPaginatedCompanies(paginationParams.value)
}

// Watch for pagination changes
watch([currentPage, perPage, sortField, sortOrder], () => {
  updateURL()
  fetchCompanies()
})

// Watch for route changes to sync state
watch(() => route.query, (newQuery) => {
  currentPage.value = parseInt(newQuery.page as string) || 1
  perPage.value = parseInt(newQuery.per_page as string) || 10
  sortField.value = newQuery.sort as string || 'created_at'
  sortOrder.value = (newQuery.order as SortOrder) || SortOrder.DESC
}, { deep: true })

// Load companies on mount
onMounted(() => {
  fetchCompanies()
})

// Methods
const toggleViewMode = () => {
  viewMode.value = viewMode.value === 'table' ? 'grid' : 'table'
}

const confirmDelete = (id: string, name: string) => {
  companyToDelete.value = { id, name }
  showDeleteModal.value = true
}

const createCompany = async () => {
  if (!newCompany.value.name || !newCompany.value.website) return

  createLoading.value = true
  try {
    await companyStore.createCompany(newCompany.value)
    showCreateModal.value = false
    newCompany.value = { name: '', website: '' }
  } catch (err) {
    console.error('Failed to create company:', err)
  } finally {
    createLoading.value = false
  }
}

const deleteCompany = async () => {
  if (!companyToDelete.value.id) return

  deleteLoading.value = true
  try {
    await companyStore.deleteCompany(companyToDelete.value.id)
    showDeleteModal.value = false
  } catch (err) {
    console.error('Failed to delete company:', err)
  } finally {
    deleteLoading.value = false
  }
}
</script>
