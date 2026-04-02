<template>
  <Modal
    v-model:display-modal="showModal"
    :title="$t('admin.organization.admin.confirmAdminRole.title')"
    icon="fa fa-exclamation-triangle"
    size="md"
    color=""
    @close="showModal = false"
  >
    <template #description>
      {{ $t('admin.organization.admin.confirmAdminRole.warningTitle') }}
    </template>

    <div class="flex flex-col gap-4">
      <!-- Warning Alert -->
      <Alert
        variant="warning"
        icon="fa-shield"
        :title="$t('admin.organization.admin.confirmAdminRole.warningTitle')"
        :description="$t('admin.organization.admin.confirmAdminRole.warningDescription')"
      />

      <!-- Description -->
      <div class="text-secondary flex flex-col gap-3 text-sm">
        <p>
          {{ $t('admin.organization.admin.confirmAdminRole.description') }}
        </p>
        <ul class="ml-5 list-disc space-y-1">
          <li>
            {{ $t('admin.organization.admin.confirmAdminRole.permissions.adminOrganizations') }}
          </li>
        </ul>
      </div>
    </div>

    <template #footer>
      <Button variant="primary" :label="$t('common.confirm')" @click="handleConfirm" />
      <Button variant="tertiary" :label="$t('common.cancel')" @click="showModal = false" />
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
