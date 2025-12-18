<template>
  <div class="flex flex-col gap-6">
    <!-- Basic Info Card -->
    <Card>
      <h2 class="text-xl font-semibold mb-4">
        {{ $t('organization.detail.basicInfo', 'Basic Information') }}
      </h2>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div>
          <label class="block text-sm font-medium text-secondary mb-1">{{
            $t('organization.name', 'Name')
          }}</label>
          <p class="text-base font-medium">{{ organization?.name }}</p>
        </div>
        <div>
          <label class="block text-sm font-medium text-secondary mb-1">{{
            $t('organization.id', 'ID')
          }}</label>
          <code class="text-sm bg-base-300 px-2 py-1 rounded">{{ organization?.id }}</code>
        </div>
        <div class="md:col-span-2" v-if="organization?.description">
          <label class="block text-sm font-medium text-secondary mb-1">{{
            $t('organization.description', 'Description')
          }}</label>
          <p class="text-base">{{ organization?.description }}</p>
        </div>
        <div v-if="organization?.created_at">
          <label class="block text-sm font-medium text-secondary mb-1">{{
            $t('organization.created', 'Created')
          }}</label>
          <p class="text-base">{{ formatDate(organization.created_at) }}</p>
        </div>
        <div v-if="organization?.updated_at">
          <label class="block text-sm font-medium text-secondary mb-1">{{
            $t('organization.updated', 'Last Updated')
          }}</label>
          <p class="text-base">{{ formatDate(organization.updated_at) }}</p>
        </div>
      </div>
    </Card>

    <!-- Module Status Section -->
    <Card>
      <h2 class="text-xl font-semibold mb-4">
        {{ $t('tokens.moduleStatus', 'Module Status') }}
      </h2>

      <!-- Loading State -->
      <div v-if="isLoadingModules" class="text-center p-4">
        <div class="animate-spin rounded-full h-6 w-6 border-b-2 border-primary mx-auto"></div>
      </div>

      <!-- Module Cards -->
      <div v-else class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <ModuleStatusCard
          v-for="module in modules"
          :key="module.name"
          :module="module.name"
          :is-enabled="module.enabled"
          :organization-id="organizationIdValue"
          @refresh="refetchModules"
        />
      </div>
    </Card>
  </div>
</template>

<script setup lang="ts">
import { computed, inject } from 'vue'
import { useQuery } from '@pinia/colada'
import Card from '@/components/ui/Card.vue'
import ModuleStatusCard from '@/components/tokens/ModuleStatusCard.vue'
import { organizationModulesQuery } from '@/queries/tokens'
import type { OrganizationAdminResponse } from '@/types/organization'

// Inject organization data from parent layout
const organization = inject<ReturnType<typeof computed<OrganizationAdminResponse | null>>>('organization')
const organizationId = inject<ReturnType<typeof computed<string>>>('organizationId')

// Get the organization ID value for child components
const organizationIdValue = computed(() => organizationId?.value || '')

// Query for module configurations
const {
  data: modulesData,
  isLoading: isLoadingModules,
  refetch: refetchModules,
} = useQuery({
  ...organizationModulesQuery({ organizationId: organizationId?.value || '' }),
  enabled: () => !!organizationId?.value,
})

const modules = computed(() => modulesData.value?.modules ?? [])

// Format date helper
function formatDate(dateString: string) {
  return new Date(dateString).toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'long',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}
</script>
