<template>
  <div class="px-6 py-4 transition-colors hover:bg-base-200/50">
    <div class="grid grid-cols-12 gap-4 items-center">
      <!-- Username -->
      <div class="col-span-2">
        <div class="font-medium">{{ user.username }}</div>
        <div
          v-if="user.status === 'revoked'"
          class="text-xs text-error mt-1 flex items-center gap-1"
        >
          <i class="fa fa-ban"></i>
          {{ $t('admin.users.status.revoked', 'Revoked') }}
        </div>
      </div>

      <!-- Email -->
      <div class="col-span-3">
        <div class="text-sm text-secondary">{{ user.email }}</div>
      </div>

      <!-- Organization -->
      <div class="col-span-2">
        <Tag
          v-if="user.organization_name"
          :label="user.organization_name"
          variant="primary"
          size="sm"
        />
        <span v-else class="text-sm text-secondary italic">
          {{ $t('admin.users.noOrganization', 'No organization') }}
        </span>
      </div>

      <!-- Permissions -->
      <div class="col-span-4">
        <div class="flex flex-wrap gap-2">
          <!-- Show role tag if permissions match a role -->
          <Tag v-if="userRole" :label="userRole.name" variant="primary" size="sm" />
          <!-- Otherwise show individual permission tags -->
          <template v-else>
            <Tag
              v-for="permission in displayPermissions"
              :key="permission"
              :label="permission"
              variant="slate"
              size="sm"
            >
              {{ permission }}
            </Tag>
          </template>
          <!-- Show "No permissions" if user has no permissions -->
          <span v-if="displayPermissions.length === 0" class="text-sm text-secondary italic">
            {{ $t('admin.users.noPermissions', 'No permissions') }}
          </span>
        </div>
      </div>

      <!-- Actions -->
      <div class="col-span-1">
        <div class="flex justify-end">
          <UserActionsDropdown
            @change-organization="$emit('change-organization', user)"
            @manage-permissions="$emit('manage-permissions', user)"
            @disable-user="$emit('disable-user', user)"
            @reset-password="$emit('reset-password', user)"
          />
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import type { AdminUserResponse } from '@/types/admin-user'
import { useRoles } from '@/composables/useRoles'
import Tag from '@/components/ui/Tag.vue'
import UserActionsDropdown from './UserActionsDropdown.vue'

interface Props {
  user: AdminUserResponse
}

const props = defineProps<Props>()

defineEmits<{
  'change-organization': [user: AdminUserResponse]
  'manage-permissions': [user: AdminUserResponse]
  'disable-user': [user: AdminUserResponse]
  'reset-password': [user: AdminUserResponse]
}>()

const { getUserRole } = useRoles()

// Check if user's permissions match a predefined role
const userRole = computed(() => getUserRole(props.user.permissions))

const displayedPermissions = [
  'organization.read',
  'organization.write',
  'company.view',
  'company.create',
  'company.delete',
  'admin.organizations',
]

const displayPermissions = computed(() => {
  return displayedPermissions.filter((permission) => props.user.permissions.includes(permission))
})
</script>
