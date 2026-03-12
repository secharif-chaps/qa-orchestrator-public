<template>
  <div class="bg-base-100 border-primary-stroke overflow-hidden rounded-lg border">
    <button
      class="flex w-full cursor-pointer items-center justify-between border-b border-gray-200 px-4 py-3 text-left dark:border-slate-700"
      @click="isOpen = !isOpen"
      :class="{
        'border-b-0': !isOpen,
      }"
    >
      <div class="flex items-center gap-3">
        <span class="font-medium">{{ t('company.debug.workflowTitle', 'Search Workflow') }}</span>
        <div class="flex items-center">
          <span class="text-secondary text-xs font-medium"
            >{{ completedCount }}/{{ totalTasks }}</span
          >
        </div>
      </div>
      <i class="fa" :class="!isOpen ? 'fa-chevron-up' : 'fa-chevron-down'"></i>
    </button>

    <div v-show="isOpen" class="p-6">
      <!-- Progress Overview -->
      <div class="mb-6">
        <!-- Segmented progress bar -->
        <div class="flex h-3 w-full overflow-hidden rounded-full bg-gray-200 dark:bg-gray-800">
          <!-- Completed segment -->
          <div
            v-if="completedPercentage > 0"
            class="h-full bg-green-400 transition-all duration-500 ease-out"
            :style="{ width: `${completedPercentage}%` }"
            :title="
              t('company.tasks.completed', {
                count: completedCount,
                percentage: Math.round(completedPercentage),
              })
            "
          ></div>

          <!-- Running segment -->
          <div
            v-if="runningPercentage > 0"
            class="h-full bg-orange-400 transition-all duration-500 ease-out"
            :style="{ width: `${runningPercentage}%` }"
            :title="
              t('company.tasks.running', {
                count: runningCount,
                percentage: Math.round(runningPercentage),
              })
            "
          ></div>

          <!-- Error segment -->
          <div
            v-if="errorPercentage > 0"
            class="h-full bg-red-400 transition-all duration-500 ease-out"
            :style="{ width: `${errorPercentage}%` }"
            :title="
              t('company.tasks.error', {
                count: errorCount,
                percentage: Math.round(errorPercentage),
              })
            "
          ></div>

          <!-- Pending segment -->
          <div
            v-if="pendingPercentage > 0"
            class="h-full bg-gray-200 transition-all duration-500 ease-out dark:bg-gray-800"
            :style="{ width: `${pendingPercentage}%` }"
            :title="
              t('company.tasks.pending', {
                count: pendingCount,
                percentage: Math.round(pendingPercentage),
              })
            "
          ></div>
        </div>

        <!-- Status summary -->
        <div class="text-secondary mt-2 flex items-center justify-between text-xs">
          <div class="flex items-center gap-4">
            <span class="flex items-center gap-1">
              <div class="h-2 w-2 rounded-full bg-green-400"></div>
              {{ t('company.tasks.completedShort', { count: completedCount }) }}
            </span>
            <span v-if="runningCount > 0" class="flex items-center gap-1">
              <div class="h-2 w-2 rounded-full bg-orange-400"></div>
              {{ t('company.tasks.runningShort', { count: runningCount }) }}
            </span>
            <span v-if="errorCount > 0" class="flex items-center gap-1">
              <div class="h-2 w-2 rounded-full bg-red-400"></div>
              {{ t('company.tasks.errorShort', { count: errorCount }) }}
            </span>
            <span v-if="pendingCount > 0" class="flex items-center gap-1">
              <div class="h-2 w-2 rounded-full bg-gray-400"></div>
              {{ t('company.tasks.pendingShort', { count: pendingCount }) }}
            </span>
          </div>
        </div>
      </div>

      <!-- Task List -->
      <div class="space-y-2">
        <div
          v-for="task in taskList"
          :key="task.type"
          class="flex items-center justify-between rounded-lg border p-3 transition-all duration-300"
          :class="getTaskClass(task)"
        >
          <!-- Task Info -->
          <div class="flex items-center gap-3">
            <div
              class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-lg"
              :class="getIconContainerClass(task.status)"
            >
              <i v-if="task.status === 'running'" class="fa fa-spinner-third animate-spin"></i>
              <i v-else :class="getTaskIcon(task.type)"></i>
            </div>

            <div class="min-w-0">
              <div class="flex items-center gap-2">
                <h3 class="text-sm font-semibold">{{ task.name }}</h3>
                <Tag
                  :variant="getStatusVariant(task.status)"
                  :label="getStatusLabel(task.status)"
                  size="xs"
                />
              </div>
              <p class="text-secondary truncate text-xs">
                {{ task.description }}
              </p>

              <!-- Token information for admins -->
              <div
                v-if="hasAdminAccess && getTokenInfo(task.type)?.hasTokenData"
                class="text-secondary mt-1 flex items-center gap-3 text-xs"
              >
                <span v-if="getTokenInfo(task.type)?.inputTokens">
                  <i class="fa fa-arrow-down text-blue-500"></i>
                  {{ formatTokens(getTokenInfo(task.type)?.inputTokens ?? null) }}
                </span>
                <span v-if="getTokenInfo(task.type)?.outputTokens">
                  <i class="fa fa-arrow-up text-green-500"></i>
                  {{ formatTokens(getTokenInfo(task.type)?.outputTokens ?? null) }}
                </span>
                <span v-if="getTokenInfo(task.type)?.totalCost" class="font-medium">
                  <i class="fa fa-coins text-yellow-500"></i>
                  {{ formatCost(getTokenInfo(task.type)?.totalCost ?? null) }}
                </span>
              </div>

              <!-- Error message -->
              <div v-if="task.error && task.status === 'error'" class="mt-1">
                <span class="text-xs text-red-500">{{ task.error }}</span>
              </div>
            </div>
          </div>

          <!-- Task Actions -->
          <div class="flex items-center gap-2">
            <!-- Restart button -->
            <Button
              v-if="canRestartTask(task)"
              variant="tertiary"
              size="sm"
              icon="fa fa-rotate-right"
              icon-only
              @click="restartTask(task.type)"
              :loading="isRestarting === task.type"
              class="ml-auto"
            />
          </div>
        </div>
      </div>

      <!-- Global Actions -->
      <div v-if="hasErrorsOrPending" class="border-primary-stroke mt-6 border-t pt-6">
        <div class="flex items-center justify-between">
          <div class="text-secondary text-sm">
            {{
              t(
                'company.tasks.canBeRestarted',
                'Tasks can be restarted or have not been started yet',
              )
            }}
          </div>
          <Button
            variant="secondary"
            size="sm"
            icon="fa fa-play"
            @click="startAllPendingTasks"
            :loading="isStartingAll"
          >
            {{ t('company.tasks.startAll', 'Start all tasks') }}
          </Button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import type { TaskType, TaskStatus, TaskResponse } from '@/types/task'
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { companyTasksQuery } from '@/queries/tasks'
import { useQuery } from '@pinia/colada'
import { useRestartTask } from '@/mutations/tasks'
import { onUnmounted } from 'vue'
import { useCompanyPermissions } from '@/composables/useCompanyPermissions'
import { useAuthStore } from '@/stores/auth'
import { Button } from '@owlint/feathers-vue'
import Tag from '@/components/ui/Tag.vue'

