<template>
  <div class="px-6 py-4 hover:bg-base-200/50 transition-colors">
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
          <Tag
            v-for="permission in displayPermissions"
            :key="permission"
            :variant="user.is_disabled ? 'slate' : 'primary'"
            :label="formatPermission(permission)"
            size="xs"
            rounded
            :gradient="false"
          />
          <Tag
            v-if="user.permissions && user.permissions.length > 3"
            variant="slate"
            :label="`+${user.permissions.length - 3}`"
            size="xs"
            rounded
            :gradient="false"
          />
        </div>
      </div>

      <div class="col-span-2">
        <div class="text-sm" :class="user.is_disabled ? 'text-gray-400' : 'text-secondary'">
          {{ formatDate(user.created_at) }}
        </div>
      </div>

      <div class="col-span-2">
        <div class="flex items-center gap-2">
          <Tag
            :variant="user.is_disabled ? 'error' : 'success'"
            :icon="user.is_disabled ? 'fa fa-ban' : 'fa fa-check-circle'"
            :label="
              user.is_disabled
                ? $t('team.status.disabled', 'Disabled')
                : $t('team.status.active', 'Active')
            "
            size="xs"
            rounded
          />
        </div>
      </div>

      <div class="col-span-1">
        <div v-if="canManageUsers" class="flex items-center gap-2">
          <Button
            variant="tertiary"
            icon="fa fa-edit"
            size="sm"
            :title="$t('team.edit', 'Edit user')"
            @click="$emit('edit-user', user)"
          />

          <Button
            v-if="user.is_disabled"
            variant="tertiary"
            icon="fa fa-check"
            size="sm"
            :title="$t('team.enable', 'Enable user')"
            @click="$emit('enable-user', user.id)"
          />

          <Button
            v-else
            variant="tertiary"
            icon="fa fa-ban"
            size="sm"
            :title="$t('team.disable', 'Disable user')"
            @click="$emit('disable-user', user.id)"
          />
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
import type { OrganizationUser } from '@/types/team'

import { Button } from '@owlint/feathers-vue'

const props = defineProps<{
  user: OrganizationUser
}>()

defineEmits<{
  'edit-user': [user: OrganizationUser]
  'disable-user': [userId: number]
  'enable-user': [userId: number]
}>()

const authStore = useAuthStore()

// Only users with organization.write can manage users (add, edit, disable)
const canManageUsers = computed(() => authStore.hasPermission('organization.write'))

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
