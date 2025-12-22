<template>
  <div class="flex flex-col gap-6">
    <!-- Page Header -->
    <div class="flex items-center gap-4">
      <div>
        <h1 class="text-2xl font-bold text-sage-900 dark:text-white">
          {{ $t('admin.import.title') }}
        </h1>
        <p class="text-sage-600 dark:text-sage-400">
          {{ preselectedOrgName
            ? `${$t('admin.import.description')} - ${preselectedOrgName}`
            : $t('admin.import.description')
          }}
        </p>
      </div>
    </div>

    <!-- Loading Organizations (only on initial load, not refetches) -->
    <div
      v-if="isLoadingOrganizations && !organizationsData"
      class="flex items-center justify-center py-16"
    >
      <i class="fa-solid fa-spinner animate-spin text-2xl text-primary" />
    </div>

    <!-- Error Loading Organizations -->
    <Alert
      v-else-if="organizationsError && !organizationsData"
      variant="danger"
      :title="$t('common.error')"
      :message="String(organizationsError)"
      icon="fa-solid fa-exclamation-circle"
    />

    <!-- Import Wizard (keep mounted once organizations are loaded) -->
    <Card v-else-if="organizationsData" class="p-6">
      <UserImportWizard
        :organization-id="preselectedOrgId"
        :organizations="organizations"
        :back-url="backUrl"
      />
    </Card>
  </div>
</template>

<route lang="yaml">
meta:
  permissions:
    - admin.organizations
  requiresAuth: true
  title: 'Import Users'
</route>

<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import { useQuery } from '@pinia/colada'
import { Alert } from '@owlint/feathers-vue'
import Card from '@/components/ui/Card.vue'
import UserImportWizard from '@/components/import/UserImportWizard.vue'
import { allOrganizationsQuery } from '@/queries/organization-admin'

const route = useRoute()

// Get organization ID from query params (for pre-selection)
const preselectedOrgId = computed(() => {
  const orgId = route.query.organizationId
  return typeof orgId === 'string' ? orgId : undefined
})

// Fetch organizations for the wizard
const {
  data: organizationsData,
  isLoading: isLoadingOrganizations,
  error: organizationsError,
} = useQuery(allOrganizationsQuery, () => ({ page: 1, limit: 100 }))

// Transform organizations for the wizard
const organizations = computed(() => {
  if (!organizationsData.value?.data) return []

  return organizationsData.value.data.map((org) => ({
    id: org.id,
    name: org.name,
  }))
})

// Get preselected organization name for display
const preselectedOrgName = computed(() => {
  if (!preselectedOrgId.value) return null
  const org = organizations.value.find((o) => o.id === preselectedOrgId.value)
  return org?.name || null
})

// Determine back URL based on context
const backUrl = computed(() => {
  if (preselectedOrgId.value) {
    return `/admin/organizations/${preselectedOrgId.value}/members`
  }
  return '/admin/users'
})
</script>