const { t } = useI18n()

interface TaskConfig {
  type: TaskType
  name: string
  description: string
}

const route = useRoute()
const companyId = computed(() => (route.params as { companyId: string }).companyId)

const isDev = import.meta.env.DEV
const isOpen = ref(false)
const isRestarting = ref<TaskType | null>(null)
const isStartingAll = ref(false)

const { data: tasks, refetch: refetchTasks } = useQuery(() =>
  companyTasksQuery({
    companyId: companyId.value,
  }),
)

const pollingInterval = ref<ReturnType<typeof setInterval> | null>(null)
const { canCreateCompany } = useCompanyPermissions()
const authStore = useAuthStore()

// Check if user has admin permissions to view token data
const hasAdminAccess = computed(() =>
  authStore.hasAnyPermission(['admin.organizations', 'admin.users', 'admin.all']),
)

// Helper functions for token formatting
const formatTokens = (tokens: number | null): string => {
  if (tokens === null || tokens === undefined) return '—'
  return tokens.toLocaleString()
}

const formatCost = (cost: number | null): string => {
  if (cost === null || cost === undefined) return '—'
  return `$${cost.toFixed(4)}`
}

const getTokenInfo = (taskType: TaskType) => {
  const task = tasks.value?.find((t: TaskResponse) => t.type === taskType)
  if (!task) return null

  return {
    inputTokens: task.input_tokens,
    outputTokens: task.output_tokens,
    totalCost: task.total_cost,
    hasTokenData:
      task.input_tokens !== null || task.output_tokens !== null || task.total_cost !== null,
  }
}

