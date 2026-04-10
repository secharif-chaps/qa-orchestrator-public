<template>
  <Table :fields="fields" :items="members" :row-key="getRowKey">
    <!-- Member column (avatar + name) -->
    <template #cell(member)="{ item }">
      <td class="px-4 py-3">
        <div class="flex items-center gap-3">
          <!-- Circular Avatar -->
          <div
            class="bg-primary flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full text-sm font-semibold text-white"
          >
            {{ getMemberInitials(item) }}
          </div>

          <!-- Name + Username -->
          <div class="min-w-0">
            <div class="flex items-center gap-2 font-medium">
              {{ getMemberDisplayName(item) }}
              <Tag
                v-if="item.is_current_user"
                variant="primary"
                :label="$t('settings.team.you')"
                size="xs"
                rounded
              />
            </div>
            <div class="text-neutral-black-font text-sm">@{{ item.username }}</div>
          </div>
        </div>
      </td>
    </template>

    <!-- Email column -->
    <template #cell(email)="{ item }">
      <td class="px-4 py-3">
        <div class="text-neutral-black-font truncate text-sm">{{ item.email }}</div>
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
      <td class="px-4 py-3">
        <div class="flex items-center justify-end">
          <Button
            v-if="canManageTeam && !item.is_current_user"
            variant="tertiary"
            icon="fa fa-key"
            :title="$t('settings.team.resetPassword')"
            @click="$emit('reset-password', item)"
          />
        </div>
      </td>
    </template>
  </Table>
</template>

<script setup lang="ts">
/**
 * Team members table using Vuellar Table component.
 * Displays team members with lazy-loaded permissions and actions.
 */
import Tag from '@/components/ui/Tag.vue'
import type { PermissionTier, TeamMemberListItem } from '@/types/team'
import { Button, Table } from '@owlint/feathers-vue'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import TeamPermissionDropdown from './TeamPermissionDropdown.vue'

interface Props {
  members: TeamMemberListItem[]
  canManageTeam: boolean
}

defineProps<Props>()

defineEmits<{
  'update-permissions': [userId: string, tier: PermissionTier]
  'reset-password': [member: TeamMemberListItem]
}>()

const { t } = useI18n()

const fields = computed(() => [
  { key: 'member', label: t('settings.team.table.member') },
  { key: 'email', label: t('settings.team.table.email') },
  { key: 'permissions', label: t('settings.team.table.permissions') },
  { key: 'actions', label: t('settings.team.table.actions'), class: 'text-right' },
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
