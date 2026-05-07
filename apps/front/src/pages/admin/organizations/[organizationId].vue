<template>
  <div class="flex flex-col gap-6">
    <!-- Loading State -->
    <OrganizationDetailSkeleton v-if="isLoading" />

    <!-- Error State -->
    <Alert
      v-else-if="error"
      variant="danger"
      :title="$t('common.error')"
      :description="errorMessage"
    />

    <!-- Organization Details -->
    <template v-else-if="organization">
      <!-- Header with Toggle Navigation -->
      <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 class="text-2xl font-bold">{{ organization.name }}</h1>
          <p class="text-neutral-black-font mt-1">
            {{ $t('admin.organization.detail.description') }}
          </p>
        </div>

        <!-- Navigation Toggle -->
        <div class="flex items-center">
          <Toggle v-model="currentSection" :options="sectionOptions" variant="pill" />
        </div>
      </div>

      <!-- Subpage Content -->
      <RouterView />
    </template>
  </div>
</template>

<route lang="yaml">
meta:
  permissions:
    - admin.organizations
</route>

<script setup lang="ts">
import OrganizationDetailSkeleton from '@/components/admin/OrganizationDetailSkeleton.vue'
import { Alert, Toggle } from '@owlint/feathers-vue'
import { useQuery } from '@pinia/colada'
import { computed, provide } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterView, useRoute, useRouter } from 'vue-router'

// Queries
import { organizationByIdQuery } from '@/queries/organization-admin'

const route = useRoute('/admin/organizations/[organizationId]')
const router = useRouter()
const { t } = useI18n()

const organizationId = computed(() => route.params.organizationId)

// Query for organization details
const {
  data: organization,
  isLoading,
  error,
} = useQuery({
  ...organizationByIdQuery({ id: organizationId.value }),
  enabled: () =>
    !!organizationId.value &&
    organizationId.value !== 'null' &&
    organizationId.value !== 'undefined',
})

// Extract error message safely
const errorMessage = computed(() => {
  const err = error.value
  if (!err) return ''
  if (typeof err === 'string') return err
  if (err instanceof Error) return err.message
  if (typeof err === 'object' && 'message' in err)
    return String((err as { message: unknown }).message)
  return 'An error occurred'
})

// Provide organization data to child components
provide('organization', organization)
provide('organizationId', organizationId)

// Section definitions for toggle
const sectionOptions = computed(() => [
  {
    value: 'profile',
    icon: 'fas fa-building',
    label: t('admin.organization.tabs.profile'),
  },
  {
    value: 'tokens',
    icon: 'fas fa-coins',
    label: t('admin.organization.tabs.tokens'),
  },
  {
    value: 'members',
    icon: 'fas fa-users',
    label: t('admin.organization.tabs.members'),
  },
  {
    value: 'sources',
    icon: 'fas fa-plug',
    label: t('admin.organization.tabs.sources'),
  },
])

// Current section based on route
const currentSection = computed({
  get: () => {
    const path = route.path
    if (path.endsWith('/tokens')) return 'tokens'
    if (path.endsWith('/members')) return 'members'
    if (path.endsWith('/sources')) return 'sources'
    return 'profile'
  },
  set: (value: string) => {
    const validSections = ['profile', 'tokens', 'members', 'sources']
    if (!value || !validSections.includes(value) || !organizationId.value) return
    router.push(`/admin/organizations/${organizationId.value}/${value}`)
  },
})
</script>
