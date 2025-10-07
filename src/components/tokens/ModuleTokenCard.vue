<template>
  <div class="bg-base-100 rounded-lg p-4 border border-primary-stroke">
    <div class="flex items-center justify-between mb-4">
      <div class="flex items-center gap-3">
        <div
          class="w-10 h-10 bg-primary/10 text-primary rounded-full flex items-center justify-center"
        >
          <i :class="moduleIcon" class="text-lg"></i>
        </div>
        <div>
          <h3 class="font-medium capitalize">{{ module }} Module</h3>
          <p class="text-sm text-primary-light-content">{{ moduleDescription }}</p>
        </div>
      </div>

      <!-- Enable/Disable Toggle -->
      <label class="relative inline-flex items-center cursor-pointer">
        <input
          type="checkbox"
          :checked="isEnabled"
          @change="handleToggle"
          :disabled="isToggling"
          class="sr-only peer"
        />
        <div
          class="relative w-11 h-6 bg-base-300 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-primary/20 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-primary-stroke after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-primary"
        ></div>
        <span class="ml-3 text-sm font-medium">
          {{ isEnabled ? $t('tokens.enabled', 'Enabled') : $t('tokens.disabled', 'Disabled') }}
        </span>
      </label>
    </div>

    <!-- Token Count Display -->
    <div class="mb-4">
      <div class="flex items-center gap-2 mb-2">
        <span class="text-sm text-primary-light-content">{{
          $t('tokens.currentCount', 'Current Tokens')
        }}</span>
        <button
          @click="refreshTokens"
          :disabled="isRefreshing"
          class="text-primary-light-content hover:text-base transition-colors p-1"
          :title="$t('tokens.refresh', 'Refresh token count')"
        >
          <i :class="{ 'animate-spin': isRefreshing }" class="fa fa-refresh text-xs"></i>
        </button>
      </div>

      <div class="flex items-center gap-4">
        <div class="text-2xl font-bold" :class="tokenCountColor">
          {{ tokenCount }}
        </div>
        <Badge v-if="isEnabled" :variant="tokenStatusVariant" :label="tokenStatusText" size="xs" />
      </div>
    </div>

    <!-- Admin Controls -->
    <div v-if="showAdminControls" class="space-y-3 pt-3 border-t border-primary-stroke">
      <!-- Quick Add Buttons -->
      <div>
        <label class="block text-xs font-medium text-primary-light-content mb-2">
          {{ $t('tokens.quickAdd', 'Quick Add') }}
        </label>
        <div class="flex items-center gap-2 flex-wrap">
          <button
            v-for="amount in quickAddAmounts"
            :key="amount"
            @click="addQuickTokens(amount)"
            :disabled="!isEnabled || addTokensMutation.isLoading.value"
            class="bg-base-300 hover:bg-primary hover:text-white border border-primary-stroke hover:border-primary text-sm px-3 py-1.5 rounded-lg transition-colors disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-1"
          >
            <i class="fa fa-plus text-xs"></i>
            {{ amount }}
          </button>

          <!-- Loading indicator for quick buttons -->
          <div
            v-if="addTokensMutation.isLoading.value"
            class="flex items-center gap-2 text-xs text-primary-light-content"
          >
            <div class="animate-spin rounded-full h-3 w-3 border-b-2 border-primary"></div>
            {{ $t('tokens.adding', 'Adding...') }}
          </div>
        </div>
      </div>

      <!-- Custom Amount -->
      <div>
        <label class="block text-xs font-medium text-primary-light-content mb-2">
          {{ $t('tokens.customAmount', 'Custom Amount') }}
        </label>
        <div class="flex items-center gap-2">
          <input
            v-model.number="tokensToAdd"
            type="number"
            min="1"
            max="10000"
            :disabled="!isEnabled || addTokensMutation.isLoading.value"
            class="flex-1 px-3 py-2 border border-primary-stroke rounded-lg text-sm focus:ring-2 focus:ring-primary/20 focus:border-primary disabled:opacity-50"
            :placeholder="$t('tokens.addPlaceholder', 'Enter custom amount...')"
            @keyup.enter="handleAddTokens"
          />
          <button
            @click="handleAddTokens"
            :disabled="!canAddTokens"
            class="bg-primary text-white px-4 py-2 rounded-lg hover:bg-primary/80 transition-colors text-sm disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2"
          >
            <div
              v-if="addTokensMutation.isLoading.value"
              class="animate-spin rounded-full h-4 w-4 border-b-2 border-white"
            ></div>
            <i v-else class="fa fa-plus"></i>
            {{ $t('tokens.add', 'Add') }}
          </button>
        </div>

        <!-- Helper text -->
        <div class="text-xs text-primary-light-content mt-1">
          {{ $t('tokens.addHelper', 'Press Enter or click Add to add custom amount') }}
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { useToggleModule, useAddModuleTokens } from '@/mutations/tokens'
import type { ModuleName } from '@/types/tokens'
import Badge from '@/components/ui/Badge.vue'

