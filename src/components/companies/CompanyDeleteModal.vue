<template>
  <!-- Delete Confirmation Modal with backdrop blur -->
  <div v-if="showDeleteModal && companyToDelete" class="fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center z-50">
    <div class="bg-bg1 rounded-lg shadow-xl max-w-md w-full mx-4">
      <!-- Header -->
      <div class="p-6 border-b border-border-2">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 bg-red-100 rounded-full flex items-center justify-center">
            <i class="fa fa-exclamation-triangle text-red-600"></i>
          </div>
          <div>
            <h3 class="text-lg font-semibold text-base">
              {{ $t('company.delete.title', 'Delete Company') }}
            </h3>
            <p class="text-sm text-secondary">
              {{ $t('company.delete.subtitle', 'This action cannot be undone') }}
            </p>
          </div>
        </div>
      </div>

      <!-- Content -->
      <div class="p-6">
        <!-- Warning Message -->
        <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg">
          <div class="flex items-start gap-3">
            <i class="fa fa-exclamation-triangle text-red-500 mt-0.5"></i>
            <div class="text-red-700 text-sm leading-relaxed">
              <p class="font-medium mb-2">
                {{ $t('company.delete.warning.title', 'Warning: This will permanently delete the company') }}
              </p>
              <p>
                {{ $t('company.delete.warning.message', 'All associated data including tasks, reports, and history will be permanently removed. This action cannot be undone.') }}
              </p>
            </div>
          </div>
        </div>

        <!-- Company Details -->
        <div class="mb-6 bg-bg2 rounded-lg p-4">
          <h4 class="font-medium text-base mb-3">
            {{ $t('company.delete.details', 'Company Details') }}
          </h4>
          <div class="space-y-2 text-sm">
            <div class="flex justify-between">
              <span class="text-secondary">{{ $t('company.name', 'Name') }}:</span>
              <span class="font-medium">{{ companyToDelete.name }}</span>
            </div>
            <div v-if="companyToDelete.website" class="flex justify-between">
              <span class="text-secondary">{{ $t('company.website', 'Website') }}:</span>
              <span class="text-xs">{{ companyToDelete.website }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-secondary">{{ $t('company.created', 'Created') }}:</span>
              <span>{{ formatDate(companyToDelete.created_at) }}</span>
            </div>
            <div v-if="companyToDelete.tasks && companyToDelete.tasks.length > 0" class="flex justify-between">
              <span class="text-secondary">{{ $t('company.tasks', 'Tasks') }}:</span>
              <span class="inline-flex items-center gap-1">
                <i class="fa fa-tasks text-primary text-xs"></i>
                {{ companyToDelete.tasks.length }} {{ $t('company.tasks.count', 'tasks') }}
              </span>
            </div>
          </div>
        </div>

        <!-- Confirmation Input -->
        <div class="mb-6">
          <p class="text-sm text-secondary mb-3">
            {{ $t('company.delete.confirm.message', 'Type the company name to confirm deletion:') }}
          </p>
          <div class="space-y-2">
            <code class="text-sm bg-bg3 px-2 py-1 rounded block">{{ companyToDelete.name }}</code>
            <Input
              v-model="confirmationText"
              :placeholder="$t('company.delete.confirm.placeholder', 'Enter company name...')"
              class="bg-bg3"
            />
          </div>
        </div>
      </div>

      <!-- Footer -->
      <div class="p-6 border-t border-border-2 flex items-center justify-end gap-3">
        <Button
          variant="ghost-primary"
          :label="$t('common.cancel', 'Cancel')"
          @click="showDeleteModal = false"
        />
        <Button
          variant="primary"
          color="danger"
          icon="fa fa-trash"
          :label="$t('company.delete.confirm.button', 'Delete Company')"
          :loading="deleteLoading"
          :disabled="!isConfirmed || deleteLoading"
          @click="deleteCompany"
        />
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import type { Company } from '@/types/company'
import Button from '@/components/ui/Button.vue'
import Input from '@/components/ui/Input.vue'
import { deleteCompany as apiDeleteCompany } from '@/api/companies'
import { toast } from '@/utils/toast'

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

const deleteLoading = ref(false)
const confirmationText = ref('')

const isConfirmed = computed(() => {
  return confirmationText.value.trim() === props.companyToDelete?.name.trim()
})

const deleteCompany = async () => {
  if (!props.companyToDelete?.id || !isConfirmed.value) return

  deleteLoading.value = true

  try {
    await apiDeleteCompany(props.companyToDelete.id.toString())
    
    // Show success toast
    toast.success(`Company "${props.companyToDelete.name}" has been deleted successfully`)
    
    // Emit event first, then clean up
    emit('delete-company')
    
    // Small delay to ensure parent component processes the event
    setTimeout(() => {
      showDeleteModal.value = false
      confirmationText.value = ''
    }, 50)
  } catch (err) {
    console.error('Failed to delete company:', err)
    // Show error toast
    toast.error(`Failed to delete company "${props.companyToDelete.name}". Please try again.`)
  } finally {
    deleteLoading.value = false
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
