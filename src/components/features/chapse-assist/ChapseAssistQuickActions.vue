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
        {{ $t('chapseAssist.quickActions.refresh', 'Refresh') }}
      </Button>
    </div>

    <!-- Loading State -->
    <div
      v-if="isLoadingActions"
      class="bg-base-200 rounded-card border border-primary-stroke p-6 flex flex-col items-center justify-center gap-4"
    >
      <div class="animate-spin rounded-full h-10 w-10 border-b-2 border-primary"></div>
      <p class="text-sm text-secondary">{{ $t('chapseAssist.quickActions.loading', 'Generating personalized actions...') }}</p>
    </div>

    <!-- Error State -->
    <Alert
      v-else-if="hasError"
      variant="error"
      :title="$t('chapseAssist.quickActions.error.title', 'Failed to Load Quick Actions')"
      :message="actionsError || $t('chapseAssist.quickActions.error.message', 'An error occurred while generating actions. Please try again.')"
      icon="fa fa-exclamation-circle"
      decoration-icon="fa fa-magic"
    >
      <template #actions>
        <div class="flex gap-3">
          <Button variant="secondary" size="sm" icon="fa fa-refresh" @click="handleRetry">
            {{ $t('chapseAssist.quickActions.tryAgain', 'Try Again') }}
          </Button>
          <Button
            v-if="actionsError?.includes('preferences')"
            variant="primary"
            size="sm"
            icon="fa fa-cog"
            @click="goToSetup"
          >
            {{ $t('chapseAssist.quickActions.configure', 'Configure AI Preferences') }}
          </Button>
        </div>
      </template>
    </Alert>

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

    <!-- Empty State (No Actions) -->
    <div v-else class="bg-base-200 rounded-card border border-primary-stroke p-6 text-center">
      <i class="fa fa-magic text-3xl text-secondary mb-3"></i>
      <h4 class="font-semibold mb-2">{{ $t('chapseAssist.quickActions.empty.title', 'No Quick Actions Available') }}</h4>
      <p class="text-sm text-secondary">
        {{ $t('chapseAssist.quickActions.empty.message', 'Configure your AI preferences to see personalized recommendations.') }}
      </p>
      <Button variant="primary" size="sm" icon="fa fa-cog" class="mt-4" @click="goToSetup">
        {{ $t('chapseAssist.quickActions.configure', 'Configure AI Preferences') }}
      </Button>
    </div>
  </div>
</template>

<script setup lang="ts">
import Alert from '@/components/ui/Alert.vue'
import Button from '@/components/ui/Button.vue'
import { useChapseAssist } from '@/composables/useChapseAssist'
import type { QuickAction } from '@/types/ai-preferences'
import type { Company } from '@/types/company'
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
   * Section title
   */
  title?: string

  /**
   * Auto-load actions on mount
   */
  autoLoad?: boolean
}

const props = defineProps<Props>()

// Computed props with translations as defaults
const title = computed(() => props.title ?? t('chapseAssist.quickActions.title', 'Chaps-e Smart Assist'))
const autoLoad = computed(() => props.autoLoad ?? true)

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
 * Check if all company tasks have succeeded
 * Quick actions should only be enabled if tasks completed successfully
 */
const areTasksSuccessful = computed(() => {
  if (!props.company?.tasks || props.company.tasks.length === 0) {
    return false
  }

  // Check if all tasks have succeeded status
  return props.company.tasks.every((task) => task.status === 'succeeded')
})

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
 * Initialize component
 */
onMounted(async () => {
  // Check if user has AI preferences first
  await checkHasPreferences()

  // Only load actions if preferences exist
  if (autoLoad.value && props.companyId && hasAiPreferences.value) {
    loadActions()
  }
})
</script>
