<template>
  <Teleport to="body">
    <Transition name="modal">
      <div
        v-if="modelValue"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4 backdrop-blur-sm"
        @click.self="emit('update:modelValue', false)"
      >
        <div
          class="bg-base-100 rounded-card border-primary-stroke shadow-shadow-3 flex max-h-[90vh] w-full max-w-4xl flex-col overflow-hidden border"
        >
          <!-- Header -->
          <div class="border-primary-stroke flex items-center justify-between border-b p-6">
            <div class="flex items-center gap-3">
              <div class="bg-primary/10 flex h-10 w-10 items-center justify-center rounded-full">
                <i class="fas fa-bug text-secondary"></i>
              </div>
              <div>
                <h2 class="text-lg font-semibold">
                  {{ t('company.debug.workflowTitle', 'Search Workflow') }}
                </h2>
                <p class="text-secondary text-sm">
                  {{
                    t('company.tasks.completedCount', {
                      completed: completedCount,
                      total: totalTasks,
                    })
                  }}
                </p>
              </div>
            </div>
            <Button
              variant="tertiary"
              icon="fa fa-times"
              icon-only
              @click="emit('update:modelValue', false)"
            />
          </div>

          <!-- Content -->
          <div class="flex-1 overflow-y-auto p-6">
            <!-- Progress Overview -->
            <div class="mb-6">
              <!-- Segmented progress bar -->
              <div class="bg-base-200 flex h-3 w-full overflow-hidden rounded-full">
                <!-- Completed segment -->
                <div
                  v-if="completedPercentage > 0"
                  class="bg-success-500 h-full transition-all duration-500 ease-out"
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
                  class="bg-warning-500 h-full transition-all duration-500 ease-out"
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
                  class="bg-error-500 h-full transition-all duration-500 ease-out"
                  :style="{ width: `${errorPercentage}%` }"
                  :title="
                    t('company.tasks.error', {
                      count: errorCount,
                      percentage: Math.round(errorPercentage),
                    })
                  "
                ></div>

                <!-- Blocked segment -->
                <div
                  v-if="blockedPercentage > 0"
                  class="h-full bg-slate-500 transition-all duration-500 ease-out"
                  :style="{ width: `${blockedPercentage}%` }"
                  :title="
                    t('company.tasks.blocked', {
                      count: blockedCount,
                      percentage: Math.round(blockedPercentage),
                    })
                  "
                ></div>

                <!-- Pending segment -->
                <div
                  v-if="pendingPercentage > 0"
                  class="bg-base-200 h-full transition-all duration-500 ease-out"
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
              <div class="text-secondary mt-3 flex items-center justify-between text-xs">
                <div class="flex items-center gap-4">
                  <span class="flex items-center gap-1.5">
                    <div class="bg-success-500 h-2 w-2 rounded-full"></div>
                    {{ t('company.tasks.completedShort', { count: completedCount }) }}
                  </span>
                  <span v-if="runningCount > 0" class="flex items-center gap-1.5">
                    <div class="bg-warning-500 h-2 w-2 rounded-full"></div>
                    {{ t('company.tasks.runningShort', { count: runningCount }) }}
                  </span>
                  <span v-if="errorCount > 0" class="flex items-center gap-1.5">
                    <div class="bg-error-500 h-2 w-2 rounded-full"></div>
                    {{ t('company.tasks.errorShort', { count: errorCount }) }}
                  </span>
                  <span v-if="pendingCount > 0" class="flex items-center gap-1.5">
                    <div class="bg-secondary h-2 w-2 rounded-full"></div>
                    {{ t('company.tasks.pendingShort', { count: pendingCount }) }}
                  </span>
                  <span v-if="blockedCount > 0" class="flex items-center gap-1.5">
                    <div class="h-2 w-2 rounded-full bg-slate-500"></div>
                    {{ t('company.tasks.blockedShort', { count: blockedCount }) }}
                  </span>
                </div>
              </div>
            </div>

            <!-- Task List -->
            <div class="space-y-3">
              <div
                v-for="task in taskList"
                :key="task.type"
                class="rounded-card flex items-center justify-between border p-4 transition-all duration-300"
                :class="getTaskClass(task)"
              >
                <!-- Task Info -->
                <div class="flex items-center gap-3">
                  <div
                    class="flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-full"
                    :class="getIconContainerClass(task.status)"
                  >
                    <i v-if="task.status === 'running'" class="fas fa-spinner-third fa-spin"></i>
                    <i v-else :class="getTaskIcon(task.type)"></i>
                  </div>

                  <div class="min-w-0 flex-1">
                    <div class="mb-1 flex items-center gap-2">
                      <h3 class="text-sm font-semibold">{{ task.name }}</h3>
                      <Tag
                        :intent="getStatusIntent(task.status)"
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
                      class="text-secondary mt-2 flex items-center gap-3 text-xs"
                    >
                      <span v-if="getTokenInfo(task.type)?.inputTokens">
                        <i class="fas fa-arrow-down text-info-500"></i>
                        {{ formatTokens(getTokenInfo(task.type)?.inputTokens ?? null) }}
                      </span>
                      <span v-if="getTokenInfo(task.type)?.outputTokens">
                        <i class="fas fa-arrow-up text-success-500"></i>
                        {{ formatTokens(getTokenInfo(task.type)?.outputTokens ?? null) }}
                      </span>
                      <span v-if="getTokenInfo(task.type)?.totalCost" class="font-medium">
                        <i class="fas fa-coins text-warning-500"></i>
                        {{ formatCost(getTokenInfo(task.type)?.totalCost ?? null) }}
                      </span>
                    </div>

                    <!-- Error message -->
                    <div v-if="task.error && task.status === 'error'" class="mt-2">
                      <span class="text-error-500 text-xs">{{ task.error }}</span>
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
                  />
                </div>
              </div>
            </div>

            <!-- Global Actions -->
            <div v-if="hasErrorsOrPending" class="border-primary-stroke mt-6 pt-6">
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
                  :label="t('company.tasks.startAll', 'Start all tasks')"
                  @click="startAllPendingTasks"
                  :loading="isStartingAll"
                />
              </div>
            </div>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup lang="ts">
