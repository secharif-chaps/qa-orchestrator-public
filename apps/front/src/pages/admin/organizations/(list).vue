<template>
  <div class="flex flex-col gap-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-3xl font-bold">
          {{ $t('admin.organizations.title', 'Organization Management') }}
        </h1>
        <p class="text-secondary mt-2">
          {{ $t('admin.organizations.description', 'Manage all organizations in Keycloak') }}
        </p>
      </div>
    </div>

    <!-- Search -->
    <div class="flex items-center gap-4">
      <Searchbar
        id="search-organizations"
        v-model="searchQuery"
        :placeholder="$t('admin.organizations.search', 'Search organizations...')"
        class="max-w-md"
      />
    </div>

    <!-- Loading State -->
    <div v-if="isLoading" class="flex items-center justify-center py-12">
      <i class="fa fa-spinner fa-spin text-primary text-2xl"></i>
    </div>

    <!-- Error State -->
    <Alert
      v-else-if="error"
      variant="danger"
      :title="$t('admin.organizations.error.title', 'Error Loading Organizations')"
      :description="errorMessage"
    />

    <!-- Organizations List -->
    <div v-else-if="organizations && organizations.length > 0" class="flex flex-col gap-3">
      <div
        v-for="org in organizations"
        :key="org.id"
        class="bg-base-200 rounded-card border-primary-stroke hover:shadow-shadow-2 cursor-pointer border p-6 transition-all"
        @click="router.push(`/admin/organizations/${org.id}/profile`)"
      >
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-4">
            <div class="bg-primary/10 flex h-12 w-12 items-center justify-center rounded-full">
              <i class="fa fa-building text-primary text-xl"></i>
            </div>
            <div>
              <h3 class="text-lg font-semibold">{{ org.name }}</h3>
              <p class="text-secondary text-sm">
                {{ org.description || $t('admin.organization.noDescription', 'No description') }}
              </p>
              <p class="text-secondary mt-1 text-xs">ID: {{ org.id }}</p>
            </div>
          </div>
          <div class="flex items-center gap-3">
            <Tag
              variant="slate"
              :label="`${org.member_count} ${$t('admin.organizations.members', 'Members')}`"
              size="sm"
            />
            <i class="fa fa-chevron-right text-secondary"></i>
          </div>
        </div>
      </div>
    </div>

    <!-- Empty State -->
    <div v-else class="py-12 text-center">
      <i class="fa fa-building text-secondary mb-4 text-4xl"></i>
      <p class="text-secondary">{{ $t('admin.organizations.empty', 'No organizations found') }}</p>
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
import { ref, computed, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useQuery } from '@pinia/colada'
import { getAllOrganizations } from '@/api/organization'
import { Alert, Searchbar } from '@owlint/feathers-vue'
import Tag from '@/components/ui/Tag.vue'
import Pagination from '@/components/ui/Pagination.vue'
import { transformToPaginationMeta } from '@/utils/pagination'

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
