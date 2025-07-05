<template>
  <div class="max-w-7xl mx-auto space-y-6">
    <!-- Companies Header -->
    <CompaniesHeader
      :search-query="searchQuery"
      :view-mode="viewMode"
      :companies-count="companies.length"
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
import CompaniesHeader from '~/components/companies/CompaniesHeader.vue'
import CompanyCardItem from '~/components/companies/CompanyCardItem.vue'
import CompanyTableView from '~/components/companies/CompanyTableView.vue'
import CompaniesEmptyState from '~/components/companies/CompaniesEmptyState.vue'

const companyStore = useCompanyStore()
const router = useRouter()

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

// Load companies on mount
onMounted(async () => {
  await companyStore.fetchCompanies()
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
