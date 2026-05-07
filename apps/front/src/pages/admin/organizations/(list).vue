<template>
  <div class="flex flex-col gap-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-3xl font-bold">
          {{ $t('admin.organizations.title') }}
        </h1>
        <p class="text-neutral-black-font mt-2">
          {{ $t('admin.organizations.description') }}
        </p>
      </div>
    </div>

    <!-- Search -->
    <div class="flex items-center gap-4">
      <Searchbar
        id="search-organizations"
        v-model="searchQuery"
        :placeholder="$t('admin.organizations.search')"
        class="max-w-112"
      />
    </div>

    <!-- Loading State -->
    <OrganizationsListSkeleton v-if="isLoading" />

    <!-- Error State -->
    <Alert
      v-else-if="error"
      variant="danger"
      :title="$t('admin.organizations.error.title')"
      :description="errorMessage"
    />

    <!-- Organizations List -->
    <div v-else-if="organizations && organizations.length > 0" class="flex flex-col gap-3">
      <div
        v-for="org in organizations"
        :key="org.id"
        class="bg-primary-lightest rounded-card border-primary-lighter-stroke hover:shadow-2 cursor-pointer border p-6 transition-all"
        @click="router.push(`/admin/organizations/${org.id}/profile`)"
      >
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-4">
            <div class="bg-primary/10 flex h-12 w-12 items-center justify-center rounded-full">
              <i class="fa fa-building text-primary text-xl"></i>
            </div>
            <div>
              <h3 class="text-lg font-semibold">{{ org.name }}</h3>
              <p class="text-neutral-black-font text-sm">
                {{ org.description || $t('admin.organization.noDescription') }}
              </p>
              <p class="text-neutral-black-font mt-1 text-xs">ID: {{ org.id }}</p>
            </div>
          </div>
          <div class="flex items-center gap-3">
            <Tag
              intent="neutral"
              :label="`${org.member_count} ${$t('admin.organizations.members')}`"
              size="sm"
            />
            <i class="fa fa-chevron-right text-neutral-black-font"></i>
          </div>
        </div>
      </div>
    </div>

    <!-- Empty State -->
    <div v-else class="py-12 text-center">
      <i class="fa fa-building text-neutral-black-font mb-4 text-4xl"></i>
      <p class="text-neutral-black-font">{{ $t('admin.organizations.empty') }}</p>
    </div>

    <!-- Pagination -->
    <Pagination
      v-if="paginationMeta"
      v-model:current-page="currentPage"
      :meta="paginationMeta"
      :item-name="$t('admin.organizations.itemName')"
      @update-per-page="updatePageSize"
    />
  </div>
</template>

<route lang="yaml">
meta:
  permissions:
    - admin.organizations
  requiresAuth: true
  title: 'Organization Management'
</route>

<script setup lang="ts">
import { getAllOrganizations } from '@/api/organization'
import Pagination from '@/components/ui/Pagination.vue'
import { transformToPaginationMeta } from '@/utils/pagination'
import OrganizationsListSkeleton from '@/components/admin/OrganizationsListSkeleton.vue'
import { Alert, Searchbar, Tag } from '@owlint/feathers-vue'
import { useQuery } from '@pinia/colada'
import { computed, ref, watch } from 'vue'
import { useRouter } from 'vue-router'

const router = useRouter()

// State
const searchQuery = ref('')
const currentPage = ref(1)
const pageSize = ref(20)

// Fetch organizations with current filters
const {
  data: organizationsData,
  isLoading,
  error,
} = useQuery({
  key: computed(() => ['organizations', currentPage.value, pageSize.value, searchQuery.value]),
  query: async () => {
    return getAllOrganizations({
      page: currentPage.value,
      limit: pageSize.value,
      search: searchQuery.value || undefined,
      sort: 'name',
      order: 'asc',
    })
  },
})

// Computed
const organizations = computed(() => organizationsData.value?.data || [])

// Transform API meta to PaginationMeta format
const paginationMeta = computed(() => transformToPaginationMeta(organizationsData.value?.meta))

// Extract error message safely
const errorMessage = computed(() => {
  const err = error.value
  if (!err) return ''
  if (typeof err === 'string') return err
  if (err instanceof Error) return err.message
  if (typeof err === 'object' && 'message' in err)
    return String((err as { message: unknown }).message)
  return 'An error occurred'
})

// Methods
function updatePageSize(newSize: number) {
  pageSize.value = newSize
  currentPage.value = 1
}

// Watch search query and reset to page 1
watch(searchQuery, () => {
  currentPage.value = 1
})
</script>
