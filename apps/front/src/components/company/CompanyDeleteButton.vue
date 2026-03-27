<template>
  <div>
    <Button
      v-if="canDeleteCompany"
      variant="tertiary"
      intent="danger"
      icon="fa fa-trash"
      :label="t('screen.company.delete.button')"
      @click="showModal = true"
    />

    <CompanyArchiveModal
      v-model="showModal"
      :company-to-archive="company"
      @archive-company="handleArchive"
    />
  </div>
</template>

<script lang="ts" setup>
import { Button } from '@owlint/feathers-vue'
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { useCompanyPermissions } from '@/composables/useCompanyPermissions'
import CompanyArchiveModal from '@/components/companies/CompanyArchiveModal.vue'
import type { Company } from '@/types/company'

defineProps<{
  company: Company | null | undefined
}>()

const emit = defineEmits<{
  archived: []
}>()

const { t } = useI18n()
const router = useRouter()
const { canDeleteCompany } = useCompanyPermissions()

const showModal = ref(false)

const handleArchive = async () => {
  emit('archived')
  // Redirect to home page after deletion
  router.push('/')
}
</script>
