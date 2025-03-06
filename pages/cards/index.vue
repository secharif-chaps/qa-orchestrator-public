<template>
  <div class="px-4 sm:px-6 lg:px-8">
    <div class="sm:flex sm:items-center">
      <div class="sm:flex-auto">
        <h1 class="text-3xl font-semibold text-primary">Cards</h1>
      </div>
      <div class="mt-4 sm:ml-16 sm:mt-0 sm:flex-none flex space-x-4">
        <PrimaryButton size="large">Add user</PrimaryButton>
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
        </TertiaryButton>
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
                    class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900"
                  >
                    Shared by
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
                  @click="$router.push('/cards/' + company.profile.name)"
                  class="cursor-pointer hover:bg-emerald-50 group"
                >
                  <td
                    class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-6 group-hover:text-primary"
                  >
                    {{ company.profile.name }}
                  </td>
                  <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">
                    todo
                  </td>
                  <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">
                    todo
                  </td>
                  <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">
                    todo
                  </td>
                  <td
                    class="relative whitespace-nowrap py-4 pl-3 pr-4 text-right text-sm font-medium sm:pr-6"
                  >
                    <i class="fa fa-ellipsis"></i>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
const companyStore = useCompanyStore()

const companies = computed(() => {
  return companyStore.getCompanyList
})
</script>
