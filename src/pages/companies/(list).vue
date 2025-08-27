<template>
  <div class="min-h-screen bg-bg3">
    <div class="">
      <!-- Header -->
      <div class="mb-8">
        <div class="flex items-center justify-between mb-6">
          <div>
            <h1 class="text-3xl font-bold">
              {{ $t('company.management.title', 'Company Management') }}
            </h1>
            <p class="text-secondary mt-2">
              {{
                $t(
                  'company.management.description',
                  'View and manage all companies in your workspace',
                )
              }}
            </p>
          </div>

          <Button
            variant="primary"
            icon="fa fa-plus"
            :label="$t('company.create.button', 'New search')"
            @click="$router.push('/search')"
          />
        </div>

        <!-- Search and Filters -->
        <div class="flex items-center gap-4 bg-bg1 p-4 rounded-lg shadow-sm border border-border-2">
          <!-- Search Input -->
          <div class="flex-1 max-w-md">
            <div class="relative">
              <i
                class="fa fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-secondary"
              ></i>
              <input
                v-model="companiesStore.filterName"
                type="text"
                :placeholder="$t('company.search.placeholder', 'Search companies...')"
                class="w-full pl-10 pr-4 py-2 border border-border-2 rounded-lg focus:ring-2 focus:ring-primary/20 focus:border-primary"
              />
            </div>
          </div>

          <!-- View Mode Toggle -->
          <div class="flex items-center gap-2">
            <Button
              :variant="viewMode === 'table' ? 'secondary' : 'tertiary'"
              icon="fa fa-list"
              icon-only
              :title="$t('company.view.table', 'Table View')"
              @click="setViewMode('table')"
            />
            <Button
              :variant="viewMode === 'grid' ? 'secondary' : 'tertiary'"
              icon="fa fa-th-large"
              icon-only
              :title="$t('company.view.grid', 'Grid View')"
              @click="setViewMode('grid')"
            />
          </div>

          <!-- Page Size Selector -->
          <div class="flex items-center gap-2">
            <label class="text-sm text-secondary">{{
              $t('company.pageSize.label', 'Show:')
            }}</label>
            <select
              v-model="companiesStore.size"
              class="px-3 py-2 border border-border-2 rounded-lg text-sm focus:ring-2 focus:ring-primary/20 focus:border-primary"
            >
              <option v-for="option in pageSizeOptions" :key="option" :value="option">
                {{ option }}
              </option>
            </select>
          </div>
        </div>
      </div>

      <!-- Error Alert -->
      <div
        v-if="status === 'error'"
        class="mb-6 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg"
      >
        <div class="flex items-center gap-2">
          <i class="fa fa-exclamation-triangle"></i>
          <span class="font-medium">Error:</span>
          <span>{{ $t('company.list.error.description', 'Failed to load companies') }}</span>
        </div>
      </div>

      <!-- Loading State -->
      <div v-if="isLoading" class="bg-bg1 rounded-lg shadow-sm p-8 text-center">
        <div
          class="animate-spin rounded-full h-12 w-12 border-b-2 border-primary mx-auto mb-4"
        ></div>
        <p class="text-secondary">{{ $t('company.loading', 'Loading companies...') }}</p>
      </div>

      <!-- Companies List -->
      <div v-else-if="status === 'success' && companies && companies.length > 0">
        <!-- Grid View -->
        <div
          v-if="viewMode === 'grid'"
          class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6"
        >
          <CompanyItem
            :mode="viewMode"
            v-for="company in companies"
            :key="company.id"
            :company="company"
            @view-company="$router.push(`/companies/${$event}`)"
            @delete-company="confirmDelete"
          />
        </div>

        <!-- Table View -->
        <div v-else class="bg-bg1 rounded-lg shadow-sm overflow-hidden border border-border-2">
          <!-- Table Header -->
          <div class="px-6 py-4 border-b border-border-2 bg-bg2">
            <div class="grid grid-cols-12 gap-4 text-sm font-medium text-secondary">
              <div class="col-span-4">{{ $t('company.name', 'Company') }}</div>
              <div class="col-span-2">{{ $t('company.created', 'Created') }}</div>
              <div class="col-span-2">{{ $t('company.owner', 'Owner') }}</div>
              <div class="col-span-2">{{ $t('company.status', 'Status') }}</div>
              <div class="col-span-2 text-right">{{ $t('company.actions', 'Actions') }}</div>
            </div>
          </div>

          <!-- Table Body -->
          <div class="divide-y divide-border-2">
            <CompanyItem
              :mode="viewMode"
              v-for="company in companies"
              :key="company.id"
              :company="company"
              @view-company="$router.push(`/companies/${$event}`)"
              @delete-company="confirmDelete"
            />
          </div>
        </div>

        <!-- Pagination -->
        <div v-if="paginationMeta" class="flex justify-center mt-8">
          <CompaniesPagination v-model:current-page="companiesStore.page" :meta="paginationMeta" />
        </div>
      </div>

      <!-- Empty State -->
      <div v-else-if="status === 'success'" class="bg-bg1 rounded-lg shadow-sm p-12 text-center">
        <i class="fa fa-building text-4xl text-secondary/50 mb-4"></i>
        <h3 class="text-lg font-medium mb-2">
          {{
            companiesStore.filterName
              ? $t('company.empty.noResults', 'No companies found')
              : $t('company.empty.title', 'No companies yet')
          }}
        </h3>
        <p class="text-secondary mb-6">
          {{
            companiesStore.filterName
              ? $t('company.empty.tryDifferentSearch', 'Try a different search term')
              : $t('company.empty.description', 'Start by adding your first company')
          }}
        </p>
        <Button
          v-if="!companiesStore.filterName"
          @click="$router.push('/search')"
          :label="$t('company.create.button', 'Make a new search')"
          variant="primary"
        />
        <Button
          v-else
          @click="companiesStore.filterName = ''"
          :label="$t('company.clearSearch', 'Clear Search')"
          variant="secondary"
        />
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