import { Button, Tag } from '@owlint/feathers-vue'
import type { TaskType, TaskStatus, TaskResponse } from '@/types/task'
import { computed, ref } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { companyTasksQuery } from '@/queries/tasks'
import { useQuery } from '@pinia/colada'
import { useRestartTask } from '@/mutations/tasks'
import { useCompanyPermissions } from '@/composables/useCompanyPermissions'
import { useAuthStore } from '@/stores/auth'

const { t } = useI18n()

interface TaskConfig {
  type: TaskType
  name: string
  description: string
}

interface Props {
  modelValue: boolean
}

defineProps<Props>()

const emit = defineEmits<{
  'update:modelValue': [value: boolean]
}>()

const route = useRoute()
// Use type assertion since route.params may have companyId on company routes
const companyId = computed(() => {
  const params = route.params as Record<string, string | string[] | undefined>
  return (params.companyId as string) || ''
})

const isRestarting = ref<TaskType | null>(null)
const isStartingAll = ref(false)

// Task data is now kept fresh via SSE (Server-Sent Events) in useTaskEvents composable
// which invalidates the cache when tasks update. No polling needed.
const { data: tasks } = useQuery(companyTasksQuery, () => ({
  companyId: companyId.value,
}))

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

// Task configuration - all 9 tasks that can run in parallel
// Using internal/dev names for easier debugging
const taskConfigs: TaskConfig[] = [
  {
    type: 'profile',
    name: 'profile',
    description: 'Company profile, business lines, key metrics',
  },
  {
    type: 'digital',
    name: 'digital',
    description: 'Online presence, social media, digital strategy',
  },
  {
    type: 'csr',
    name: 'csr',
    description: 'CSR initiatives, sustainability, social impact',
  },
  {
    type: 'press',
    name: 'press',
    description: 'Press releases, news, media coverage',
  },
  {
    type: 'timeline',
    name: 'timeline',
    description: 'Company history, milestones, key events',
  },
  {
    type: 'products',
    name: 'products',
    description: 'Products, services, offerings',
  },
  {
    type: 'team',
    name: 'team',
    description: 'Leadership team, org structure, key personnel',
  },
  {
    type: 'jobs',
    name: 'jobs',
    description: 'Job openings, career opportunities',
  },
  {
    type: 'data_collection',
    name: 'data_collection',
    description: 'Structured data collection (scraping)',
  },
]

