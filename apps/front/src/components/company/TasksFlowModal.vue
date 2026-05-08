<template>
  <Teleport to="body">
    <Transition name="modal">
      <div
        v-if="modelValue"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4 backdrop-blur-sm"
        @click.self="emit('update:modelValue', false)"
      >
        <div
          class="rounded-card border-primary-lighter-stroke shadow-3 flex max-h-[90vh] w-full max-w-224 flex-col overflow-hidden border bg-white"
        >
          <!-- Header -->
          <div class="border-primary-lighter-stroke flex items-center justify-between border-b p-6">
            <div class="flex items-center gap-3">
              <div class="bg-primary/10 flex h-10 w-10 items-center justify-center rounded-full">
                <i class="fas fa-bug text-neutral-black-font"></i>
              </div>
              <div>
                <h2 class="text-lg font-semibold">
                  {{ t('screen.company.debug.workflowTitle') }}
                </h2>
                <p class="text-neutral-black-font text-sm">
                  {{
                    t('screen.company.tasks.completedCount', {
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
              <div class="bg-primary-lightest flex h-3 w-full overflow-hidden rounded-full">
                <!-- Completed segment -->
                <div
                  v-if="completedPercentage > 0"
                  class="bg-success-500 h-full transition-all duration-500 ease-out"
                  :style="{ width: `${completedPercentage}%` }"
                  :title="
                    t('screen.company.tasks.completed', {
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
                    t('screen.company.tasks.running', {
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
                    t('screen.company.tasks.error', {
                      count: errorCount,
                      percentage: Math.round(errorPercentage),
                    })
                  "
                ></div>

                <!-- Pending segment -->
                <div
                  v-if="pendingPercentage > 0"
                  class="bg-primary-lightest h-full transition-all duration-500 ease-out"
                  :style="{ width: `${pendingPercentage}%` }"
                  :title="
                    t('screen.company.tasks.pending', {
                      count: pendingCount,
                      percentage: Math.round(pendingPercentage),
                    })
                  "
                ></div>
              </div>

              <!-- Status summary -->
              <div class="text-neutral-black-font mt-3 flex items-center justify-between text-xs">
                <div class="flex items-center gap-4">
                  <span class="flex items-center gap-1.5">
                    <div class="bg-success-500 h-2 w-2 rounded-full"></div>
                    {{ t('screen.company.tasks.completedShort', { count: completedCount }) }}
                  </span>
                  <span v-if="runningCount > 0" class="flex items-center gap-1.5">
                    <div class="bg-warning-500 h-2 w-2 rounded-full"></div>
                    {{ t('screen.company.tasks.runningShort', { count: runningCount }) }}
                  </span>
                  <span v-if="errorCount > 0" class="flex items-center gap-1.5">
                    <div class="bg-error-500 h-2 w-2 rounded-full"></div>
                    {{ t('screen.company.tasks.errorShort', { count: errorCount }) }}
                  </span>
                  <span v-if="pendingCount > 0" class="flex items-center gap-1.5">
                    <div class="bg-secondary h-2 w-2 rounded-full"></div>
                    {{ t('screen.company.tasks.pendingShort', { count: pendingCount }) }}
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
                    <Icon v-if="task.status === 'running'" icon="fa-spinner-third fa-spin" />
                    <Icon v-else :icon="getTaskIcon(task.type)" />
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
                    <p class="text-neutral-black-font truncate text-xs">
                      {{ task.description }}
                    </p>

                    <!-- Token information for admins -->
                    <div
                      v-if="hasAdminAccess && getTokenInfo(task.type)?.hasTokenData"
                      class="text-neutral-black-font mt-2 flex items-center gap-3 text-xs"
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
            <div v-if="hasErrorsOrPending" class="border-primary-lighter-stroke mt-6 pt-6">
              <div class="flex items-center justify-between">
                <div class="text-neutral-black-font text-sm">
                  {{ t('screen.company.tasks.canBeRestarted') }}
                </div>
                <Button
                  variant="secondary"
                  size="sm"
                  icon="fa fa-play"
                  :label="t('screen.company.tasks.startAll')"
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
import { useCompanyPermissions } from '@/composables/useCompanyPermissions'
import { useRestartTask } from '@/mutations/tasks'
import { companyTasksQuery } from '@/queries/tasks'
import { useAuthStore } from '@/stores/auth'
import type { TaskResponse, TaskStatus, TaskType } from '@/types/task'
import { toast } from '@/utils/toast'
import { Button, Icon, Tag } from '@owlint/feathers-vue'
import { useQuery } from '@pinia/colada'
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'

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
const { data: tasks } = useQuery(() =>
  companyTasksQuery({
    companyId: companyId.value,
  }),
)

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

// Task types list
const taskTypes: TaskType[] = [
  'profile',
  'digital',
  'csr',
  'press',
  'timeline',
  'products',
  'team',
  'jobs',
  'corporate_structure',
  'sanctions',
]

const taskTypeNameMap: Record<TaskType, string> = {
  profile: t('screen.company.tasks.types.profile.name'),
  digital: t('screen.company.tasks.types.digital.name'),
  csr: t('screen.company.tasks.types.csr.name'),
  press: t('screen.company.tasks.types.press.name'),
  timeline: t('screen.company.tasks.types.timeline.name'),
  products: t('screen.company.tasks.types.products.name'),
  team: t('screen.company.tasks.types.team.name'),
  jobs: t('screen.company.tasks.types.jobs.name'),
  corporate_structure: t('screen.company.tasks.types.corporate_structure.name'),
  sanctions: t('screen.company.tasks.types.sanctions.name'),
  financial: t('screen.company.tasks.types.financial.name'),
}

const taskTypeDescriptionMap: Record<TaskType, string> = {
  profile: t('screen.company.tasks.types.profile.description'),
  digital: t('screen.company.tasks.types.digital.description'),
  csr: t('screen.company.tasks.types.csr.description'),
  press: t('screen.company.tasks.types.press.description'),
  timeline: t('screen.company.tasks.types.timeline.description'),
  products: t('screen.company.tasks.types.products.description'),
  team: t('screen.company.tasks.types.team.description'),
  jobs: t('screen.company.tasks.types.jobs.description'),
  corporate_structure: t('screen.company.tasks.types.corporate_structure.description'),
  sanctions: t('screen.company.tasks.types.sanctions.description'),
  financial: t('screen.company.tasks.types.financial.description'),
}

const taskConfigs: TaskConfig[] = taskTypes.map((type) => ({
  type,
  name: taskTypeNameMap[type],
  description: taskTypeDescriptionMap[type],
}))

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
    corporate_structure: 'fas fa-sitemap',
    sanctions: 'fas fa-shield-halved',
    financial: 'fas fa-chart-line',
  }
  return iconMap[taskType] || 'fas fa-question'
}

const getTaskClass = (task: { status: TaskStatus | null }): string => {
  const baseClasses = 'bg-white'

  switch (task.status) {
    case 'succeeded':
      return `${baseClasses} border-success-500`
    case 'error':
      return `${baseClasses} border-error-500`
    case 'running':
      return `${baseClasses} border-warning-500`
    case 'pending':
      return `${baseClasses} border-info-500`
    default:
      return `${baseClasses} border-primary-lighter-stroke opacity-60`
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
    default:
      return 'bg-primary-lightest text-neutral-black-font'
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
    default:
      return 'accent'
  }
}

const getStatusLabel = (status: TaskStatus | null): string => {
  switch (status) {
    case 'succeeded':
      return t('screen.company.analysisCard.status.succeeded')
    case 'error':
      return t('screen.company.analysisCard.status.error')
    case 'running':
      return t('screen.company.analysisCard.status.running')
    case 'pending':
      return t('screen.company.analysisCard.status.pending')
    default:
      return t('screen.company.analysisCard.status.notStarted')
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

  const pendingOrErrorTasks = tasks.value?.filter(
    (task: TaskResponse) => task.status === 'pending' || task.status === 'error',
  )
  if (!pendingOrErrorTasks?.length) return

  isStartingAll.value = true
  try {
    const results = await Promise.allSettled(pendingOrErrorTasks.map((task) => restart(task.id)))
    const failed = results.filter((r) => r.status === 'rejected')
    if (failed.length === pendingOrErrorTasks.length) {
      toast.error(t('screen.company.tasks.startAllError'))
    } else if (failed.length > 0) {
      toast.warning(
        t('screen.company.tasks.startAllPartial', {
          failed: failed.length,
          total: pendingOrErrorTasks.length,
        }),
      )
    } else {
      toast.success(t('screen.company.tasks.startAllSuccess'))
    }
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

.modal-enter-active .bg-white,
.modal-leave-active .bg-white {
  transition: transform 0.3s ease;
}

.modal-enter-from .bg-white,
.modal-leave-to .bg-white {
  transform: scale(0.95);
}
</style>
