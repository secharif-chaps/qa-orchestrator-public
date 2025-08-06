<template>
  <div class="px-6 py-4 hover:bg-bg2/50 transition-colors">
    <div class="grid grid-cols-12 gap-4 items-center">
      <div class="col-span-4">
        <div class="flex items-center gap-3">
          <div
            class="w-10 h-10 rounded-full flex items-center justify-center text-white font-medium"
            :class="user.is_disabled ? 'bg-gray-400' : 'bg-primary'"
          >
            {{ userInitials }}
          </div>
          <div>
            <div class="font-medium" :class="user.is_disabled ? 'text-gray-500' : 'text-base'">
              @{{ user.username }}
            </div>
            <div class="text-sm" :class="user.is_disabled ? 'text-gray-400' : 'text-secondary'">
              {{ user.email }}
            </div>
          </div>
        </div>
      </div>

      <div class="col-span-3">
        <div class="flex flex-wrap gap-1">
          <span
            v-for="permission in displayPermissions"
            :key="permission"
            class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium"
            :class="user.is_disabled ? 'bg-gray-100 text-gray-500' : 'bg-primary/10 text-primary'"
          >
            {{ formatPermission(permission) }}
          </span>
          <span
            v-if="user.permissions && user.permissions.length > 3"
            class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium"
            :class="user.is_disabled ? 'bg-gray-100 text-gray-500' : 'bg-gray-100 text-gray-600'"
          >
            +{{ user.permissions.length - 3 }}
          </span>
        </div>
      </div>

      <div class="col-span-2">
        <div class="text-sm" :class="user.is_disabled ? 'text-gray-400' : 'text-secondary'">
          {{ formatDate(user.created_at) }}
        </div>
      </div>

      <div class="col-span-2">
        <div class="flex items-center gap-2">
          <span
            class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium"
            :class="
              user.is_disabled
                ? 'bg-red-400/20 text-red-800 dark:text-red-400'
                : 'bg-green-400/20 text-green-800 dark:text-green-400'
            "
          >
            <i :class="user.is_disabled ? 'fa fa-ban' : 'fa fa-check-circle'" class="mr-1"></i>
            {{
              user.is_disabled
                ? $t('team.status.disabled', 'Disabled')
                : $t('team.status.active', 'Active')
            }}
          </span>
        </div>
      </div>

      <div class="col-span-1">
        <div v-if="canManageUsers" class="flex items-center gap-2">
          <button
            @click="$emit('edit-user', user)"
            class="text-primary hover:text-primary/80 transition-colors p-2"
            :title="$t('team.edit', 'Edit user')"
          >
            <i class="fa fa-edit"></i>
          </button>

          <button
            v-if="user.is_disabled"
            @click="$emit('enable-user', user.id)"
            class="text-green-600 hover:text-green-700 transition-colors p-2"
            :title="$t('team.enable', 'Enable user')"
          >
            <i class="fa fa-check"></i>
          </button>

          <button
            v-else
            @click="$emit('disable-user', user.id)"
            class="text-red-600 hover:text-red-700 transition-colors p-2"
            :title="$t('team.disable', 'Disable user')"
          >
            <i class="fa fa-ban"></i>
          </button>
        </div>
        <div v-else class="flex items-center justify-center">
          <span class="text-xs text-secondary">{{ $t('team.readOnly', 'Read-only') }}</span>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useAuthStore } from '@/stores/auth'
import type { WorkspaceUser } from '@/types/team'

const props = defineProps<{
  user: WorkspaceUser
}>()

defineEmits<{
  'edit-user': [user: WorkspaceUser]
  'disable-user': [userId: number]
  'enable-user': [userId: number]
}>()

const authStore = useAuthStore()

// Only users with workspace.write can manage users (add, edit, disable)
const canManageUsers = computed(() => authStore.hasPermission('workspace.write'))

const userDisplayName = computed(() => {
  return `${props.user.first_name} ${props.user.last_name}`.trim() || props.user.username
})

const userInitials = computed(() => {
  const firstName = props.user.first_name?.charAt(0) || ''
  const lastName = props.user.last_name?.charAt(0) || ''
  return (firstName + lastName).toUpperCase() || props.user.username.charAt(0).toUpperCase()
})

const displayPermissions = computed(() => {
  return props.user.permissions?.slice(0, 3) || []
})

const formatPermission = (permission: string) => {
  return permission.replace(/\./g, ' ').replace(/\b\w/g, (l) => l.toUpperCase())
}

const formatDate = (dateString: string) => {
  return new Date(dateString).toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
  })
}
</script>
