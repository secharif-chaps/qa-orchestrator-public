<template>
  <!-- Initial Loading State (checking preferences) -->
  <div
    v-if="isCheckingPreferences"
    class="space-y-4"
  >
    <div class="flex items-center gap-3">
      <img
        src="@/assets/chapse/head.svg"
        alt="Chapse Assistant"
        class="h-8 w-8 object-contain"
        loading="lazy"
      />
      <h3 class="text-lg font-semibold">{{ title }}</h3>
    </div>
    <div class="bg-base-200 rounded-card border border-primary-stroke p-6 flex flex-col items-center justify-center gap-4">
      <div class="animate-spin rounded-full h-10 w-10 border-b-2 border-primary"></div>
      <p class="text-sm text-secondary">{{ $t('chapseAssist.quickActions.checkingPreferences', 'Checking AI preferences...') }}</p>
    </div>
  </div>

  <!-- Error checking preferences -->
  <div v-else-if="preferencesCheckError" class="space-y-4">
    <div class="flex items-center gap-3">
      <img
        src="@/assets/chapse/head.svg"
        alt="Chapse Assistant"
        class="h-8 w-8 object-contain"
        loading="lazy"
      />
      <h3 class="text-lg font-semibold">{{ title }}</h3>
    </div>
    <div class="flex flex-col gap-3">
      <Alert
        variant="danger"
        :title="$t('chapseAssist.quickActions.error.preferencesCheck', 'Failed to Check Preferences')"
        :description="preferencesCheckError ?? ''"
        icon="fa-exclamation-circle"
      />
      <div class="flex justify-end">
        <Button variant="secondary" size="sm" icon="fa fa-refresh" :label="$t('chapseAssist.quickActions.tryAgain', 'Try Again')" @click="retryPreferencesCheck" />
      </div>
    </div>
  </div>

  <!-- Main Content (only shown after preferences check succeeds AND user has preferences) -->
  <div v-else-if="hasAiPreferences" class="space-y-4">
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
        :label="$t('chapseAssist.quickActions.refresh', 'Refresh')"
        @click="handleRefresh"
        :disabled="isLoadingActions"
      />
    </div>

    <!-- Loading State (generating actions) -->
    <div
      v-if="isLoadingActions"
      class="bg-base-200 rounded-card border border-primary-stroke p-6 flex flex-col items-center justify-center gap-4"
    >
      <div class="animate-spin rounded-full h-10 w-10 border-b-2 border-primary"></div>
      <p class="text-sm text-secondary">{{ $t('chapseAssist.quickActions.loading', 'Generating personalized actions...') }}</p>
    </div>

    <!-- Error State -->
    <div v-else-if="hasError" class="flex flex-col gap-3">
      <Alert
        variant="danger"
        :title="$t('chapseAssist.quickActions.error.title', 'Failed to Load Quick Actions')"
        :description="actionsError || $t('chapseAssist.quickActions.error.message', 'An error occurred while generating actions. Please try again.')"
        icon="fa-exclamation-circle"
      />
      <div class="flex gap-3 justify-end">
        <Button variant="secondary" size="sm" icon="fa fa-refresh" :label="$t('chapseAssist.quickActions.tryAgain', 'Try Again')" @click="handleRetry" />
        <Button
          v-if="actionsError?.includes('preferences')"
          variant="primary"
          size="sm"
          icon="fa fa-cog"
          :label="$t('chapseAssist.quickActions.configure', 'Configure AI Preferences')"
          @click="goToSetup"
        />
      </div>
    </div>

    <!-- Quick Actions Grid -->
    <div v-else-if="hasActions" class="grid grid-cols-1 gap-3">
      <button
        v-for="action in quickActions"
        :key="action.id"
        @click="handleActionClick(action)"
        :disabled="!areTasksSuccessful"
        class="group bg-base-200 border border-primary-stroke rounded-lg p-4 text-left transition-all duration-200"
        :class="{
          'opacity-50 cursor-not-allowed': !areTasksSuccessful,
          'hover:bg-accent-100 dark:hover:bg-accent-400/20 hover:border-accent-500 hover:shadow-shadow-2':
            areTasksSuccessful,
        }"
      >
        <div class="flex items-start gap-4">
          <!-- Icon -->
          <div
            class="flex-shrink-0 w-10 h-10 rounded-full bg-sage-200 dark:bg-sage-950 flex items-center justify-center transition-all duration-300"
            :class="{
              'group-hover:bg-accent-500 group-hover:text-accent-50': areTasksSuccessful,
            }"
          >
            <i :class="action.icon" class="text-lg"></i>
          </div>

          <!-- Content -->
          <div class="flex-1 min-w-0">
            <h4
              class="font-semibold text-base mb-1 transition-colors"
              :class="{
                'group-hover:text-accent-900 dark:group-hover:text-accent-100': areTasksSuccessful,
              }"
            >
              {{ action.label }}
            </h4>
            <p
              class="text-sm text-secondary transition-colors line-clamp-2"
              :class="{
                'group-hover:text-accent-900 dark:group-hover:text-accent-100': areTasksSuccessful,
              }"
            >
              {{ action.description }}
            </p>
          </div>

          <!-- Arrow Icon -->
          <div class="flex-shrink-0">
            <i
              class="fa fa-arrow-right text-secondary transition-colors"
              :class="{
                'group-hover:text-accent-500': areTasksSuccessful,
              }"
            ></i>
          </div>
        </div>
      </button>
    </div>

    <!-- Empty State (No Actions) - Only show after we've attempted to load -->
    <div v-else-if="hasLoadedOnce" class="bg-base-200 rounded-card border border-primary-stroke p-6 text-center">
      <i class="fa fa-magic text-3xl text-secondary mb-3"></i>
      <h4 class="font-semibold mb-2">{{ $t('chapseAssist.quickActions.empty.title', 'No Quick Actions Available') }}</h4>
      <p class="text-sm text-secondary">
        {{ $t('chapseAssist.quickActions.empty.loadedMessage', 'Unable to generate quick actions for this company. Try refreshing or check back later.') }}
      </p>
      <Button variant="secondary" size="sm" icon="fa fa-refresh" class="mt-4" :label="$t('chapseAssist.quickActions.tryAgain', 'Try Again')" @click="handleRetry" />
    </div>

    <!-- Initial State (Not yet loaded) - Component doesn't render anything until first load attempt completes -->
  </div>