// Task configuration - all 8 tasks that can run in parallel
const taskConfigs: TaskConfig[] = [
  {
    type: 'profile',
    name: t('company.analysisCards.profile.title', 'Company Profile'),
    description: t(
      'company.analysisCards.profile.description',
      'View detailed company information, business lines, and key metrics',
    ),
  },
  {
    type: 'digital',
    name: t('company.onlinePresence.title', 'Online Presence'),
    description: t('company.onlinePresence.socialMedia', 'Social Media Presence'),
  },
  {
    type: 'csr',
    name: t('company.analysisCards.csr.title', 'Corporate Social Responsibility'),
    description: t(
      'company.analysisCards.csr.description',
      'CSR initiatives, sustainability programs, and social impact',
    ),
  },
  {
    type: 'press',
    name: t('company.analysisCards.press.title', 'Press & Media'),
    description: t(
      'company.analysisCards.press.description',
      'Press releases, news articles, and media coverage',
    ),
  },
  {
    type: 'timeline',
    name: t('company.analysisCards.timeline.title', 'Timeline & History'),
    description: t(
      'company.analysisCards.timeline.description',
      'Company history, milestones, and key events over time',
    ),
  },
  {
    type: 'products',
    name: t('company.analysisCards.products.title', 'Products & Services'),
    description: t(
      'company.analysisCards.products.description',
      'Browse products, services, and offerings',
    ),
  },
  {
    type: 'team',
    name: t('company.analysisCards.team.title', 'Team & Management'),
    description: t(
      'company.analysisCards.team.description',
      'Leadership team, organizational structure, and key personnel',
    ),
  },
  {
    type: 'jobs',
    name: t('company.analysisCards.jobs.title', 'Job Offers'),
    description: t(
      'company.analysisCards.jobs.description',
      'Current job openings and career opportunities',
    ),
  },
]

const restartTaskMutation = useRestartTask()
const { mutate: restart } = restartTaskMutation

// Initialize component when mounted
onMounted(async () => {
  // Start all pending tasks automatically if workflow was previously started
  // performAutoRecovery()
})

// Helper function to get task status
const getTaskStatus = (taskType: TaskType): TaskStatus | null => {
  const task = tasks.value?.find((t: TaskResponse) => t.type === taskType)
  return task?.status || null
}

// Helper function to get task error
const getTaskError = (taskType: TaskType): string | null => {
  const task = tasks.value?.find((t: TaskResponse) => t.type === taskType)
  return task?.error || null
}

// Create combined task list with config and status
const taskList = computed(() => {
  return taskConfigs.map((config) => ({
    ...config,
    status: getTaskStatus(config.type),
    error: getTaskError(config.type),
  }))
})

// Task status utilities
const getTaskIcon = (taskType: TaskType): string => {
  const iconMap: Record<TaskType, string> = {
    profile: 'fas fa-user',
    digital: 'fas fa-globe',
    timeline: 'fas fa-history',
    products: 'fas fa-box',
    jobs: 'fas fa-briefcase',
    csr: 'fas fa-leaf',
    press: 'fas fa-newspaper',
    team: 'fas fa-users',
    data_collection: 'fas fa-database',
  }
  return iconMap[taskType] || 'fas fa-question'
}

const getTaskClass = (task: { status: TaskStatus | null }): string => {
  const baseClasses = 'bg-base-100'

  switch (task.status) {
    case 'succeeded':
      return `${baseClasses} border-green-400`
    case 'error':
      return `${baseClasses} border-red-400`
    case 'running':
      return `${baseClasses} border-orange-400`
    case 'pending':
      return `${baseClasses} border-blue-400`
    default:
      return `${baseClasses} border-gray-300 dark:border-slate-700 opacity-60`
  }
}

const getIconContainerClass = (status: TaskStatus | null): string => {
  switch (status) {
    case 'succeeded':
      return 'bg-green-100 text-green-600 dark:bg-green-400/10 dark:text-green-400'
    case 'error':
      return 'bg-red-100 text-red-600 dark:bg-red-400/10 dark:text-red-400'
    case 'running':
      return 'bg-orange-100 text-orange-600 dark:bg-orange-400/10 dark:text-orange-400'
    case 'pending':
      return 'bg-blue-100 text-blue-600 dark:bg-blue-400/10 dark:text-blue-400'
    default:
      return 'bg-gray-100 text-gray-400 dark:bg-gray-400/10 dark:text-gray-400'
  }
}

const getStatusVariant = (status: TaskStatus | null) => {
  switch (status) {
    case 'succeeded':
      return 'success'
    case 'error':
      return 'error'
    case 'running':
      return 'warning'
    case 'pending':
      return 'info'
    default:
      return 'slate'
  }
}

const getStatusLabel = (status: TaskStatus | null): string => {
  switch (status) {
    case 'succeeded':
      return t('company.analysisCard.status.succeeded', 'Completed')
    case 'error':
      return t('company.analysisCard.status.error', 'Error')
    case 'running':
      return t('company.analysisCard.status.running', 'In progress')
    case 'pending':
      return t('company.analysisCard.status.pending', 'Pending')
    default:
      return t('company.analysisCard.status.notStarted', 'Not started')
  }
}

