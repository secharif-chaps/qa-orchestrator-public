<template>
  <div class="bg-base-100 border border-primary-stroke rounded-card">
    <div class="px-6 py-4 border-b border-primary-stroke flex justify-between items-center">
      <div>
        <h2 class="text-lg font-semibold">{{ t('settings.team.title', 'Team Management') }}</h2>
        <p class="text-sm text-secondary mt-1">
          {{ t('settings.team.description', 'Manage team members and their permissions') }}
        </p>
      </div>

      <div class="max-w-md min-w-xs w-full">
        <Input
          v-model="searchQuery"
          id="searchPlaceholder"
          icon="fa fa-search"
          :placeholder="t('settings.team.searchPlaceholder', 'Search by name, email, or username')"
          clearable
        />
      </div>
    </div>
    <div class="">
      <div class="flex flex-col gap-6">
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
        <div v-else-if="error" class="py-12">
          <Alert
            variant="danger"
            icon="fa-exclamation-circle"
            :title="t('settings.team.error.title', 'Error')"
            :description="t('settings.team.error.description', 'Failed to load team members')"
          />
        </div>

        <!-- Empty State -->
        <div v-else-if="!teamMembers?.length" class="py-12 text-center">
          <i class="fa fa-users text-5xl text-secondary/30 mb-4"></i>
          <h3 class="text-lg font-semibold mb-2">
            {{ t('settings.team.empty.title', 'No team members found') }}
          </h3>
          <p class="text-secondary">
            {{
              searchQuery
                ? t('settings.team.empty.searchDescription', 'Try a different search term')
                : t('settings.team.empty.description', 'No team members in your organization')
            }}
          </p>
        </div>

        <!-- Team Members Table -->
        <div v-else class="bg-base-100 rounded-lg border border-primary-stroke overflow-visible">
          <!-- Table Header -->
          <div class="px-6 py-4 border-b border-primary-stroke bg-base-200">
            <div class="grid grid-cols-12 gap-4 text-sm font-medium text-secondary">
              <div class="col-span-4">{{ t('settings.team.table.member', 'Member') }}</div>
              <div class="col-span-3">{{ t('settings.team.table.email', 'Email') }}</div>
              <div class="col-span-3">{{ t('settings.team.table.permissions', 'Permissions') }}</div>
              <div class="col-span-2 text-right">{{ t('settings.team.table.actions', 'Actions') }}</div>
            </div>
          </div>

          <!-- Team Member Rows -->
          <div class="divide-y divide-primary-stroke overflow-visible">
            <TeamMemberRow
              v-for="member in teamMembers"
              :key="member.id"
              :member="member"
              :can-manage="canManageTeam && !member.is_current_user"
              @update-permissions="handleUpdatePermissions"
              @reset-password="handleResetPassword"
            />
          </div>
        </div>

        <ResetPasswordModal
          v-if="showPasswordModal && canManageTeam"
          :temporary-password="tempPassword"
          :member="selectedMember!"
          @close="closePasswordModal"
        />
      </div>
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
import { ref } from 'vue'
import { useQuery } from '@pinia/colada'
import { Input, Alert } from '@owlint/feathers-vue'
import { useI18n } from 'vue-i18n'
import { teamMembersQuery } from '@/queries/team'
import { useUpdateMemberPermissions, useResetMemberPassword } from '@/mutations/team'
import { useTeamPermissions } from '@/composables/useTeamPermissions'
import TeamMemberRow from '@/components/team/TeamMemberRow.vue'
import ResetPasswordModal from '@/components/team/ResetPasswordModal.vue'
import type { TeamMember, PermissionTier } from '@/types/team'

const { t } = useI18n()
const { canManageTeam } = useTeamPermissions()

const searchQuery = ref('')

// Query team members with search
const {
  data: teamMembers,
  isLoading,
  error,
} = useQuery(teamMembersQuery, () => ({
  search: searchQuery.value,
}))

// Mutations
const { updatePermissions } = useUpdateMemberPermissions()
const { resetPassword } = useResetMemberPassword()

// Password reset modal state
const showPasswordModal = ref(false)
const tempPassword = ref('')
const selectedMember = ref<TeamMember | null>(null)

function handleUpdatePermissions(userId: string, tier: PermissionTier) {
  updatePermissions({ userId, data: { permission_tier: tier } })
}

async function handleResetPassword(member: TeamMember) {
  try {
    const result = await resetPassword(member.id)
    if (result) {
      selectedMember.value = member
      tempPassword.value = result.temporary_password
      showPasswordModal.value = true
    }
  } catch (error) {
    console.error('Failed to reset password:', error)
  }
}

function closePasswordModal() {
  showPasswordModal.value = false
  tempPassword.value = ''
  selectedMember.value = null
}
</script>
