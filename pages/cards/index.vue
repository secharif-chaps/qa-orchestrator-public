<template>
  <div class="px-4 sm:px-6 lg:px-8">
    <div class="sm:flex sm:items-center">
      <div class="sm:flex-auto">
        <h1 class="text-3xl font-semibold text-primary">Cards</h1>
      </div>
      <div class="mt-4 sm:ml-16 sm:mt-0 sm:flex-none flex space-x-4">
        <!-- <PrimaryButton size="large">Add user</PrimaryButton>
        <TertiaryButton size="large"
          >Filter
          <i class="fa fa-chevron-down"></i>
        </TertiaryButton>
        <TertiaryButton size="large"
          >Creation date
          <i class="fa fa-chevron-down"></i>
        </TertiaryButton>
        <TertiaryButton size="large">
          <i class="fa fa-grip-lines"></i>
        </TertiaryButton> -->
      </div>
    </div>
    <div class="mt-8 flow-root">
      <div class="-mx-4 -my-2 overflow-x-auto sm:-mx-6 lg:-mx-8">
        <div class="inline-block min-w-full py-2 align-middle sm:px-6 lg:px-8">
          <div class="overflow-hidden shadow ring-1 ring-black/5 sm:rounded-lg">
            <table class="min-w-full divide-y divide-gray-300">
              <thead class="bg-gray-50">
                <tr>
                  <th
                    scope="col"
                    class="py-3.5 pl-4 pr-3 text-left text-sm font-semibold text-gray-900 sm:pl-6"
                  >
                    Name
                  </th>
                  <th
                    scope="col"
                    class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900"
                  >
                    Creator
                  </th>
                  <th
                    scope="col"
                    class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900"
                  >
                    Last modification
                  </th>

                  <th
                    scope="col"
                    class="relative py-3.5 pl-3 pr-4 sm:pr-6"
                  >
                    <span class="sr-only">actions</span>
                  </th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-200 bg-white">
                <tr v-if="!companies.length">
                  <td
                    class="py-4 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-6"
                    colspan="5"
                  >
                    No company found
                  </td>
                </tr>
                <tr
                  v-for="company in companies"
                  :key="company.name"
                  class="hover:bg-bg2 group"
                >
                  <td
                    class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-6 group-hover:text-primary cursor-pointer"
                    @click="
                      $router.push(
                        '/cards/' + getSourcedValue(company.profile.name)
                      )
                    "
                  >
                    {{ getSourcedValue(company.profile.name) }}
                  </td>
                  <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">
                    You
                  </td>
                  <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">
                    {{ company.meta?.query_date || 'N/A' }}
                  </td>

                  <td
                    class="relative whitespace-nowrap py-4 pl-3 pr-4 text-right text-sm font-medium sm:pr-6"
                  >
                    <div class="flex items-center justify-end space-x-3">
                      <button
                        @click="
                          $router.push(
                            '/cards/' + getSourcedValue(company.profile.name)
                          )
                        "
                        class="text-gray-500 hover:text-gray-700 focus:outline-none"
                        title="View details"
                      >
                        <i class="fa fa-eye"></i>
                      </button>
                      <button
                        @click.stop="
                          confirmDelete(getSourcedValue(company.profile.name))
                        "
                        class="text-red-500 hover:text-red-700 focus:outline-none"
                        title="Delete company"
                      >
                        <i class="fa fa-trash"></i>
                      </button>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div
      v-if="showDeleteModal"
      class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full flex items-center justify-center z-50"
    >
      <div
        class="relative mx-auto p-5 border w-96 shadow-lg rounded-md bg-white"
      >
        <div class="mt-3 text-center">
          <div
            class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-red-100"
          >
            <i class="fa fa-exclamation-triangle text-red-600 text-xl"></i>
          </div>
          <h3 class="text-lg leading-6 font-medium text-gray-900 mt-2">
            Delete Company
          </h3>
          <div class="mt-2 px-7 py-3">
            <p class="text-sm text-gray-500">
              Are you sure you want to delete
              <span class="font-semibold">{{ companyToDelete }}</span
              >? This action cannot be undone.
            </p>
          </div>
          <div
            class="items-center px-4 py-3 sm:flex sm:flex-row-reverse sm:px-6"
          >
            <button
              @click="deleteCompany"
              class="w-full sm:w-auto sm:ml-3 mb-2 sm:mb-0 inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-red-600 text-base font-medium text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500"
            >
              Delete
            </button>
            <button
              @click="cancelDelete"
              class="w-full sm:w-auto inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
            >
              Cancel
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, ref } from 'vue'
import { useCompanyData } from '~/composables/useCompanyData'
import { useCompanyStore } from '~/stores/company'

const companyStore = useCompanyStore()

const companies = computed(() => {
  return companyStore.getCompanyList.filter((c) => c.profile?.name)
})

const { getSourcedValue } = useCompanyData()

// Delete functionality
const showDeleteModal = ref(false)
const companyToDelete = ref('')

const confirmDelete = (companyName) => {
  companyToDelete.value = companyName
  showDeleteModal.value = true
}

const cancelDelete = () => {
  showDeleteModal.value = false
  companyToDelete.value = ''
}

const deleteCompany = () => {
  if (companyToDelete.value) {
    companyStore.deleteCompany(companyToDelete.value)
    showDeleteModal.value = false
    companyToDelete.value = ''
  }
}
</script>
