<template>
  <CompanyCard :title="title" icon="fa-building">
    <template #actions>
      <Button
        v-if="canDeleteCompany && company"
        variant="tertiary"
        color="danger"
        icon="fa fa-trash"
        :label="$t('company.delete.button', 'Delete')"
        @click="confirmDelete"
      />
    </template>

    <div class="flex flex-col gap-4">
      <TasksFlow v-if="displayTasks" />
      <RouterView />
    </div>
    <!-- Floating AI Chat -->
    <FloatingChat />
  </CompanyCard>

  <!-- Archive company Modal -->
  <CompanyArchiveModal
    v-model="showDeleteModal"
    :company-to-archive="company"
    @archive-company="handleArchiveCompany"
  />
</template>

<script lang="ts" setup>
import CompanyCard from '@/components/company/CompanyCard.vue'
import TasksFlow from '@/components/company/tasks/TasksFlow.vue'
import FloatingChat from '@/components/company/FloatingChat.vue'
import CompanyArchiveModal from '@/components/companies/CompanyArchiveModal.vue'
import Button from '@/components/ui/Button.vue'
import { useCompanyPermissions } from '@/composables/useCompanyPermissions'
import { companyByIdQuery } from '@/queries/companies'
import { useQuery } from '@pinia/colada'
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'

const route = useRoute()
const router = useRouter()
const { t } = useI18n()

const companyId = computed(() => (route.params as { companyId: string }).companyId)
const folderId = computed(() => (route.params as { folderId: string }).folderId)

// Get company data
const { data: company, error, status } = useQuery(
  companyByIdQuery,
  () => ({ id: companyId.value }),
  {
    enabled: () => !!companyId.value && companyId.value !== 'null' && companyId.value !== 'undefined',
  }
)

// Permissions
const { canDeleteCompany } = useCompanyPermissions()

// Modal state
const showDeleteModal = ref(false)

// Handle 404 errors - redirect to companies list if company doesn't exist
watch([error, status], ([newError, newStatus]) => {
  // Check for 404 error in multiple possible formats
  if ((newError && (newError.status === 404 || newError.response?.status === 404)) ||
      (newStatus === 'error' && newError && newError.message?.includes('404'))) {
    // Company not found, redirect to companies list
    router.push(`/folders/${folderId.value}`)
  }
})

const displayTasks = computed(() => {
  return route.name === '/folders/[folderId]/companies/[companyId]/'
})

const confirmDelete = () => {
  showDeleteModal.value = true
}

const handleArchiveCompany = async () => {
  // Redirect to companies list after deletion
  router.push(`/folders/${folderId.value}/companies/`)
}

const title = computed(() => {
  switch (route.name) {
    case '/folders/[folderId]/companies/[companyId]/':
      return t('dashboard.title')
    case '/folders/[folderId]/companies/[companyId]/profile':
      return t('profile.title')
    case '/folders/[folderId]/companies/[companyId]/products':
      return t('products.title')
    case '/folders/[folderId]/companies/[companyId]/jobs':
      return t('jobs.title')
    case '/folders/[folderId]/companies/[companyId]/timeline':
      return t('timeline.title')
    case '/folders/[folderId]/companies/[companyId]/team':
      return t('team.title')
    case '/companies/[companyId]/press':
      return 'Press & Media Coverage'
    default:
      return ''
  }
})
</script>