</template>

<script setup lang="ts">
import { Alert, Button } from '@owlint/feathers-vue'
import { useChapseAssist } from '@/composables/useChapseAssist'
import type { QuickAction } from '@/types/ai-preferences'
import type { Company } from '@/types/company'
import type { TaskResponse } from '@/types/task'
import { computed, onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()

interface Props {
  /**
   * Company ID to generate actions for
   */
  companyId: number

  /**
   * Company data (to check task statuses)
   */
  company?: Company

  /**
   * Tasks data (fetched separately from company)
   */
  tasks?: TaskResponse[]

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
  autoLoad: true,
})

// Computed props with translations as defaults
const title = computed(() => props.title ?? t('chapseAssist.quickActions.title', 'Chaps-e Smart Assist'))

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
const isCheckingPreferences = ref(true) // Start as true since we check on mount
const preferencesCheckError = ref<string | null>(null)

/**
 * Check if all company tasks have succeeded
 * Quick actions should only be enabled if tasks completed successfully
 * Uses props.tasks (fetched separately) or falls back to props.company?.tasks
 */
const areTasksSuccessful = computed(() => {
  // Prefer tasks prop (fetched separately), fallback to company.tasks
  const tasksList = props.tasks || props.company?.tasks

  if (!tasksList || tasksList.length === 0) {
    return false
  }

  // Check if all tasks have succeeded status
  return tasksList.every((task) => task.status === 'succeeded')
})

/**
 * Load quick actions
 */
async function loadActions() {
  try {
    const actions = await fetchQuickActions(props.companyId)
    emit('loadSuccess', actions)
  } catch (error: any) {
    console.error('Failed to load quick actions:', error)
    emit('loadError', actionsError.value || 'Failed to load actions')
  } finally {
    // Always mark as loaded so we show appropriate state (not infinite loading)
    hasLoadedOnce.value = true
  }
}

/**
 * Handle action button click
 */
function handleActionClick(action: QuickAction) {
  // Don't execute action if tasks haven't succeeded
  if (!areTasksSuccessful.value) {
    console.log('Quick action disabled: Company tasks have not succeeded yet')
    return
  }

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
  },
)

/**
 * Check preferences and handle errors
 */
async function doCheckPreferences() {
  isCheckingPreferences.value = true
  preferencesCheckError.value = null

  try {
    await checkHasPreferences()
  } catch (error: any) {
    console.error('Error checking AI preferences:', error)
    preferencesCheckError.value = error.message || 'Failed to check AI preferences'
  } finally {
    isCheckingPreferences.value = false
  }
}

/**
 * Retry checking preferences
 */
async function retryPreferencesCheck() {
  await doCheckPreferences()

  // If preferences exist after retry, load actions
  if (hasAiPreferences.value && props.autoLoad && props.companyId) {
    loadActions()
  }
}

/**
 * Initialize component
 */
onMounted(async () => {
  // Check if user has AI preferences first
  await doCheckPreferences()

  // Only load actions if preferences exist
  if (props.autoLoad && props.companyId && hasAiPreferences.value) {
    loadActions()
  }
})
</script>