// Task actions
const canRestartTask = (task: { status: TaskStatus | null }): boolean => {
  // In dev mode, allow restarting any task (for testing)
  if (isDev) return true
  return task.status === 'error' || task.status === 'pending'
}

// Computed stats
const completedCount = computed(
  () => tasks.value?.filter((t) => t.status === 'succeeded').length || 0,
)

const runningCount = computed(
  () => tasks.value?.filter((t: TaskResponse) => t.status === 'running').length || 0,
)

const errorCount = computed(
  () => tasks.value?.filter((t: TaskResponse) => t.status === 'error').length || 0,
)

const pendingCount = computed(() => {
  const existingTasks = new Set(tasks.value?.map((t: TaskResponse) => t.type) || [])
  const totalConfigTasks = taskConfigs.length
  const pendingFromExisting = tasks.value?.filter((t) => t.status === 'pending').length || 0
  const notStartedTasks = totalConfigTasks - existingTasks.size
  return pendingFromExisting + notStartedTasks
})

const totalTasks = computed(() => taskConfigs.length)

const hasErrorsOrPending = computed(() => errorCount.value > 0 || pendingCount.value > 0)

// Auto-open when tasks are not complete
watch(
  completedCount,
  (newCount) => {
    if (newCount < 8) {
      isOpen.value = true
    } else {
      isOpen.value = false
    }
  },
  { immediate: true },
)

// Polling logic for running tasks
const hasRunningTasks = computed(() => tasks.value?.some((t) => t.status === 'running') || false)

const startPolling = () => {
  if (pollingInterval.value) return // Already polling

  console.log('🔄 Starting task polling...')
  pollingInterval.value = setInterval(() => {
    if (hasRunningTasks.value) {
      console.log('🔍 Polling for task updates...')
      refetchTasks()
    } else {
      console.log('✅ No running tasks, stopping poll')
      stopPolling()
    }
  }, 10000) // Poll every 10 seconds
}

const stopPolling = () => {
  if (pollingInterval.value) {
    clearInterval(pollingInterval.value)
    pollingInterval.value = null
    console.log('⏹️ Stopped task polling')
  }
}

// Percentage calculations for segmented progress bar
const completedPercentage = computed(() =>
  totalTasks.value > 0 ? (completedCount.value / totalTasks.value) * 100 : 0,
)

const runningPercentage = computed(() =>
  totalTasks.value > 0 ? (runningCount.value / totalTasks.value) * 100 : 0,
)

const errorPercentage = computed(() =>
  totalTasks.value > 0 ? (errorCount.value / totalTasks.value) * 100 : 0,
)

const pendingPercentage = computed(() =>
  totalTasks.value > 0 ? (pendingCount.value / totalTasks.value) * 100 : 0,
)

// Watch for running tasks to start/stop polling
watch(
  hasRunningTasks,
  (isRunning) => {
    if (isRunning) {
      startPolling()
    } else {
      stopPolling()
    }
  },
  { immediate: true },
)

// Cleanup on unmount
onUnmounted(() => {
  stopPolling()
})

const restartTask = async (taskType: TaskType) => {
  if (!canCreateCompany.value) {
    console.warn('❌ No permission to restart tasks')
    return
  }

  isRestarting.value = taskType
  try {
    const task = tasks.value?.find((t: TaskResponse) => t.type === taskType)
    if (task) {
      console.log(`🔄 Restarting task ${taskType}`)
      await restart(task.id)
      console.log(`✅ Task ${taskType} restarted successfully`)
    }
  } catch (error) {
    console.error('❌ Error restarting task:', error)
  } finally {
    isRestarting.value = null
  }
}

// Restart all pending/error tasks
const performAutoRecovery = async () => {
  const pendingOrErrorTasks = tasks.value?.filter(
    (t: TaskResponse) => t.status === 'pending' || t.status === 'error',
  )
  if (!pendingOrErrorTasks?.length) return

  for (const task of pendingOrErrorTasks) {
    try {
      await restart(task.id)
    } catch (error) {
      console.error(`❌ Failed to restart task ${task.type}:`, error)
    }
  }
  await refetchTasks()
}

const startAllPendingTasks = async () => {
  if (!canCreateCompany.value) {
    console.warn('❌ No permission to start tasks')
    return
  }

  isStartingAll.value = true
  try {
    await performAutoRecovery()
  } catch (error) {
    console.error('❌ Error starting all tasks:', error)
  } finally {
    isStartingAll.value = false
  }
}
</script>

<style scoped>
/* Custom animations */
@keyframes pulse-ring {
  0% {
    transform: scale(0.8);
    opacity: 1;
  }
  100% {
    transform: scale(1.2);
    opacity: 0;
  }
}

.animate-pulse-ring {
  animation: pulse-ring 1.5s ease-out infinite;
}
</style>
