<template>
  <div class="min-h-screen bg-base-300">
    <div class="flex flex-col gap-4">
      <!-- Header -->
      <CompaniesHeader v-model:view-mode="viewMode" />

      <!-- Error Alert -->
      <div
        v-if="status === 'error'"
        class="mb-6 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg"
      >
        <div class="flex items-center gap-2">
          <i class="fa fa-exclamation-triangle"></i>
          <span class="font-medium">{{ $t('common.error', 'Error') }}:</span>
          <span>{{ $t('company.list.error.description', 'Failed to load companies') }}</span>
        </div>
      </div>

      <!-- Loading State -->
      <div v-if="isLoading" class="bg-base-100 rounded-lg shadow-sm p-8 text-center">
        <div
          class="animate-spin rounded-full h-12 w-12 border-b-2 border-primary mx-auto mb-4"
        ></div>
        <p class="text-primary-light-content">
          {{ $t('company.loading', 'Loading companies...') }}
        </p>
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
        <div v-else class="bg-base-100 rounded-lg overflow-hidden border border-primary-stroke">
          <!-- Table Header -->
          <div class="px-6 py-4 border-b border-primary-stroke bg-base-200">
            <div class="grid grid-cols-12 gap-4 text-sm font-medium text-primary-light-content">
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
        <div v-if="paginationMeta" class="mt-4">
          <Pagination
            v-model:current-page="companiesStore.page"
            :meta="paginationMeta"
            :page-size-options="pageSizeOptions"
            item-name="companies"
            @update-per-page="updatePerPage"
          />
        </div>
      </div>

      <!-- Empty State -->
      <div
        v-else-if="status === 'success'"
        class="bg-base-100 rounded-lg shadow-sm p-12 text-center"
      >
        <i class="fa fa-building text-4xl text-primary-light-content/50 mb-4"></i>
        <h3 class="text-lg font-medium mb-2">
          {{
            companiesStore.filterName
              ? $t('company.empty.noResults', 'No companies found')
              : $t('company.empty.title', 'No companies yet')
          }}
        </h3>
        <p class="text-primary-light-content mb-6">
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
import CompaniesHeader from '@/components/companies/CompaniesHeader.vue'
import type { Company } from '@/types/company'
import { ref, onMounted, computed } from 'vue'
import { companiesQuery } from '@/queries/companies'
import Pagination from '@/components/ui/Pagination.vue'
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

const updatePerPage = (newSize: number) => {
  companiesStore.size = newSize
  // Reset to first page when changing page size
  companiesStore.page = 1
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
