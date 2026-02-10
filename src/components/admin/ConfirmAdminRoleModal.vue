<template>
  <Modal
    v-model:display-modal="showModal"
    :title="$t('organization.admin.confirmAdminRole.title', 'Assign Admin Role?')"
    icon="fa fa-exclamation-triangle"
    size="md"
    color=""
    @close="showModal = false"
  >
    <template #description>
      {{ $t('organization.admin.confirmAdminRole.warningTitle', 'High privilege role') }}
    </template>

    <div class="flex flex-col gap-4">
      <!-- Warning Alert -->
      <Alert
        variant="warning"
        icon="fa-shield"
        :title="$t('organization.admin.confirmAdminRole.warningTitle', 'High privilege role')"
        :description="$t('organization.admin.confirmAdminRole.warningDescription', 'This role grants full administrative access to the organization.')"
      />

      <!-- Description -->
      <div class="text-sm text-secondary flex flex-col gap-3">
        <p>
          {{ $t('organization.admin.confirmAdminRole.description', 'The Admin role includes:') }}
        </p>
        <ul class="list-disc ml-5 space-y-1">
          <li>{{ $t('organization.admin.confirmAdminRole.permissions.adminOrganizations', 'Admin access to organization management') }}</li>
        </ul>
      </div>
    </div>

    <template #footer>
      <Button
        variant="primary"
        :label="$t('common.confirm', 'Confirm')"
        @click="handleConfirm"
      />
      <Button
        variant="tertiary"
        :label="$t('common.cancel', 'Cancel')"
        @click="showModal = false"
      />
    </template>
  </Modal>
</template>

<script setup lang="ts">
import { Alert, Button, Modal } from '@owlint/feathers-vue'

const showModal = defineModel<boolean>({
  required: true,
  default: false,
})

const emit = defineEmits<{
  confirm: []
}>()

const handleConfirm = () => {
  emit('confirm')
  showModal.value = false
}
</script>
