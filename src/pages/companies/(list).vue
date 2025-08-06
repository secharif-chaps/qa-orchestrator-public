<template>
  <div class="max-w-7xl mx-auto space-y-6">
    <!-- Companies Header -->
    <CompaniesHeader :view-mode="viewMode" @toggle-view="toggleViewMode" />

    <!-- Main Content -->
    <div v-if="status === 'success' && companies && companies.length > 0">
      <!-- Companies Container -->
      <div
        :class="
          viewMode === 'grid'
            ? 'grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6'
            : 'flex flex-col rounded-lg overflow-hidden '
        "
      >
        <!-- Table Header for List View -->
        <div v-if="viewMode === 'table'" class="p-4">
          <div class="grid grid-cols-12 gap-4 items-center text-sm font-medium text-secondary">
            <div class="col-span-5">Company</div>
            <div class="col-span-2">Created</div>
            <div class="col-span-3">Owner</div>
            <div class="col-span-2 text-right">Actions</div>
          </div>
        </div>

        <div class="flex flex-col gap-4">
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
        <CompaniesPagination :meta="paginationMeta" />
      </div>
    </div>

    <!-- No Results State -->
    <CompaniesEmptyState
      v-else-if="status === 'success' && companies && companies.length === 0"
      type="no-results"
    />

    <div v-else-if="status === 'error'">
      <OAlert
        color="red"
        :title="$t('company.list.error.title')"
        :description="$t('company.list.error.description')"
      />
    </div>

    <div v-if="isLoading" class="flex justify-center items-center h-full gap-4">
      <i class="fas fa-spinner-third fa-spin"></i>
      <span class="text-secondary italic text-sm">Loading companies...</span>
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
import CompaniesHeader from '@/components/companies/CompaniesHeader.vue'
import CompaniesEmptyState from '@/components/companies/CompaniesEmptyState.vue'
import CompaniesPagination from '@/components/companies/CompaniesPagination.vue'
import CompanyItem from '@/components/companies/CompanyItem.vue'
import { OAlert } from '@owlint/feathers-vue'
import { useQuery } from '@pinia/colada'
import { useCompaniesStore } from '@/stores/companies'

// Constants
const VIEW_MODE_STORAGE_KEY = 'companies-view-mode'

// View state
const viewMode = ref<'table' | 'grid'>('table')

const companiesStore = useCompaniesStore()

const { data, status, isLoading } = useQuery(companiesQuery, () => ({
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

const handleDeleteCompany = () => {
  // TODO: Implement delete company
}

const confirmDelete = (company: Company) => {
  // TODO: Implement delete company
}

const toggleViewMode = () => {
  viewMode.value = viewMode.value === 'table' ? 'grid' : 'table'
  // Save to localStorage
  localStorage.setItem(VIEW_MODE_STORAGE_KEY, viewMode.value)
}
</script>
