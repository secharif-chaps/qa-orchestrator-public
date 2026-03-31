<template>
  <div class="flex flex-col gap-4">
    <div class="flex items-end justify-between">
      <div>
        <h2 class="text-xl font-semibold">{{ t('settings.team.title') }}</h2>
      </div>

      <Searchbar
        id="team-search"
        :model-value="searchQuery"
        :placeholder="t('settings.team.searchPlaceholder')"
        class="w-96"
        @update:model-value="handleSearchInput"
      />
    </div>

    <div class="flex flex-col gap-4">
      <!-- Loading State -->
      <div v-if="isLoading" class="flex items-center justify-center py-12">
        <div class="text-center">
          <i class="fa fa-spinner fa-spin text-primary mb-3 text-3xl"></i>
          <p class="text-secondary">
            {{ t('settings.team.loading') }}
          </p>
        </div>
      </div>

      <!-- Error State -->
      <div v-else-if="error" class="px-6 py-12">
        <Alert
          variant="danger"
          icon="fa-exclamation-circle"
          :title="t('settings.team.error.title')"
          :description="t('settings.team.error.description')"
        />
      </div>

      <!-- Team Members Table -->
      <template v-else>
        <!-- Empty state -->
        <div v-if="teamMembers.length === 0" class="p-12 text-center">
          <i class="fa fa-users text-secondary/30 mb-4 text-5xl"></i>
          <h3 class="mb-2 text-lg font-semibold">
            {{ t('settings.team.empty.title') }}
          </h3>
          <p class="text-secondary">
            {{
              searchQuery
                ? t('settings.team.empty.searchDescription')
                : t('settings.team.empty.description')
            }}
          </p>
        </div>

        <template v-else>
          <TeamMembersTable
            :members="teamMembers"
            :can-manage-team="canManageTeam"
            @update-permissions="handleUpdatePermissions"
            @reset-password="openResetPasswordModal"
          />

          <!-- Pagination -->
          <Pagination
            v-if="paginationMeta"
            v-model:current-page="currentPage"
            :meta="paginationMeta"
            :item-name="$t('settings.team.itemName')"
          />
        </template>
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
import { transformToPaginationMeta } from '@/utils/pagination'
import type { TeamMemberListItem, PermissionTier } from '@/types/team'

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
} = useQuery(() =>
  teamMembersQuery({
    page: queryParams.page,
    limit: queryParams.limit,
    search: searchQuery.value || undefined,
  }),
)

// Computed properties for data
const teamMembers = computed(() => response.value?.data || [])

// Pagination meta for custom Pagination component
const paginationMeta = computed(() => transformToPaginationMeta(response.value?.pagination))

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
