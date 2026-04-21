<template>
  <div class="flex flex-col gap-6">
    <!-- Basic Info Card -->
    <Card>
      <h2 class="mb-4 text-xl font-semibold">
        {{ $t('admin.organization.detail.basicInfo') }}
      </h2>
      <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
        <div>
          <label class="text-neutral-black-font mb-1 block text-sm font-medium">{{
            $t('admin.organization.name')
          }}</label>
          <p class="text-base font-medium">{{ organization?.name }}</p>
        </div>
        <div>
          <label class="text-neutral-black-font mb-1 block text-sm font-medium">{{
            $t('admin.organization.id')
          }}</label>
          <code class="bg-primary-lighter rounded px-2 py-1 text-sm">{{ organization?.id }}</code>
        </div>
        <div class="md:col-span-2" v-if="organization?.description">
          <label class="text-neutral-black-font mb-1 block text-sm font-medium">{{
            $t('admin.organization.description')
          }}</label>
          <p class="text-base">{{ organization?.description }}</p>
        </div>
        <div v-if="organization?.created_at">
          <label class="text-neutral-black-font mb-1 block text-sm font-medium">{{
            $t('admin.organization.created')
          }}</label>
          <p class="text-base">{{ formatDateTime(organization.created_at) }}</p>
        </div>
        <div v-if="organization?.updated_at">
          <label class="text-neutral-black-font mb-1 block text-sm font-medium">{{
            $t('admin.organization.updated')
          }}</label>
          <p class="text-base">{{ formatDateTime(organization.updated_at) }}</p>
        </div>
      </div>
    </Card>

    <!-- Module Status Section -->
    <Card>
      <h2 class="mb-4 text-xl font-semibold">
        {{ $t('settings.tokens.moduleStatus') }}
      </h2>

      <!-- Loading State -->
      <div v-if="isLoadingModules" class="p-4 text-center">
        <div class="border-primary mx-auto h-6 w-6 animate-spin rounded-full border-b-2"></div>
      </div>

      <!-- Module Cards -->
      <div v-else class="grid grid-cols-1 gap-4 md:grid-cols-3">
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

    <!-- Global Features Section -->
    <Card>
      <h2 class="mb-4 text-xl font-semibold">
        {{ $t('settings.featureFlags.globalFeatures') }}
      </h2>
      <p class="text-neutral-black-font mb-4 text-sm">
        {{ $t('settings.featureFlags.description') }}
      </p>

      <!-- Loading State -->
      <div v-if="isLoadingFeatureFlags" class="p-4 text-center">
        <div class="border-primary mx-auto h-6 w-6 animate-spin rounded-full border-b-2"></div>
      </div>

      <!-- Feature Flag Cards - Full width layout for URL input space -->
      <div v-else class="grid grid-cols-1 gap-4">
        <FeatureFlagCard
          v-for="featureFlag in featureFlags"
          :key="featureFlag.flag"
          :flag="featureFlag.flag"
          :is-enabled="featureFlag.enabled"
          :organization-id="organizationIdValue"
          :config="featureFlag.config"
        />
      </div>
    </Card>
  </div>
</template>

<script setup lang="ts">
import FeatureFlagCard from '@/components/tokens/FeatureFlagCard.vue'
import ModuleStatusCard from '@/components/tokens/ModuleStatusCard.vue'
import Card from '@/components/ui/Card.vue'
import { organizationFeatureFlagsQuery } from '@/queries/feature-flags'
import { organizationModulesQuery } from '@/queries/tokens'
import type { FeatureFlagName } from '@/types/feature-flags'
import type { OrganizationAdminResponse } from '@/types/organization'
import { formatDateTime } from '@/utils/time'
import { useQuery } from '@pinia/colada'
import { computed, inject } from 'vue'

// Inject organization data from parent layout
const organization =
  inject<ReturnType<typeof computed<OrganizationAdminResponse | null>>>('organization')
const organizationId = inject<ReturnType<typeof computed<string>>>('organizationId')

// Get the organization ID value for child components
const organizationIdValue = computed(() => organizationId?.value || '')

// Query for module configurations
const {
  data: modulesData,
  isLoading: isLoadingModules,
  refetch: refetchModules,
} = useQuery(() => organizationModulesQuery({ organizationId: organizationId?.value || '' }))

const modules = computed(() =>
  (modulesData.value?.modules ?? []).filter((m) => m.name !== 'stream'),
)

// Query for feature flags
const { data: featureFlagsData, isLoading: isLoadingFeatureFlags } = useQuery({
  ...organizationFeatureFlagsQuery({ organizationId: organizationId?.value || '' }),
  enabled: () => !!organizationId?.value,
})

const featureFlags = computed(() => {
  const flags = featureFlagsData.value?.feature_flags ?? []
  return flags.map((f) => ({
    flag: f.flag as FeatureFlagName,
    enabled: f.enabled,
    config: f.config,
  }))
})
</script>
