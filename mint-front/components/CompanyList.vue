<template>
  <div class="company-list">
    <h2>Companies</h2>

    <div v-if="loading" class="loading"><i class="fas fa-spinner fa-spin"></i> Loading...</div>

    <div v-else-if="error" class="error"><i class="fas fa-exclamation-circle"></i> {{ error }}</div>

    <div v-else-if="companies.length === 0" class="empty">
      No companies found.
      <button @click="showCreateForm = true" class="create-btn">
        <i class="fas fa-plus"></i> Create Company
      </button>
    </div>

    <div v-else class="companies-container">
      <div class="companies-header">
        <h3>Company List</h3>
        <button @click="showCreateForm = true" class="create-btn">
          <i class="fas fa-plus"></i> Create Company
        </button>
      </div>

      <ul class="company-items">
        <li v-for="company in companies" :key="company.id" class="company-item">
          <div class="company-info">
            <h4>{{ company.name }}</h4>
            <p>{{ company.website }}</p>
            <p class="created-at">Created: {{ formatDate(company.created_at) }}</p>
          </div>

          <div class="company-actions">
            <button @click="viewCompany(company.id)" class="view-btn">
              <i class="fas fa-eye"></i>
            </button>
            <button @click="editCompany(company.id)" class="edit-btn">
              <i class="fas fa-pen"></i>
            </button>
            <button @click="confirmDelete(company.id)" class="delete-btn">
              <i class="fas fa-trash"></i>
            </button>
          </div>
        </li>
      </ul>
    </div>

    <!-- Create Company Form Modal -->
    <div v-if="showCreateForm" class="modal">
      <div class="modal-content">
        <div class="modal-header">
          <h3>Create New Company</h3>
          <button @click="showCreateForm = false" class="close-btn">
            <i class="fas fa-times"></i>
          </button>
        </div>

        <div class="modal-body">
          <form @submit.prevent="createNewCompany">
            <div class="form-group">
              <label for="name">Company Name</label>
              <input
                id="name"
                v-model="newCompany.name"
                type="text"
                required
                placeholder="Enter company name"
              />
            </div>

            <div class="form-group">
              <label for="website">Website</label>
              <input
                id="website"
                v-model="newCompany.website"
                type="url"
                required
                placeholder="Enter website URL"
              />
            </div>

            <div class="form-actions">
              <button type="button" @click="showCreateForm = false" class="cancel-btn">
                Cancel
              </button>
              <button type="submit" class="submit-btn" :disabled="createLoading">
                <i v-if="createLoading" class="fas fa-spinner fa-spin"></i>
                <span v-else>Create</span>
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div v-if="showDeleteConfirm" class="modal">
      <div class="modal-content">
        <div class="modal-header">
          <h3>Confirm Delete</h3>
          <button @click="showDeleteConfirm = false" class="close-btn">
            <i class="fas fa-times"></i>
          </button>
        </div>

        <div class="modal-body">
          <p>Are you sure you want to delete this company?</p>
          <div class="form-actions">
            <button type="button" @click="showDeleteConfirm = false" class="cancel-btn">
              Cancel
            </button>
            <button
              type="button"
              @click="deleteCompanyConfirmed"
              class="delete-btn"
              :disabled="deleteLoading"
            >
              <i v-if="deleteLoading" class="fas fa-spinner fa-spin"></i>
              <span v-else>Delete</span>
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue'

import type { CompanyCreate } from '~/types/company'

const companyStore = useCompanyStore()
const router = useRouter()

// Local state
const showCreateForm = ref(false)
const showDeleteConfirm = ref(false)
const deleteCompanyId = ref<number | null>(null)
const createLoading = ref(false)
const deleteLoading = ref(false)
const newCompany = ref<CompanyCreate>({
  name: '',
  website: ''
})

// Computed properties
const companies = computed(() => companyStore.companies)
const loading = computed(() => companyStore.loading)
const error = computed(() => companyStore.error)