const { mutate: restart } = useRestartTask()

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
      return `${baseClasses} border-success-500`
    case 'error':
      return `${baseClasses} border-error-500`
    case 'running':
      return `${baseClasses} border-warning-500`
    case 'pending':
      return `${baseClasses} border-info-500`
    case 'blocked':
      return `${baseClasses} border-slate-500`
    default:
      return `${baseClasses} border-primary-stroke opacity-60`
  }
}

const getIconContainerClass = (status: TaskStatus | null): string => {
  switch (status) {
    case 'succeeded':
      return 'bg-success-500/10 text-success-500'
    case 'error':
      return 'bg-error-500/10 text-error-500'
    case 'running':
      return 'bg-warning-500/10 text-warning-500'
    case 'pending':
      return 'bg-info-500/10 text-info-500'
    case 'blocked':
      return 'bg-slate-500/10 text-slate-500'
    default:
      return 'bg-base-200 text-secondary'
  }
}

const getStatusIntent = (status: TaskStatus | null) => {
  switch (status) {
    case 'succeeded':
      return 'success'
    case 'error':
      return 'danger'
    case 'running':
      return 'warning'
    case 'pending':
      return 'info'
    case 'blocked':
      return 'neutral'
    default:
      return 'accent'
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
    case 'blocked':
      return t('company.analysisCard.status.blocked', 'Pending (blocked)')
    default:
      return t('company.analysisCard.status.notStarted', 'Not started')
  }
}

// Check if current user is a debug user or in dev mode
const isDebugUser = computed(() => {
  // Always allow in dev mode
  if (import.meta.env.DEV) return true
  const username = authStore.user?.profile?.preferred_username?.toLowerCase()
  return username === 'nmr' || username === 'suh' || username === 'nmr-cv'
})

// Task actions
const canRestartTask = (task: { status: TaskStatus | null }): boolean => {
  // Debug users can restart any task, including successful ones
  if (isDebugUser.value) {
    return task.status !== null
  }
  // Regular users can only restart error or pending tasks
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

const blockedCount = computed(
  () => tasks.value?.filter((t: TaskResponse) => t.status === 'blocked').length || 0,
)

const totalTasks = computed(() => taskConfigs.length)

const hasErrorsOrPending = computed(() => errorCount.value > 0 || pendingCount.value > 0)

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

const blockedPercentage = computed(() =>
  totalTasks.value > 0 ? (blockedCount.value / totalTasks.value) * 100 : 0,
)

const pendingPercentage = computed(() =>
  totalTasks.value > 0 ? (pendingCount.value / totalTasks.value) * 100 : 0,
)

const restartTask = async (taskType: TaskType) => {
  if (!canCreateCompany.value) {
    console.warn('❌ No permission to restart tasks')
    return
  }

  isRestarting.value = taskType
  try {
    const task = tasks.value?.find((t: TaskResponse) => t.type === taskType)
    if (task) {
      console.log('🔄 Restarting task:', task.id, task.type)
      await restart(task.id)
      console.log('✅ Task restarted successfully')
    } else {
      console.warn('⚠️ Task not found for type:', taskType)
    }
  } catch (error) {
    console.error('❌ Error restarting task:', error)
  } finally {
    isRestarting.value = null
  }
}

const startAllPendingTasks = async () => {
  if (!canCreateCompany.value) {
    console.warn('❌ No permission to start tasks')
    return
  }

  isStartingAll.value = true
  try {
    // TODO: Implement start all pending tasks logic
    console.log('Starting all pending tasks...')
  } catch (error) {
    console.error('❌ Error starting all tasks:', error)
  } finally {
    isStartingAll.value = false
  }
}
</script>

<style scoped>
.modal-enter-active,
.modal-leave-active {
  transition: opacity 0.3s ease;
}

.modal-enter-from,
.modal-leave-to {
  opacity: 0;
}

.modal-enter-active .bg-base-100,
.modal-leave-active .bg-base-100 {
  transition: transform 0.3s ease;
}

.modal-enter-from .bg-base-100,
.modal-leave-to .bg-base-100 {
  transform: scale(0.95);
}
</style>