interface Props {
  module: ModuleName
  tokenCount: number
  isEnabled: boolean
  workspaceId: number
  showAdminControls?: boolean
  isLoading?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  showAdminControls: false,
  isLoading: false,
})

const emit = defineEmits<{
  refresh: []
}>()

// Mutations
const { toggleModule, isLoading: isToggling } = useToggleModule()
const addTokensMutation = useAddModuleTokens()

// State for token input (local to this component)
const tokensToAdd = ref(0)

// Quick add amounts - can be customized based on module or user preferences
const quickAddAmounts = computed(() => {
  // Smart amounts based on current token count
  const current = props.tokenCount
  const baseAmounts = [5, 10, 25, 50, 100]

  // If current count is very low, suggest smaller amounts first
  if (current < 10) {
    return [5, 10, 25, 50]
  }

  // If current count is medium, suggest balanced amounts
  if (current < 50) {
    return [10, 25, 50, 100]
  }

  // For higher counts, suggest larger amounts
  return [25, 50, 100, 250]
})

// Module configurations
const moduleConfigs = {
  screen: {
    icon: 'fa fa-search',
    description: 'Company search and screening',
  },
  target: {
    icon: 'fa fa-bullseye',
    description: 'Advanced targeting features',
  },
  explore: {
    icon: 'fa fa-compass',
    description: 'Market exploration tools',
  },
  stream: {
    icon: 'fa fa-stream',
    description: 'Data streaming capabilities',
  },
}

// State
const isRefreshing = ref(false)

// Computed properties
const moduleIcon = computed(() => moduleConfigs[props.module]?.icon || 'fa fa-cog')
const moduleDescription = computed(
  () => moduleConfigs[props.module]?.description || 'Module functionality',
)

const tokenCountColor = computed(() => {
  if (!props.isEnabled) return 'text-primary-light-content'
  if (props.tokenCount === 0) return 'text-red-600'
  if (props.tokenCount < 10) return 'text-yellow-600'
  return 'text-green-600'
})

const tokenStatusVariant = computed(() => {
  if (props.tokenCount === 0) return 'error'
  if (props.tokenCount < 10) return 'warning'
  return 'success'
})

const tokenStatusText = computed(() => {
  if (props.tokenCount === 0) return 'No tokens'
  if (props.tokenCount < 10) return 'Low tokens'
  return 'Active'
})

const canAddTokens = computed(() => {
  return (
    props.isEnabled &&
    !addTokensMutation.isLoading.value &&
    tokensToAdd.value > 0 &&
    props.showAdminControls
  )
})

// Methods
const handleToggle = async () => {
  try {
    await toggleModule({
      workspaceId: props.workspaceId,
      module: props.module,
      enabled: !props.isEnabled,
    })
    emit('refresh')
  } catch (error) {
    console.error('Failed to toggle module:', error)
  }
}

const addQuickTokens = async (amount: number) => {
  if (!props.isEnabled || addTokensMutation.isLoading.value) return

  try {
    // Set up the mutation with current workspace and module
    addTokensMutation.workspaceId.value = props.workspaceId
    addTokensMutation.module.value = props.module
    addTokensMutation.tokensToAdd.value = amount

    await addTokensMutation.addTokens()

    emit('refresh')
  } catch (error) {
    console.error('Failed to add tokens:', error)
  }
}

const handleAddTokens = async () => {
  if (!canAddTokens.value) return

  try {
    // Set up the mutation with current workspace and module
    addTokensMutation.workspaceId.value = props.workspaceId
    addTokensMutation.module.value = props.module
    addTokensMutation.tokensToAdd.value = tokensToAdd.value

    await addTokensMutation.addTokens()

    // Reset local input
    tokensToAdd.value = 0

    emit('refresh')
  } catch (error) {
    console.error('Failed to add tokens:', error)
  }
}

const refreshTokens = async () => {
  isRefreshing.value = true
  try {
    emit('refresh')
    // Add a small delay for visual feedback
    await new Promise((resolve) => setTimeout(resolve, 300))
  } finally {
    isRefreshing.value = false
  }
}
</script>
