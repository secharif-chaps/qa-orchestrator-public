<template>
  <Dropdown align="right" width="md" :close-on-select="true">
    <template #trigger>
      <Button variant="tertiary" size="sm" icon="fa fa-ellipsis-v" />
    </template>

    <template #content="{ close }">
      <button
        class="w-full flex items-center gap-3 px-4 py-2 text-sm text-base hover:bg-base-200 transition-colors"
        @click="handleChangeOrganization(close)"
      >
        <Icon icon="fa-building" class="text-secondary" />
        <span>Change Organization</span>
      </button>

      <button
        class="w-full flex items-center gap-3 px-4 py-2 text-sm text-base hover:bg-base-200 transition-colors"
        @click="handleManagePermissions(close)"
      >
        <Icon icon="fa-shield" class="text-secondary" />
        <span>Manage Permissions</span>
      </button>

      <div class="my-1 border-t border-primary-stroke"></div>

      <!-- Show Enable User if user is revoked, otherwise show Disable User -->
      <button
        v-if="isRevoked"
        class="w-full flex items-center gap-3 px-4 py-2 text-sm text-success hover:bg-base-200 transition-colors"
        @click="handleEnableUser(close)"
      >
        <Icon icon="fa-user-check" class="text-success" />
        <span>Enable User</span>
      </button>

      <button
        v-else
        class="w-full flex items-center gap-3 px-4 py-2 text-sm text-warning hover:bg-base-200 transition-colors"
        @click="handleDisableUser(close)"
      >
        <Icon icon="fa-user-slash" class="text-warning" />
        <span>Disable User</span>
      </button>

      <button
        class="w-full flex items-center gap-3 px-4 py-2 text-sm text-info hover:bg-base-200 transition-colors"
        @click="handleResetPassword(close)"
      >
        <Icon icon="fa-key" class="text-info" />
        <span>Reset Password</span>
      </button>
    </template>
  </Dropdown>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import Dropdown from '@/components/ui/Dropdown.vue'
import { Button, Icon } from '@owlint/feathers-vue'

const props = defineProps<{
  userStatus: string
}>()

const emit = defineEmits<{
  'change-organization': []
  'manage-permissions': []
  'disable-user': []
  'enable-user': []
  'reset-password': []
}>()

const isRevoked = computed(() => props.userStatus === 'revoked')

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

function handleEnableUser(close: () => void) {
  emit('enable-user')
  close()
}

function handleResetPassword(close: () => void) {
  emit('reset-password')
  close()
}
</script>
