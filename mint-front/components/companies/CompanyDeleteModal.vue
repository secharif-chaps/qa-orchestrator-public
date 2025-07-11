<template>
    <!-- Delete Confirmation Modal -->
    <OModal 
      v-model="showDeleteModal"
      :display-modal="showDeleteModal"
      :title="$t('company.list.delete.title')"
      size="md"
      icon="fas fa-trash"
      color="red"
    >

    <template #description>
        <p class="text-secondary">
          {{ $t('company.list.delete.confirm') }} <strong class="font-medium text-primary capitalize">{{ companyToDelete.name }}</strong>?
        </p>
        <p class="text-secondary">
          {{ $t('company.list.delete.warning') }} 
        </p>
        </template>

          <template #footer>
        <div class="flex justify-end gap-3">
          <OButton 
            type="secondary" 
            :label="$t('company.list.delete.actions.cancel')"
            @click="showDeleteModal = false"
          />
          <OButton 
            type="primary"
            color="red" 
            :label="$t('company.list.delete.actions.delete')"
            :loading="deleteLoading" 
            @click="deleteCompany"
          />

        </div>
        </template>
    </OModal>
</template>

<script setup lang="ts">
import { OButton, OModal } from '@owlint/feathers-vue'
import type { Company } from '~/types/company';

const { companyToDelete } = defineProps<{
  companyToDelete: Company | null
}>()

const showDeleteModal = defineModel<boolean>({
  required: true,
  default: false
})

const emit = defineEmits<{
  (e: 'delete-company'): void
}>()

const companyStore = useCompanyStore()
const deleteLoading = ref(false)

const deleteCompany = async () => {

  deleteLoading.value = true

  try {
    if (companyToDelete?.id) {
      await companyStore.deleteCompany(companyToDelete.id)
    }
    showDeleteModal.value = false
    emit('delete-company')
  } catch (err) {
    console.error('Failed to delete company:', err)
  } finally {
    deleteLoading.value = false
  }
}
</script>
