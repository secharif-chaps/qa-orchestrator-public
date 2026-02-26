<template>
  <Table :fields="fields" :items="users" :row-key="getRowKey">
    <!-- Username column -->
    <template #cell(username)="{ item }">
      <td class="px-4 py-3">
        <div class="font-medium">{{ item.username }}</div>
      </td>
    </template>

    <!-- Email column -->
    <template #cell(email)="{ item }">
      <td class="px-4 py-3">
        <div class="text-secondary text-sm">{{ item.email }}</div>
      </td>
    </template>

    <!-- Name column -->
    <template #cell(name)="{ item }">
      <td class="px-4 py-3">
        <div class="text-sm">
          <template v-if="getFullName(item)">{{ getFullName(item) }}</template>
          <span v-else class="text-secondary italic">—</span>
        </div>
      </td>
    </template>

    <!-- Permission Tier column -->
    <template #cell(permission_tier)="{ item }">
      <td class="px-4 py-3">
        <Tag
          v-if="item.permission_tier"
          :label="getPermissionTierLabel(item.permission_tier)"
          :variant="getRoleVariant(item.permission_tier)"
          size="sm"
        />
        <Tag v-else :label="$t('admin.users.roles.custom', 'Custom')" variant="slate" size="sm" />
      </td>
    </template>

    <!-- Organization column -->
    <template #cell(organization)="{ item }">
      <td class="px-4 py-3">
        <div class="text-sm">
          <template v-if="item.organization_name">{{ item.organization_name }}</template>
          <span v-else class="text-secondary italic">—</span>
        </div>
      </td>
    </template>

    <!-- Status column -->
    <template #cell(status)="{ item }">
      <td class="px-4 py-3">
        <Tag
          v-if="item.status === 'active'"
          :label="$t('admin.users.status.active', 'Active')"
          variant="success"
          size="sm"
        />
        <Tag
          v-else
          :label="$t('admin.users.status.revoked', 'Revoked')"
          variant="error"
          size="sm"
        />
      </td>
    </template>

    <!-- Actions column -->
    <template #cell(actions)="{ item }">
      <td class="px-4 py-3 text-right">
        <UserActionsDropdown
          :user-status="item.status"
          @change-organization="emit('change-organization', item)"
          @manage-permissions="emit('manage-permissions', item)"
          @disable-user="emit('disable-user', item)"
          @enable-user="emit('enable-user', item)"
          @reset-password="emit('reset-password', item)"
        />
      </td>
    </template>

    <!-- Empty state -->
    <template #empty>
      <div class="p-12 text-center">
        <i class="fa fa-users text-secondary/50 mb-4 text-4xl"></i>
        <h3 class="mb-2 text-base text-lg font-medium">
          {{
            hasFilters
              ? $t('admin.users.empty.filtered', 'No users found')
              : $t('admin.users.empty.title', 'No users found')
          }}
        </h3>
        <p class="text-secondary mb-6">
          {{
            hasFilters
              ? $t('admin.users.empty.filteredDescription', 'Try a different search or filter')
              : $t('admin.users.empty.description', 'No users in the system')
          }}
        </p>
        <Button
          v-if="hasFilters"
          variant="secondary"
          :label="$t('admin.users.clearFilters', 'Clear Filters')"
          @click="emit('clear-filters')"
        />
      </div>
    </template>
  </Table>
</template>

<script setup lang="ts">
/**
 * Admin users table using Vuellar Table component.
 * Displays users with actions for organization assignment, permissions, etc.
 */
import Tag from '@/components/ui/Tag.vue'
import type { AdminUserListItem } from '@/types/admin-user'
import { Button, Table } from '@owlint/feathers-vue'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import UserActionsDropdown from './UserActionsDropdown.vue'

interface Props {
  users: AdminUserListItem[]
  hasFilters: boolean
}

interface Emits {
  'change-organization': [user: AdminUserListItem]
  'manage-permissions': [user: AdminUserListItem]
  'disable-user': [user: AdminUserListItem]
  'enable-user': [user: AdminUserListItem]
  'reset-password': [user: AdminUserListItem]
  'clear-filters': []
}

defineProps<Props>()

const emit = defineEmits<Emits>()

const { t } = useI18n()

const fields = computed(() => [
  { key: 'username', label: t('admin.users.table.username', 'Username') },
  { key: 'email', label: t('admin.users.table.email', 'Email') },
  { key: 'name', label: t('admin.users.table.name', 'Name') },
  { key: 'permission_tier', label: t('admin.users.table.role', 'Role') },
  { key: 'organization', label: t('admin.users.table.organization', 'Organization') },
  { key: 'status', label: t('admin.users.table.status', 'Status') },
  { key: 'actions', label: t('admin.users.table.actions', 'Actions'), class: 'text-right' },
])

// Helper to get localized permission tier label
const getPermissionTierLabel = (tier: string): string => {
  const tierLabels: Record<string, string> = {
    reader: t('admin.users.roles.reader', 'Reader'),
    writer: t('admin.users.roles.writer', 'Writer'),
    manager: t('admin.users.roles.manager', 'Manager'),
    admin: t('admin.users.roles.admin', 'Admin'),
  }
  return tierLabels[tier] || tier
}

// Helper to get tag variant based on role (admin = accent/rose, others = success/green)
const getRoleVariant = (tier: string): 'success' | 'accent' => {
  return tier === 'admin' ? 'accent' : 'success'
}

// Helper to get row key for table
const getRowKey = (item: AdminUserListItem): string => item.user_id

// Helper to get full name from user
const getFullName = (user: AdminUserListItem): string | null => {
  const parts = [user.first_name, user.last_name].filter(Boolean)
  return parts.length > 0 ? parts.join(' ') : null
}
</script>
