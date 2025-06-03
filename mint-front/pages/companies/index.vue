<template>
  <div class="px-4 sm:px-6 lg:px-8">
    <div class="sm:flex sm:items-center">
      <div class="sm:flex-auto">
        <h1 class="text-3xl font-semibold text-primary">{{ $t('cards.title') }}</h1>
      </div>
    </div>
    <div class="mt-8 flow-root">
      <div class="-mx-4 -my-2 overflow-x-auto sm:-mx-6 lg:-mx-8">
        <div class="inline-block min-w-full py-2 align-middle sm:px-6 lg:px-8">
          <div class="overflow-hidden shadow ring-1 ring-black/5 sm:rounded-lg">
            <div v-if="loading" class="p-4 text-center">
              <i class="fas fa-spinner fa-spin mr-2"></i> Loading...
            </div>
            <div v-else-if="error" class="p-4 text-center text-red-500">
              <i class="fas fa-exclamation-circle mr-2"></i> {{ error }}
            </div>
            <table v-else class="min-w-full divide-y divide-gray-300">
              <thead class="bg-gray-50">
                <tr>
                  <th
                    scope="col"
                    class="py-3.5 pl-4 pr-3 text-left text-sm font-semibold text-gray-900 sm:pl-6"
                  >
                    {{ $t('cards.table.name') }}
                  </th>
                  <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">
                    Website
                  </th>
                  <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">
                    {{ $t('cards.table.lastModification') }}
                  </th>

                  <th scope="col" class="relative py-3.5 pl-3 pr-4 sm:pr-6">
                    <span class="sr-only">{{ $t('cards.table.actions') }}</span>
                  </th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-200 bg-white">
                <tr v-if="!companies.length">
                  <td class="py-4 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-6" colspan="4">
                    {{ $t('cards.noResults') }}
                  </td>
                </tr>
                <tr v-for="company in companies" :key="company.id" class="hover:bg-bg2 group">
                  <td
                    class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-6 group-hover:text-primary cursor-pointer"
                    @click="$router.push(`/companies/${company.id}`)"
                  >
                    {{ company.name }}
                  </td>
                  <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">
                    {{ company.website }}
                  </td>
                  <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">
                    {{ formatDate(company.updated_at) }}
                  </td>
                  <td
                    class="relative whitespace-nowrap py-4 pl-3 pr-4 text-right text-sm font-medium sm:pr-6"
                  >
                    <div class="flex items-center justify-end space-x-3">
                      <OButton
                        icon="fa-eye"
                        type="tertiary"
                        :title="$t('cards.actions.view')"
                        @click="$router.push(`/companies/${company.id}`)"
                      >
                      </OButton>
                      <OButton
                        @click="confirmDelete(company.id, company.name)"
                        icon="fa-trash"
                        color="red"
                        type="tertiary"
                        :title="$t('cards.actions.delete')"
                      />
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <!-- Create Company Modal -->
    <div
      v-if="showCreateModal"
      class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50"
    >
      <div class="bg-white rounded-lg shadow-lg p-6 w-full max-w-md">
        <div class="flex justify-between items-center mb-4">
          <h2 class="text-xl font-semibold">Create New Company</h2>
          <button @click="showCreateModal = false" class="text-gray-500 hover:text-gray-700">
            <i class="fas fa-times"></i>
          </button>
        </div>
        <form @submit.prevent="createCompany">
          <div class="mb-4">
            <label for="name" class="block text-sm font-medium text-gray-700">Company Name</label>
            <input
              id="name"
              v-model="newCompany.name"
              type="text"
              required
              class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary text-sm"
              placeholder="Enter company name"
            />
          </div>
          <div class="mb-6">
            <label for="website" class="block text-sm font-medium text-gray-700">Website</label>
            <input
              id="website"
              v-model="newCompany.website"
              type="url"
              required
              class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary text-sm"
              placeholder="Enter website URL"
            />
          </div>
          <div class="flex justify-end gap-2">
            <OButton type="tertiary" @click="showCreateModal = false"> Cancel </OButton>
            <OButton type="primary" :loading="createLoading" submit> Create </OButton>
          </div>
        </form>
      </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div
      v-if="showDeleteModal"
      class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50"
    >
      <div class="bg-white rounded-lg shadow-lg p-6 w-full max-w-md">
        <div class="flex justify-between items-center mb-4">
          <h2 class="text-xl font-semibold text-red-600">Delete Company</h2>
          <button @click="showDeleteModal = false" class="text-gray-500 hover:text-gray-700">
            <i class="fas fa-times"></i>
          </button>
        </div>
        <p class="mb-4">
          Are you sure you want to delete <strong>{{ companyToDelete.name }}</strong
          >?
        </p>
        <p class="mb-6 text-red-600 font-medium">This action cannot be undone.</p>
        <div class="flex justify-end gap-2">
          <OButton type="tertiary" @click="showDeleteModal = false"> Cancel </OButton>
          <OButton type="danger" :loading="deleteLoading" @click="deleteCompany"> Delete </OButton>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { OButton } from '@owlint/feathers-vue'
import { computed, onMounted, ref } from 'vue'

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

// Computed properties
const companies = computed(() => companyStore.companies)
const loading = computed(() => companyStore.loading)
const error = computed(() => companyStore.error)

// Load companies on mount
onMounted(async () => {
  await companyStore.fetchCompanies()
})

// Methods
const formatDate = dateString => {
  if (!dateString) return 'N/A'
  return new Date(dateString).toLocaleDateString()
}

const confirmDelete = (id, name) => {
  companyToDelete.value = { id, name }
  showDeleteModal.value = true
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
