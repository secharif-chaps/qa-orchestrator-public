<template>
  <Modal
    v-if="companyToDelete"
    v-model:display-modal="showDeleteModal"
    :title="$t('screen.company.delete.title')"
    icon="fa-exclamation-triangle"
    size="md"
    color=""
  >
    <template #description>
      <div class="flex flex-col gap-6">
        <p class="text-neutral-black-font">
          {{ $t('screen.company.delete.subtitle') }}
        </p>

        <!-- Warning Message -->
        <div class="rounded-sm border border-red-200 bg-red-50 p-4">
          <div class="flex items-start gap-3">
            <i class="fa fa-exclamation-triangle mt-0.5 text-red-500"></i>
            <div class="text-sm leading-relaxed text-red-700">
              <p class="mb-2 font-medium">
                {{ $t('screen.company.delete.warning.title') }}
              </p>
              <p>
                {{ $t('screen.company.delete.warning.message') }}
              </p>
            </div>
          </div>
        </div>

        <!-- Company Details -->
        <div class="bg-primary-lightest rounded-sm p-4">
          <h4 class="mb-3 text-base font-medium">
            {{ $t('screen.company.delete.details') }}
          </h4>
          <div class="flex flex-col gap-2 text-sm">
            <div class="flex justify-between">
              <span class="text-neutral-black-font">{{
                $t('screen.company.detailsLabels.name')
              }}</span>
              <span class="font-medium">{{ companyToDelete.name }}</span>
            </div>
            <div v-if="companyToDelete.website" class="flex justify-between">
              <span class="text-neutral-black-font">{{
                $t('screen.company.detailsLabels.website')
              }}</span>
              <span class="text-xs">{{ companyToDelete.website }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-neutral-black-font">{{
                $t('screen.company.detailsLabels.created')
              }}</span>
              <span>{{ formatDate(companyToDelete.created_at, 'eventDate') }}</span>
            </div>
            <div
              v-if="companyToDelete.tasks && companyToDelete.tasks.length > 0"
              class="flex justify-between"
            >
              <span class="text-neutral-black-font">{{
                $t('screen.company.detailsLabels.tasks')
              }}</span>
              <span class="inline-flex items-center gap-1">
                <i class="fa fa-tasks text-neutral-black-font text-xs"></i>
                {{ companyToDelete.tasks.length }} {{ $t('screen.company.tasks.count') }}
              </span>
            </div>
          </div>
        </div>

        <!-- Confirmation Input -->
        <div class="flex flex-col gap-3">
          <p class="text-neutral-black-font text-sm">
            {{ $t('screen.company.delete.confirm.message') }}
          </p>
          <div class="flex flex-col gap-2">
            <code class="bg-primary-lighter block rounded px-2 py-1 text-sm">{{
              companyToDelete.name
            }}</code>
            <Input
              id="delete-confirmation"
              v-model="confirmationText"
              :placeholder="$t('screen.company.delete.confirm.placeholder')"
              class="bg-primary-lighter"
            />
          </div>
        </div>
      </div>
    </template>

    <template #footer>
      <Button
        variant="accent"
        icon="fa-trash"
        :label="$t('screen.company.delete.confirm.button')"
        :loading="isLoading"
        :disabled="!isConfirmed || isLoading"
        @click="handleDelete"
      />
      <Button variant="tertiary" :label="$t('common.cancel')" @click="handleClose" />
    </template>
  </Modal>
</template>

<script setup lang="ts">
import { useDateTime } from '@/composables/useDateTime'
import { useDeleteCompany } from '@/mutations/companies'
import type { Company } from '@/types/company'
import { Button, Input, Modal } from '@owlint/feathers-vue'
import { computed, ref, watch } from 'vue'

const { formatDate } = useDateTime()

interface Props {
  companyToDelete: Company | null
}

const props = defineProps<Props>()

const showDeleteModal = defineModel<boolean>({
  required: true,
  default: false,
})

const emit = defineEmits<{
  'delete-company': []
}>()

const confirmationText = ref('')

const isConfirmed = computed(() => {
  return confirmationText.value.trim() === props.companyToDelete?.name.trim()
})

// Reset confirmation field whenever the modal closes so the next opening starts clean.
watch(showDeleteModal, (open) => {
  if (!open) confirmationText.value = ''
})

const { deleteCompany, isLoading } = useDeleteCompany()

const handleClose = () => {
  showDeleteModal.value = false
}

const handleDelete = async () => {
  if (!props.companyToDelete?.id || !isConfirmed.value) return

  try {
    await deleteCompany({
      companyId: props.companyToDelete.id.toString(),
      companyName: props.companyToDelete.name,
    })

    emit('delete-company')
    handleClose()
  } catch (error) {
    console.error('Error deleting company:', error)
  }
}
</script>
