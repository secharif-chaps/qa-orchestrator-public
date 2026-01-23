<template>
  <div class="bg-base-100 border border-primary-stroke rounded-card">
    <div class="px-6 py-4 border-b border-primary-stroke flex justify-between items-center">
      <div>
        <h2 class="text-lg font-semibold">{{ t('settings.team.title', 'Team Management') }}</h2>
        <p class="text-sm text-secondary mt-1">
          {{ t('settings.team.description', 'Manage team members and their permissions') }}
        </p>
      </div>

      <Searchbar
        id="team-search"
        :model-value="searchQuery"
        :placeholder="t('settings.team.searchPlaceholder', 'Search by name, email, or username')"
        class="w-96"
        @update:model-value="handleSearchInput"
      />
    </div>

    <div class="flex flex-col gap-4">
      <!-- Loading State -->
      <div v-if="isLoading" class="flex items-center justify-center py-12">
        <div class="text-center">
          <i class="fa fa-spinner fa-spin text-3xl text-primary mb-3"></i>
          <p class="text-secondary">
            {{ t('settings.team.loading', 'Loading team members...') }}
          </p>
        </div>
      </div>

      <!-- Error State -->
      <div v-else-if="error" class="py-12 px-6">
        <Alert
          variant="danger"
          icon="fa-exclamation-circle"
          :title="t('settings.team.error.title', 'Error')"
          :description="t('settings.team.error.description', 'Failed to load team members')"
        />
      </div>

      <!-- Team Members Table -->
      <template v-else>
        <TeamMembersTable
          :members="teamMembers"
          :can-manage-team="canManageTeam"
          :has-search="!!searchQuery"
          @update-permissions="handleUpdatePermissions"
          @reset-password="openResetPasswordModal"
        />

        <!-- Pagination -->
        <div v-if="teamMembers.length > 0 && paginationMeta" class="px-6 pb-4">
          <Pagination
            v-model:current-page="currentPage"
            :meta="paginationMeta"
            item-name="members"
          />
        </div>
      </template>

      <!-- Reset Password Modal -->
      <ResetPasswordModal
        v-if="selectedMember && canManageTeam"
        :member="selectedMember"
        @close="closePasswordModal"
      />
    </div>
  </div>
</template>

<route lang="yaml">
meta:
  permissions:
    - organization.read
requiresAuth: true
title: 'Team Management'
</route>

<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { useQuery } from '@pinia/colada'
import { Alert, Searchbar } from '@owlint/feathers-vue'
import { useI18n } from 'vue-i18n'
import { teamMembersQuery } from '@/queries/team'
import { useUpdateMemberPermissions } from '@/mutations/team'
import { useTeamPermissions } from '@/composables/useTeamPermissions'
import TeamMembersTable from '@/components/team/TeamMembersTable.vue'
import ResetPasswordModal from '@/components/team/ResetPasswordModal.vue'
import Pagination from '@/components/ui/Pagination.vue'
import type { TeamMemberListItem, PermissionTier } from '@/types/team'
import type { PaginationMeta } from '@/types/pagination'

const { t } = useI18n()
const { canManageTeam } = useTeamPermissions()

const searchQuery = ref('')

// Query parameters state
const queryParams = reactive({
  page: 1,
  limit: 10,
})

// Query team members with pagination
const {
  data: response,
  isLoading,
  error,
} = useQuery(teamMembersQuery, () => ({
  page: queryParams.page,
  limit: queryParams.limit,
  search: searchQuery.value || undefined,
}))

// Computed properties for data
const teamMembers = computed(() => response.value?.data || [])

// Pagination meta for custom Pagination component
const paginationMeta = computed<PaginationMeta | null>(() => {
  if (!response.value?.pagination) return null

  const p = response.value.pagination
  const from = (p.page - 1) * p.limit + 1
  const to = Math.min(p.page * p.limit, p.total)

  return {
    total: p.total,
    per_page: p.limit,
    current_page: p.page,
    last_page: p.total_pages,
    from,
    to,
  }
})

// Pagination
const currentPage = computed({
  get: () => queryParams.page,
  set: (value: number) => {
    queryParams.page = value
  },
})

// Handle search input (Searchbar emits string | number)
const handleSearchInput = (value: string | number) => {
  searchQuery.value = String(value)
}

// Reset to page 1 when search changes
watch(searchQuery, () => {
  queryParams.page = 1
})

// Mutations
const { updatePermissions } = useUpdateMemberPermissions()

// Password reset modal state
const selectedMember = ref<TeamMemberListItem | null>(null)

const handleUpdatePermissions = (userId: string, tier: PermissionTier) => {
  updatePermissions({ userId, data: { permission_tier: tier } })
}

const openResetPasswordModal = (member: TeamMemberListItem) => {
  selectedMember.value = member
}

const closePasswordModal = () => {
  selectedMember.value = null
}
</script>
