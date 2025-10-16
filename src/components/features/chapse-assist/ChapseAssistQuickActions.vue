<template>
  <div v-if="hasAiPreferences" class="space-y-4">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <div class="flex items-center gap-3">
        <img
          src="@/assets/chapse/head.svg"
          alt="Chapse Assistant"
          class="h-8 w-8 object-contain"
          loading="lazy"
        />
        <h3 class="text-lg font-semibold">{{ title }}</h3>
      </div>
      <Button
        v-if="!isLoadingActions && hasActions && !hasError"
        variant="tertiary"
        size="sm"
        icon="fa fa-refresh"
        @click="handleRefresh"
        :disabled="isLoadingActions"
      >
        Refresh
      </Button>
    </div>

    <!-- Loading State -->
    <div
      v-if="isLoadingActions"
      class="bg-base-200 rounded-card border border-primary-stroke p-6 flex flex-col items-center justify-center gap-4"
    >
      <div class="animate-spin rounded-full h-10 w-10 border-b-2 border-primary"></div>
      <p class="text-sm text-secondary">Generating personalized actions...</p>
    </div>

    <!-- Error State -->
    <div
      v-else-if="hasError"
      class="bg-error-light border border-error-stroke rounded-lg p-6 text-center"
    >
      <i class="fa fa-exclamation-circle text-3xl text-error-light-content mb-3"></i>
      <h4 class="font-semibold text-error-light-content mb-2">Failed to Load Quick Actions</h4>
      <p class="text-sm text-error-light-content mb-4">
        {{ actionsError || 'An error occurred while generating actions. Please try again.' }}
      </p>
      <div class="flex gap-3 justify-center">
        <Button variant="secondary" size="sm" icon="fa fa-refresh" @click="handleRetry">
          Try Again
        </Button>
        <Button
          v-if="actionsError?.includes('preferences')"
          variant="primary"
          size="sm"
          icon="fa fa-cog"
          @click="goToSetup"
        >
          Configure AI Preferences
        </Button>
      </div>
    </div>

    <!-- Quick Actions Grid -->
    <div v-else-if="hasActions" class="grid grid-cols-1 gap-3">
      <button
        v-for="action in quickActions"
        :key="action.id"
        @click="handleActionClick(action)"
        class="group bg-base-200 hover:bg-accent-100 dark:hover:bg-accent-400/20 border border-primary-stroke hover:border-accent-500 rounded-lg p-4 text-left transition-all duration-200 hover:shadow-shadow-2 "
      >
        <div class="flex items-start gap-4">
          <!-- Icon -->
          <div
            class="flex-shrink-0 w-10 h-10 rounded-full bg-sage-950  flex items-center justify-center group-hover:bg-accent-500 group-hover:text-accent-50 transition-all duration-300"
          >
            <i :class="action.icon" class="text-lg"></i>
          </div>

          <!-- Content -->
          <div class="flex-1 min-w-0">
            <h4 class="font-semibold text-base mb-1 group-hover:text-accent-900 dark:group-hover:text-accent-100 transition-colors">
              {{ action.label }}
            </h4>
            <p class="text-sm text-secondary group-hover:text-accent-900 transition-colors line-clamp-2 dark:group-hover:text-accent-100">
              {{ action.description }}
            </p>
          </div>

          <!-- Arrow Icon -->
          <div class="flex-shrink-0">
            <i
              class="fa fa-arrow-right text-secondary group-hover:text-accent-500 transition-colors"
            ></i>
          </div>
        </div>
      </button>
    </div>

    <!-- Empty State (No Actions) -->
    <div
      v-else
      class="bg-base-200 rounded-card border border-primary-stroke p-6 text-center"
    >
      <i class="fa fa-magic text-3xl text-secondary mb-3"></i>
      <h4 class="font-semibold mb-2">No Quick Actions Available</h4>
      <p class="text-sm text-secondary">
        Configure your AI preferences to see personalized recommendations.
      </p>
      <Button
        variant="primary"
        size="sm"
        icon="fa fa-cog"
        class="mt-4"
        @click="goToSetup"
      >
        Configure AI Preferences
      </Button>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useChapseAssist } from '@/composables/useChapseAssist'
import Button from '@/components/ui/Button.vue'
import type { QuickAction } from '@/types/ai-preferences'

interface Props {
  /**
   * Company ID to generate actions for
   */
  companyId: number

  /**
   * Section title
   */
  title?: string

  /**
   * Auto-load actions on mount
   */
  autoLoad?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  title: 'Chaps-e Smart Assist',
  autoLoad: true,
})

const emit = defineEmits<{
  actionClick: [action: QuickAction]
  loadSuccess: [actions: QuickAction[]]
  loadError: [error: string]
}>()

const router = useRouter()
const {
  quickActions,
  isLoadingActions,
  actionsError,
  hasActions,
  hasError,
  hasAiPreferences,
  checkHasPreferences,
  fetchQuickActions,
  retryFetchActions,
  openQuickAction,
} = useChapseAssist()

// Local state
const hasLoadedOnce = ref(false)

/**
 * Load quick actions
 */
async function loadActions() {
  try {
    const actions = await fetchQuickActions(props.companyId)
    hasLoadedOnce.value = true
    emit('loadSuccess', actions)
  } catch (error: any) {
    console.error('Failed to load quick actions:', error)
    emit('loadError', actionsError.value || 'Failed to load actions')
  }
}

/**
 * Handle action button click
 */
function handleActionClick(action: QuickAction) {
  emit('actionClick', action)
  openQuickAction(action, props.companyId)
}

/**
 * Handle refresh button click
 */
async function handleRefresh() {
  await loadActions()
}

/**
 * Handle retry button click
 */
async function handleRetry() {
  try {
    const actions = await retryFetchActions(props.companyId, 3)
    hasLoadedOnce.value = true
    emit('loadSuccess', actions)
  } catch (error) {
    console.error('Retry failed:', error)
    emit('loadError', actionsError.value || 'Failed to load actions after retries')
  }
}

/**
 * Navigate to setup page
 */
function goToSetup() {
  router.push({ name: '/ai-preferences-setup' })
}

/**
 * Watch for company ID changes and reload
 */
watch(
  () => props.companyId,
  (newId, oldId) => {
    if (newId !== oldId && newId && hasAiPreferences.value) {
      loadActions()
    }
  }
)

/**
 * Initialize component
 */
onMounted(async () => {
  // Check if user has AI preferences first
  await checkHasPreferences()

  // Only load actions if preferences exist
  if (props.autoLoad && props.companyId && hasAiPreferences.value) {
    loadActions()
  }
})
</script>
