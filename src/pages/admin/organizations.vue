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

    <!-- Search and Filters -->
    <div class="flex items-center justify-between gap-4">
      <!-- Search Input -->
      <div class="flex-1 max-w-md">
        <div class="relative">
          <i
            class="fa fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-secondary"
          ></i>
          <Input
            v-model="searchQuery"
            type="text"
            :placeholder="$t('admin.organizations.search', 'Search organizations...')"
            class="pl-10"
          />
        </div>
      </div>

      <div class="flex items-center gap-2">
        <!-- Sort Dropdown -->
        <div class="relative">
          <button
            @click="showSortDropdown = !showSortDropdown"
            class="flex items-center gap-2 px-3 py-2 border border-primary-stroke rounded-lg hover:bg-base-200 transition-colors text-sm font-medium bg-base-100"
          >
            <span class="text-secondary">{{
              $t('admin.organizations.sortBy', 'Sort by: Name')
            }}</span>
            <i
              class="fa fa-chevron-down text-xs transition-transform"
              :class="{ 'rotate-180': showSortDropdown }"
            ></i>
          </button>
        </div>
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="isLoading" class="flex items-center justify-center py-12">
      <i class="fa fa-spinner fa-spin text-2xl text-primary"></i>
    </div>

    <!-- Error State -->
    <Alert
      v-else-if="error"
      variant="error"
      :title="$t('admin.organizations.error.title', 'Error Loading Organizations')"
      :message="error.message"
      icon="fa fa-exclamation-circle"
    />

    <!-- Organizations List -->
    <div v-else-if="organizations && organizations.length > 0" class="flex flex-col gap-3">
      <div
        v-for="org in organizations"
        :key="org.id"
        class="bg-base-200 rounded-card border border-primary-stroke p-6 hover:shadow-shadow-2 transition-all cursor-pointer"
        @click="router.push(`/admin/organizations/${org.id}`)"
      >
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-4">
            <div class="w-12 h-12 rounded-full bg-primary/10 flex items-center justify-center">
              <i class="fa fa-building text-primary text-xl"></i>
            </div>
            <div>
              <h3 class="text-lg font-semibold">{{ org.name }}</h3>
              <p class="text-sm text-secondary">{{ org.description || 'No description' }}</p>
              <p class="text-xs text-secondary mt-1">ID: {{ org.id }}</p>
            </div>
          </div>
          <div class="flex items-center gap-3">
            <Tag variant="slate" :label="`${org.member_count} members`" size="sm" />
            <i class="fa fa-chevron-right text-secondary"></i>
          </div>
        </div>
      </div>
    </div>

    <!-- Empty State -->
    <div v-else class="text-center py-12">
      <i class="fa fa-building text-4xl text-secondary mb-4"></i>
      <p class="text-secondary">{{ $t('admin.organizations.empty', 'No organizations found') }}</p>
    </div>

    <!-- Pagination -->
    <div v-if="organizations && organizations.length > 0" class="flex items-center justify-between">
      <p class="text-sm text-secondary">
        {{
          $t('admin.organizations.showing', 'Showing {from} to {to} of {total}', {
            from: (currentPage - 1) * pageSize + 1,
            to: Math.min(currentPage * pageSize, totalOrganizations),
            total: totalOrganizations,
          })
        }}
      </p>
      <div class="flex items-center gap-2">
        <Button
          variant="tertiary"
          icon="fa fa-chevron-left"
          size="sm"
          :disabled="currentPage === 1"
          @click="previousPage"
        />
        <span class="text-sm">{{ currentPage }}</span>
        <Button
          variant="tertiary"
          icon="fa fa-chevron-right"
          size="sm"
          :disabled="currentPage * pageSize >= totalOrganizations"
          @click="nextPage"
        />
      </div>
    </div>
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
import Alert from '@/components/ui/Alert.vue'
import Tag from '@/components/ui/Tag.vue'
import Button from '@/components/ui/Button.vue'
import Input from '@/components/ui/Input.vue'

const router = useRouter()

// State
const searchQuery = ref('')
const showSortDropdown = ref(false)
const currentPage = ref(1)
const pageSize = ref(20)

// Fetch organizations with current filters
const {
  data: organizationsData,
  isLoading,
  error,
  refetch,
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
const totalOrganizations = computed(() => organizationsData.value?.meta?.total || 0)

// Methods
function navigateToOrganization(orgId: string) {
  router.push(`/admin/organizations/${orgId}`)
}

function previousPage() {
  if (currentPage.value > 1) {
    currentPage.value--
  }
}

function nextPage() {
  if (currentPage.value * pageSize.value < totalOrganizations.value) {
    currentPage.value++
  }
}

// Watch search query and reset to page 1
watch(searchQuery, () => {
  currentPage.value = 1
})
</script>
