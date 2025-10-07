<template>
  <div class="space-y-4">
    <div class="pb-2">
      <h4 class="text-base font-medium mb-1">
        {{ $t('team.permissions.title', 'User Permissions') }}
      </h4>
      <p class="text-sm text-primary-light-content">
        {{
          $t(
            'team.permissions.description',
            'Select which actions this user can perform in the workspace',
          )
        }}
      </p>
    </div>

    <div v-if="canManageWorkspace" class="grid grid-cols-1 gap-2">
      <PermissionItem
        v-for="permission in availablePermissions"
        :key="permission.key"
        :permission="permission"
        :is-selected="permissions.includes(permission.key)"
        :disabled="disabled"
        @toggle="togglePermission"
      />
    </div>

    <div v-if="!canManageWorkspace" class="bg-primary/10 border border-primary/20 rounded-lg p-4">
      <div class="flex items-start gap-3">
        <div class="flex-shrink-0">
          <i class="fa fa-info-circle text-primary-light-content text-lg"></i>
        </div>
        <div class="text-sm">
          <strong class="text-base">{{
            $t('team.permissions.limitedAccess', 'Limited Access')
          }}</strong>
          <p class="text-primary-light-content mt-1">
            {{
              $t(
                'team.permissions.limitedDescription',
                'You can only assign permissions you have yourself. The Team Management permission requires administrative access.',
              )
            }}
          </p>
        </div>
      </div>
    </div>

    <div
      v-if="permissions.includes('workspace.write')"
      class="bg-error/10 border border-error/20 rounded-lg p-4"
    >
      <div class="flex items-start gap-3">
        <div class="flex-shrink-0">
          <i class="fa fa-exclamation-triangle text-error text-lg"></i>
        </div>
        <div class="text-sm">
          <strong class="text-base">{{ $t('team.permissions.warning', 'Important') }}</strong>
          <p class="text-primary-light-content mt-1">
            {{
              $t(
                'team.permissions.workspaceManageWarning',
                'Users with Team Management permission can add, edit, and manage other users in the workspace. Grant this permission carefully.',
              )
            }}
          </p>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import PermissionItem from './PermissionItem.vue'

const { t } = useI18n()

const props = defineProps<{
  permissions: string[]
  canManageWorkspace?: boolean
  disabled?: boolean
}>()

const emit = defineEmits<{
  'update:permissions': [permissions: string[]]
}>()

const availablePermissions = computed(() => [
  // Basic permissions
  {
    key: 'workspace.read',
    name: t('team.permissionsList.workspaceRead.name', 'Basic Access'),
    description: t(
      'team.permissionsList.workspaceRead.description',
      'View workspace content and companies',
    ),
    icon: 'fa fa-eye',
  },
  // Company permissions
  {
    key: 'company.view',
    name: t('team.permissionsList.companyView.name', 'View Companies'),
    description: t(
      'team.permissionsList.companyView.description',
      'Access detailed company information',
    ),
    icon: 'fa fa-building',
  },
  {
    key: 'company.create',
    name: t('team.permissionsList.companyCreate.name', 'Create Companies'),
    description: t(
      'team.permissionsList.companyCreate.description',
      'Add new companies to the workspace',
    ),
    icon: 'fa fa-plus-circle',
  },
  {
    key: 'company.delete',
    name: t('team.permissionsList.companyDelete.name', 'Delete Companies'),
    description: t(
      'team.permissionsList.companyDelete.description',
      'Remove companies from the workspace',
    ),
    icon: 'fa fa-trash',
  },
  // Admin permission
  {
    key: 'workspace.write',
    name: t('team.permissionsList.workspaceWrite.name', 'Team Management'),
    description: t(
      'team.permissionsList.workspaceWrite.description',
      'Manage workspace users and settings',
    ),
    icon: 'fa fa-users-cog',
  },
])
const togglePermission = (permission: string) => {
  if (props.disabled) {
    return
  }

  if (permission === 'workspace.write' && !props.canManageWorkspace) {
    return
  }

  const currentPermissions = [...props.permissions]
  const index = currentPermissions.indexOf(permission)

  if (index > -1) {
    currentPermissions.splice(index, 1)
  } else {
    currentPermissions.push(permission)
  }

  emit('update:permissions', currentPermissions)
}
</script>
