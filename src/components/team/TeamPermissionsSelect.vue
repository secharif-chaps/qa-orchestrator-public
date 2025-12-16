<template>
  <div class="space-y-4">
    <div class="pb-2">
      <h4 class="text-base font-medium mb-1">
        {{ $t('team.permissions.title', 'User Permissions') }}
      </h4>
      <p class="text-sm text-secondary">
        {{
          $t(
            'team.permissions.description',
            'Select which actions this user can perform in the organization',
          )
        }}
      </p>
    </div>

    <div v-if="canManageorganization" class="grid grid-cols-1 gap-2">
      <PermissionItem
        v-for="permission in availablePermissions"
        :key="permission.key"
        :permission="permission"
        :is-selected="permissions.includes(permission.key)"
        :disabled="disabled"
        @toggle="togglePermission"
      />
    </div>

    <div
      v-if="!canManageorganization"
      class="bg-primary/10 border border-primary/20 rounded-lg p-4"
    >
      <div class="flex items-start gap-3">
        <div class="flex-shrink-0">
          <i class="fa fa-info-circle text-secondary text-lg"></i>
        </div>
        <div class="text-sm">
          <strong class="text-base">{{
            $t('team.permissions.limitedAccess', 'Limited Access')
          }}</strong>
          <p class="text-secondary mt-1">
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
      v-if="permissions.includes('organization.write')"
      class="bg-error/10 border border-error/20 rounded-lg p-4"
    >
      <div class="flex items-start gap-3">
        <div class="flex-shrink-0">
          <i class="fa fa-exclamation-triangle text-error text-lg"></i>
        </div>
        <div class="text-sm">
          <strong class="text-base">{{ $t('team.permissions.warning', 'Important') }}</strong>
          <p class="text-secondary mt-1">
            {{
              $t(
                'team.permissions.organizationManageWarning',
                'Users with Team Management permission can add, edit, and manage other users in the organization. Grant this permission carefully.',
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
  canManageorganization?: boolean
  disabled?: boolean
}>()

const emit = defineEmits<{
  'update:permissions': [permissions: string[]]
}>()

const availablePermissions = computed(() => [
  // Basic access - can read shared folders and companies
  {
    key: 'organization.read',
    name: t('team.permissionsList.organizationRead.name', 'Basic Access'),
    description: t(
      'team.permissionsList.organizationRead.description',
      'View folders and companies shared with this user',
    ),
    icon: 'fa fa-eye',
  },
  // Organization write - can create folders, edit/delete owned folders
  {
    key: 'organization.write',
    name: t('team.permissionsList.organizationWrite.name', 'Folder Management'),
    description: t(
      'team.permissionsList.organizationWrite.description',
      'Create folders, edit and delete owned folders, manage team members',
    ),
    icon: 'fa fa-folder-plus',
  },
  // Company create - can add items to folders
  {
    key: 'company.create',
    name: t('team.permissionsList.companyCreate.name', 'Add Items'),
    description: t(
      'team.permissionsList.companyCreate.description',
      'Add company screens and other items to folders',
    ),
    icon: 'fa fa-plus-circle',
  },
])
const togglePermission = (permission: string) => {
  if (props.disabled) {
    return
  }

  if (permission === 'organization.write' && !props.canManageorganization) {
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
