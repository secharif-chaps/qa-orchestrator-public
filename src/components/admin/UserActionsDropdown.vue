<template>
  <Dropdown align="right" width="md" :close-on-select="true">
    <template #trigger>
      <Button variant="tertiary" size="sm" icon="fa fa-ellipsis-v" icon-only />
    </template>

    <template #content="{ close }">
      <button
        class="w-full flex items-center gap-3 px-4 py-2 text-sm text-base hover:bg-base-200 transition-colors"
        @click="handleChangeOrganization(close)"
      >
        <i class="fa fa-building text-secondary"></i>
        <span>Change Organization</span>
      </button>

      <button
        class="w-full flex items-center gap-3 px-4 py-2 text-sm text-base hover:bg-base-200 transition-colors"
        @click="handleManagePermissions(close)"
      >
        <i class="fa fa-shield text-secondary"></i>
        <span>Manage Permissions</span>
      </button>

      <div class="my-1 border-t border-primary-stroke"></div>

      <button
        class="w-full flex items-center gap-3 px-4 py-2 text-sm text-warning hover:bg-base-200 transition-colors"
        @click="handleDisableUser(close)"
      >
        <i class="fa fa-user-slash text-warning"></i>
        <span>Disable User</span>
      </button>

      <button
        class="w-full flex items-center gap-3 px-4 py-2 text-sm text-info hover:bg-base-200 transition-colors"
        @click="handleResetPassword(close)"
      >
        <i class="fa fa-key text-info"></i>
        <span>Reset Password</span>
      </button>
    </template>
  </Dropdown>
</template>

<script setup lang="ts">
import Dropdown from '@/components/ui/Dropdown.vue'
import Button from '@/components/ui/Button.vue'

const emit = defineEmits<{
  'change-organization': []
  'manage-permissions': []
  'disable-user': []
  'reset-password': []
}>()

function handleChangeOrganization(close: () => void) {
  emit('change-organization')
  close()
}

function handleManagePermissions(close: () => void) {
  emit('manage-permissions')
  close()
}

function handleDisableUser(close: () => void) {
  emit('disable-user')
  close()
}

function handleResetPassword(close: () => void) {
  emit('reset-password')
  close()
}
</script>