// Lifecycle hooks
onMounted(async () => {
  await companyStore.fetchCompanies()
})

// Methods
const formatDate = (dateString: string) => {
  return new Date(dateString).toLocaleDateString()
}

const viewCompany = (id: number) => {
  router.push(`/companies/${id}`)
}

const editCompany = (id: number) => {
  router.push(`/companies/${id}/edit`)
}

const confirmDelete = (id: number) => {
  deleteCompanyId.value = id
  showDeleteConfirm.value = true
}

const deleteCompanyConfirmed = async () => {
  if (!deleteCompanyId.value) return

  deleteLoading.value = true
  try {
    await companyStore.deleteCompany(deleteCompanyId.value)
    showDeleteConfirm.value = false
  } catch (err) {
    console.error('Failed to delete company:', err)
  } finally {
    deleteLoading.value = false
  }
}

const createNewCompany = async () => {
  createLoading.value = true
  try {
    const createdCompany = await companyStore.createCompany(newCompany.value)
    showCreateForm.value = false
    newCompany.value = { name: '', website: '' }

    // Navigate to the created company
    router.push(`/companies/${createdCompany.id}`)
  } catch (err) {
    console.error('Failed to create company:', err)
  } finally {
    createLoading.value = false
  }
}
</script>

<style scoped>
.company-list {
  padding: 1rem;
}

.loading,
.error,
.empty {
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 2rem;
  background-color: #f9f9f9;
  border-radius: 0.5rem;
  margin: 1rem 0;
}

.error {
  color: #e74c3c;
}

.companies-container {
  margin: 1rem 0;
}

.companies-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1rem;
}

.create-btn {
  background-color: #2ecc71;
  color: white;
  border: none;
  padding: 0.5rem 1rem;
  border-radius: 0.25rem;
  cursor: pointer;
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.company-items {
  list-style: none;
  padding: 0;
  margin: 0;
}

.company-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1rem;
  background-color: #f9f9f9;
  border-radius: 0.5rem;
  margin-bottom: 0.5rem;
}

.company-info h4 {
  margin: 0 0 0.5rem 0;
}

.company-info p {
  margin: 0;
  color: #555;
}

.created-at {
  font-size: 0.8rem;
  color: #777;
  margin-top: 0.5rem;
}

.company-actions {
  display: flex;
  gap: 0.5rem;
}

.view-btn,
.edit-btn,
.delete-btn {
  border: none;
  padding: 0.5rem;
  border-radius: 0.25rem;
  cursor: pointer;
  color: white;
}

.view-btn {
  background-color: #3498db;
}

.edit-btn {
  background-color: #f39c12;
}

.delete-btn {
  background-color: #e74c3c;
}

.modal {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background-color: rgba(0, 0, 0, 0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
}

.modal-content {
  background-color: white;
  padding: 1rem;
  border-radius: 0.5rem;
  width: 100%;
  max-width: 500px;
}

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1rem;
}

.modal-header h3 {
  margin: 0;
}

.close-btn {
  background: none;
  border: none;
  font-size: 1.25rem;
  cursor: pointer;
}

.form-group {
  margin-bottom: 1rem;
}

.form-group label {
  display: block;
  margin-bottom: 0.5rem;
  font-weight: bold;
}

.form-group input {
  width: 100%;
  padding: 0.5rem;
  border: 1px solid #ddd;
  border-radius: 0.25rem;
}

.form-actions {
  display: flex;
  justify-content: flex-end;
  gap: 0.5rem;
  margin-top: 1rem;
}

.cancel-btn {
  background-color: #95a5a6;
  color: white;
  border: none;
  padding: 0.5rem 1rem;
  border-radius: 0.25rem;
  cursor: pointer;
}

.submit-btn {
  background-color: #2ecc71;
  color: white;
  border: none;
  padding: 0.5rem 1rem;
  border-radius: 0.25rem;
  cursor: pointer;
}

.submit-btn:disabled,
.delete-btn:disabled {
  opacity: 0.7;
  cursor: not-allowed;
}
</style>
