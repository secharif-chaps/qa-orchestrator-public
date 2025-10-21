<template>
  <Card>
    <div class="flex items-center justify-between mb-6">
      <div>
        <h2 class="text-xl font-semibold">
          {{ $t('tokens.moduleManagement', 'Module & Token Management') }}
        </h2>
        <p class="text-secondary mt-1">
          {{ $t('tokens.moduleDescription', 'Configure module access and token allocations') }}
        </p>
      </div>

      <div class="flex items-center gap-2">
        <button
          @click="refreshAllTokens"
          :disabled="isRefreshing"
          class="text-secondary hover:text-base transition-colors p-2"
          :title="$t('tokens.refreshAll', 'Refresh all token counts')"
        >
          <i :class="{ 'animate-spin': isRefreshing }" class="fa fa-refresh"></i>
        </button>

        <button
          v-if="hasChanges"
          @click="saveAllChanges"
          :disabled="isSaving"
          class="bg-primary text-white px-4 py-2 rounded-lg hover:bg-primary/80 transition-colors text-sm disabled:opacity-50 flex items-center gap-2"
        >
          <div
            v-if="isSaving"
            class="animate-spin rounded-full h-4 w-4 border-b-2 border-white"
          ></div>
          <i v-else class="fa fa-save"></i>
          {{ $t('tokens.saveChanges', 'Save Changes') }}
        </button>
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="isLoading" class="text-center p-8">
      <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary mx-auto mb-4"></div>
      <p class="text-secondary">
        {{ $t('tokens.loading', 'Loading token configuration...') }}
      </p>
    </div>

    <!-- Error State -->
    <div
      v-else-if="error"
      class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-6"
    >
      <div class="flex items-center gap-2">
        <i class="fa fa-exclamation-triangle"></i>
        <span class="font-medium">Error:</span>
        <span>{{ error.message }}</span>
      </div>
    </div>

    <!-- Usage Statistics -->
    <div v-if="modules && modules.modules.length > 0" class="mb-8">
      <h3 class="text-lg font-medium mb-4">
        {{ $t('tokens.statistics', 'Token Statistics') }}
      </h3>

      <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="bg-base-200 rounded-lg p-4">
          <div class="text-sm text-secondary mb-1">
            {{ $t('tokens.totalTokens', 'Total Tokens') }}
          </div>
          <div class="text-2xl font-bold">{{ totalTokens }}</div>
        </div>

        <div class="bg-base-200 rounded-lg p-4">
          <div class="text-sm text-secondary mb-1">
            {{ $t('tokens.enabledModules', 'Enabled Modules') }}
          </div>
          <div class="text-2xl font-bold text-green-600">{{ enabledModulesCount }}</div>
        </div>

        <div class="bg-base-200 rounded-lg p-4">
          <div class="text-sm text-secondary mb-1">
            {{ $t('tokens.disabledModules', 'Disabled Modules') }}
          </div>
          <div class="text-2xl font-bold text-red-600">{{ disabledModulesCount }}</div>
        </div>

        <div class="bg-base-200 rounded-lg p-4">
          <div class="text-sm text-secondary mb-1">
            {{ $t('tokens.lowTokenModules', 'Low Token Modules') }}
          </div>
          <div class="text-2xl font-bold text-yellow-600">{{ lowTokenModulesCount }}</div>
        </div>
      </div>
    </div>

    <!-- Module Cards -->
    <div v-if="modules" class="space-y-4 lg:space-y-0 lg:grid lg:grid-cols-2 lg:gap-4">
      <ModuleTokenCard
        v-for="module in modules.modules"
        :key="module.name"
        :module="module.name"
        :token-count="module.token_count"
        :is-enabled="module.enabled"
        :workspace-id="workspaceId"
        :show-admin-controls="true"
        @refresh="refreshTokens"
      />
    </div>

    <!-- Empty State -->
    <div v-else class="text-center p-8">
      <i class="fa fa-cogs text-4xl text-secondary/50 mb-4"></i>
      <h3 class="text-lg font-medium text-base mb-2">
        {{ $t('tokens.empty.title', 'No modules configured') }}
      </h3>
      <p class="text-secondary mb-6">
        {{
          $t(
            'tokens.empty.description',
            'Module configuration will be displayed here once available',
          )
        }}
      </p>
      <button
        @click="refreshTokens"
        class="bg-primary text-white px-6 py-2 rounded-lg hover:bg-primary/80 transition-colors"
      >
        {{ $t('tokens.refresh', 'Refresh') }}
      </button>
    </div>
  </Card>
</template>

<script setup lang="ts">
import { useUpdateWorkspaceModules } from '@/mutations/tokens'
import { workspaceModulesQuery } from '@/queries/tokens'
import { useQuery } from '@pinia/colada'
import { computed, ref } from 'vue'
import Card from '../ui/Card.vue'
import ModuleTokenCard from './ModuleTokenCard.vue'

interface Props {
  workspaceId: number
}

const props = defineProps<Props>()

// State
const isRefreshing = ref(false)
const hasChanges = ref(false)

// Query for workspace modules
const {
  data: modules,
  isLoading,
  error,
  refetch,
} = useQuery(workspaceModulesQuery, () => ({ workspaceId: props.workspaceId }), {
  enabled: computed(() => !!props.workspaceId),
})

// Mutation for bulk updates
const { updateModules, isLoading: isSaving } = useUpdateWorkspaceModules()

// Computed statistics
const totalTokens = computed(() => {
  if (!modules.value?.modules) return 0
  return modules.value.modules.reduce((sum, module) => sum + module.token_count, 0)
})

const enabledModulesCount = computed(() => {
  if (!modules.value?.modules) return 0
  return modules.value.modules.filter((module) => module.enabled).length
})

const disabledModulesCount = computed(() => {
  if (!modules.value?.modules) return 0
  return modules.value.modules.filter((module) => !module.enabled).length
})

const lowTokenModulesCount = computed(() => {
  if (!modules.value?.modules) return 0
  return modules.value.modules.filter((module) => module.enabled && module.token_count < 10).length
})

// Methods
const refreshTokens = async () => {
  await refetch()
}

const refreshAllTokens = async () => {
  isRefreshing.value = true
  try {
    await refreshTokens()
    // Add a small delay for visual feedback
    await new Promise((resolve) => setTimeout(resolve, 500))
  } finally {
    isRefreshing.value = false
  }
}

const saveAllChanges = async () => {
  // This would be used if we implement bulk editing functionality
  // For now, individual changes are saved immediately
  hasChanges.value = false
}
</script>
