<template>
  <Table :fields="fields" :items="members" :row-key="getRowKey">
    <!-- Member column (avatar + name) -->
    <template #cell(member)="{ item }">
      <td class="px-4 py-3">
        <div class="flex items-center gap-3">
          <!-- Circular Avatar -->
          <div
            class="w-10 h-10 rounded-full bg-primary text-white flex items-center justify-center font-semibold text-sm flex-shrink-0"
          >
            {{ getMemberInitials(item) }}
          </div>

          <!-- Name + Username -->
          <div class="min-w-0">
            <div class="font-medium flex items-center gap-2">
              {{ getMemberDisplayName(item) }}
              <Tag
                v-if="item.is_current_user"
                variant="primary"
                :label="$t('settings.team.you', 'You')"
                size="xs"
                rounded
              />
            </div>
            <div class="text-sm text-secondary">@{{ item.username }}</div>
          </div>
        </div>
      </td>
    </template>

    <!-- Email column -->
    <template #cell(email)="{ item }">
      <td class="px-4 py-3">
        <div class="text-sm text-secondary truncate">{{ item.email }}</div>
      </td>
    </template>

    <!-- Permissions column -->
    <template #cell(permissions)="{ item }">
      <td class="px-4 py-3">
        <TeamPermissionDropdown
          :member-id="item.id"
          :can-manage="canManageTeam && !item.is_current_user"
          @update-permissions="(tier) => $emit('update-permissions', item.id, tier)"
        />
      </td>
    </template>

    <!-- Actions column -->
    <template #cell(actions)="{ item }">
      <td class="px-4 py-3 ">
        <div class="flex justify-end items-center">

        <Button
          v-if="canManageTeam && !item.is_current_user"
          variant="tertiary"
          icon="fa fa-key"
          :title="$t('settings.team.resetPassword', 'Reset Password')"
          @click="$emit('reset-password', item)"
        />
      </div>

      </td>
    </template>

    <!-- Empty state -->
    <template #empty>
      <div class="p-12 text-center">
        <i class="fa fa-users text-5xl text-secondary/30 mb-4"></i>
        <h3 class="text-lg font-semibold mb-2">
          {{ $t('settings.team.empty.title', 'No team members found') }}
        </h3>
        <p class="text-secondary">
          {{
            hasSearch
              ? $t('settings.team.empty.searchDescription', 'Try a different search term')
              : $t('settings.team.empty.description', 'No team members in your organization')
          }}
        </p>
      </div>
    </template>
  </Table>
</template>

<script setup lang="ts">
/**
 * Team members table using Vuellar Table component.
 * Displays team members with lazy-loaded permissions and actions.
 */
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { Table, Button } from '@owlint/feathers-vue'
import Tag from '@/components/ui/Tag.vue'
import TeamPermissionDropdown from './TeamPermissionDropdown.vue'
import type { TeamMemberListItem, PermissionTier } from '@/types/team'

interface Props {
  members: TeamMemberListItem[]
  canManageTeam: boolean
  hasSearch: boolean
}

defineProps<Props>()

defineEmits<{
  'update-permissions': [userId: string, tier: PermissionTier]
  'reset-password': [member: TeamMemberListItem]
}>()

const { t } = useI18n()

const fields = computed(() => [
  { key: 'member', label: t('settings.team.table.member', 'Member') },
  { key: 'email', label: t('settings.team.table.email', 'Email') },
  { key: 'permissions', label: t('settings.team.table.permissions', 'Permissions') },
  { key: 'actions', label: t('settings.team.table.actions', 'Actions'), class: 'text-right' },
])

// Helper to get row key for table
const getRowKey = (item: TeamMemberListItem): string => item.id

// Helper to get member initials
const getMemberInitials = (member: TeamMemberListItem): string => {
  if (member.first_name && member.last_name) {
    return `${member.first_name[0]}${member.last_name[0]}`.toUpperCase()
  }
  return member.username.substring(0, 2).toUpperCase()
}

// Helper to get member display name
const getMemberDisplayName = (member: TeamMemberListItem): string => {
  if (member.first_name && member.last_name) {
    return `${member.first_name} ${member.last_name}`
  }
  return member.username
}
</script>
