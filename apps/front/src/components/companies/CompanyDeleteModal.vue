<template>
  <!-- Delete Confirmation Modal with backdrop blur -->
  <div
    v-if="showDeleteModal && companyToDelete"
    class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm"
  >
    <div class="mx-4 w-full max-w-112 rounded-sm bg-white shadow-xl">
      <!-- Header -->
      <div class="border-primary-lighter-stroke border-b p-6">
        <div class="flex items-center gap-3">
          <div class="flex h-10 w-10 items-center justify-center rounded-full bg-red-100">
            <i class="fa fa-exclamation-triangle text-red-600"></i>
          </div>
          <div>
            <h3 class="text-base text-lg font-semibold">
              {{ $t('screen.company.delete.title') }}
            </h3>
            <p class="text-neutral-black-font text-sm">
              {{ $t('screen.company.delete.subtitle') }}
            </p>
          </div>
        </div>
      </div>

      <!-- Content -->
      <div class="p-6">
        <!-- Warning Message -->
        <div class="mb-6 rounded-sm border border-red-200 bg-red-50 p-4">
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
        <div class="bg-primary-lightest mb-6 rounded-sm p-4">
          <h4 class="mb-3 text-base font-medium">
            {{ $t('screen.company.delete.details') }}
          </h4>
          <div class="space-y-2 text-sm">
            <div class="flex justify-between">
              <span class="text-neutral-black-font">{{ $t('screen.company.name') }}:</span>
              <span class="font-medium">{{ companyToDelete.name }}</span>
            </div>
            <div v-if="companyToDelete.website" class="flex justify-between">
              <span class="text-neutral-black-font">{{ $t('screen.company.website') }}:</span>
              <span class="text-xs">{{ companyToDelete.website }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-neutral-black-font">{{ $t('screen.company.created') }}:</span>
              <span>{{ formatDate(companyToDelete.created_at) }}</span>
            </div>
            <div
              v-if="companyToDelete.tasks && companyToDelete.tasks.length > 0"
              class="flex justify-between"
            >
              <span class="text-neutral-black-font">{{ $t('screen.company.tasks') }}:</span>
              <span class="inline-flex items-center gap-1">
                <i class="fa fa-tasks text-neutral-black-font text-xs"></i>
                {{ companyToDelete.tasks.length }} {{ $t('screen.company.tasks.count') }}
              </span>
            </div>
          </div>
        </div>

        <!-- Confirmation Input -->
        <div class="mb-6">
          <p class="text-neutral-black-font mb-3 text-sm">
            {{ $t('screen.company.delete.confirm.message') }}
          </p>
          <div class="space-y-2">
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

      <!-- Footer -->
      <div class="border-primary-lighter-stroke flex items-center justify-end gap-3 border-t p-6">
        <Button variant="tertiary" :label="$t('common.cancel')" @click="handleClose" />
        <Button
          variant="accent"
          icon="fa fa-trash"
          :label="$t('screen.company.delete.confirm.button')"
          :loading="isLoading"
          :disabled="!isConfirmed || isLoading"
          @click="handleDelete"
        />
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { useDeleteCompany } from '@/mutations/companies'
import type { Company } from '@/types/company'
import { Button, Input } from '@owlint/feathers-vue'
import { computed, ref } from 'vue'

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

// Use mutation for deleting with cache invalidation
const { deleteCompany, isLoading } = useDeleteCompany()

const handleClose = () => {
  showDeleteModal.value = false
  confirmationText.value = ''
}

const handleDelete = async () => {
  if (!props.companyToDelete?.id || !isConfirmed.value) return

  try {
    await deleteCompany({
      companyId: props.companyToDelete.id.toString(),
      companyName: props.companyToDelete.name,
    })

    // Emit event for parent
    emit('delete-company')

    // Close modal and reset
    handleClose()
  } catch (error) {
    // Error toast is shown by the mutation's onError handler
    console.error('Error deleting company:', error)
  }
}

const formatDate = (dateString: string) => {
  return new Date(dateString).toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
  })
}
</script>
