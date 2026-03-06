<template>
  <Dropdown align="right" width="lg" :close-on-select="true">
    <template #trigger>
      <Button variant="tertiary" size="sm" icon="fa fa-ellipsis-v" />
    </template>

    <template #content="{ close }">
      <button
        class="hover:bg-base-200 flex w-full items-center gap-3 px-4 py-2 text-base text-sm transition-colors"
        @click="handleChangeOrganization(close)"
      >
        <Icon icon="fa-building" class="text-secondary" />
        <span class="flex-1 text-left">{{
          t('admin.userActions.changeOrganization', 'Change Organization')
        }}</span>
      </button>

      <button
        class="hover:bg-base-200 flex w-full items-center gap-3 px-4 py-2 text-base text-sm transition-colors"
        @click="handleManagePermissions(close)"
      >
        <Icon icon="fa-shield" class="text-secondary" />
        <span class="flex-1 text-left">{{
          t('admin.userActions.managePermissions', 'Manage Permissions')
        }}</span>
      </button>

      <div class="border-primary-stroke my-1 border-t"></div>

      <!-- Show Enable User if user is revoked, otherwise show Disable User -->
      <button
        v-if="isRevoked"
        class="text-success hover:bg-base-200 flex w-full items-center gap-3 px-4 py-2 text-sm transition-colors"
        @click="handleEnableUser(close)"
      >
        <Icon icon="fa-user-check" class="text-success" />
        <span class="flex-1 text-left">{{ t('admin.userActions.enableUser', 'Enable User') }}</span>
      </button>

      <button
        v-else
        class="text-warning hover:bg-base-200 flex w-full items-center gap-3 px-4 py-2 text-sm transition-colors"
        @click="handleDisableUser(close)"
      >
        <Icon icon="fa-user-slash" class="text-warning" />
        <span class="flex-1 text-left">{{
          t('admin.userActions.disableUser', 'Disable User')
        }}</span>
      </button>

      <button
        class="text-info hover:bg-base-200 flex w-full items-center gap-3 px-4 py-2 text-sm transition-colors"
        @click="handleResetPassword(close)"
      >
        <Icon icon="fa-key" class="text-info" />
        <span class="flex-1 text-left">{{
          t('admin.userActions.resetPassword', 'Reset Password')
        }}</span>
      </button>
    </template>
  </Dropdown>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import Dropdown from '@/components/ui/Dropdown.vue'
import { Button, Icon } from '@owlint/feathers-vue'

const { t } = useI18n()

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