<route lang="yaml">
meta:
  permissions:
    - company.view
</route>

<script setup lang="ts">
import CompanyDeleteModal from '@/components/companies/CompanyDeleteModal.vue'
import type { Company } from '@/types/company'
import { ref, onMounted, computed } from 'vue'
import { companiesQuery } from '@/queries/companies'
import CompaniesPagination from '@/components/companies/CompaniesPagination.vue'
import CompanyItem from '@/components/companies/CompanyItem.vue'
import Button from '@/components/ui/Button.vue'
import { useQuery } from '@pinia/colada'
import { useCompaniesStore } from '@/stores/companies'

// Constants
const VIEW_MODE_STORAGE_KEY = 'companies-view-mode'

// Dynamic page size options based on view mode
const pageSizeOptions = computed(() => {
  if (viewMode.value === 'grid') {
    // Multiples of 3 for grid view (3 columns)
    return [6, 12, 21, 30]
  } else {
    // Traditional options for table view
    return [5, 10, 20, 50]
  }
})

// View state
const viewMode = ref<'table' | 'grid'>('table')

const companiesStore = useCompaniesStore()

const { data, status, isLoading, refetch } = useQuery(companiesQuery, () => ({
  filters: {
    page: companiesStore.page,
    size: companiesStore.size,
    name: companiesStore.debouncedName,
  },
}))

const companies = computed(() => data.value?.data)
const paginationMeta = computed(() => data.value?.meta)

const showDeleteModal = ref(false)
const companyToDelete = ref<Company | null>(null)

// Load saved view mode from localStorage
onMounted(() => {
  const savedViewMode = localStorage.getItem(VIEW_MODE_STORAGE_KEY)
  if (savedViewMode === 'grid' || savedViewMode === 'table') {
    viewMode.value = savedViewMode
  }
})

const handleDeleteCompany = async () => {
  // Refresh the companies list after successful deletion
  await refetch()
}

const confirmDelete = (company: Company) => {
  companyToDelete.value = company
  showDeleteModal.value = true
}

const setViewMode = (mode: 'table' | 'grid') => {
  viewMode.value = mode
  // Save to localStorage
  localStorage.setItem(VIEW_MODE_STORAGE_KEY, mode)
  
  // Adjust page size when switching modes to match the new options
  if (mode === 'grid' && !pageSizeOptions.value.includes(companiesStore.size)) {
    // Switch to closest grid-friendly option
    if (companiesStore.size <= 6) companiesStore.size = 6
    else if (companiesStore.size <= 12) companiesStore.size = 12
    else if (companiesStore.size <= 21) companiesStore.size = 21
    else companiesStore.size = 30
  } else if (mode === 'table' && !pageSizeOptions.value.includes(companiesStore.size)) {
    // Switch to closest table-friendly option
    if (companiesStore.size <= 5) companiesStore.size = 5
    else if (companiesStore.size <= 10) companiesStore.size = 10
    else if (companiesStore.size <= 20) companiesStore.size = 20
    else companiesStore.size = 50
  }
}
</script>
