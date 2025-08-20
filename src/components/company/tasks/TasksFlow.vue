<template>
  <div class="bg-bg1 border border-border-2 rounded-lg overflow-hidden">
    <button
      class="w-full px-4 py-3 flex items-center justify-between text-left border-b border-gray-200 dark:border-slate-700 cursor-pointer"
      @click="isOpen = !isOpen"
      :class="{
        'border-b-0': !isOpen,
      }"
    >
      <div class="flex items-center gap-3">
        <span class="font-medium">Workflow de recherche</span>
        <div class="flex items-center">
          <span class="text-xs text-secondary font-medium"
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
        <div class="w-full bg-gray-200 dark:bg-gray-800 rounded-full h-3 overflow-hidden flex">
          <!-- Completed segment -->
          <div
            v-if="completedPercentage > 0"
            class="bg-green-400 h-full transition-all duration-500 ease-out"
            :style="{ width: `${completedPercentage}%` }"
            :title="`${completedCount} tâches terminées (${Math.round(completedPercentage)}%)`"
          ></div>

          <!-- Running segment -->
          <div
            v-if="runningPercentage > 0"
            class="bg-orange-400 h-full transition-all duration-500 ease-out"
            :style="{ width: `${runningPercentage}%` }"
            :title="`${runningCount} tâches en cours (${Math.round(runningPercentage)}%)`"
          ></div>

          <!-- Error segment -->
          <div
            v-if="errorPercentage > 0"
            class="bg-red-400 h-full transition-all duration-500 ease-out"
            :style="{ width: `${errorPercentage}%` }"
            :title="`${errorCount} tâches en erreur (${Math.round(errorPercentage)}%)`"
          ></div>

          <!-- Pending segment -->
          <div
            v-if="pendingPercentage > 0"
            class="bg-gray-200 dark:bg-gray-800 h-full transition-all duration-500 ease-out"
            :style="{ width: `${pendingPercentage}%` }"
            :title="`${pendingCount} tâches en attente (${Math.round(pendingPercentage)}%)`"
          ></div>
        </div>

        <!-- Status summary -->
        <div class="flex items-center justify-between mt-2 text-xs text-secondary">
          <div class="flex items-center gap-4">
            <span class="flex items-center gap-1">
              <div class="w-2 h-2 bg-green-400 rounded-full"></div>
              {{ completedCount }} terminées
            </span>
            <span v-if="runningCount > 0" class="flex items-center gap-1">
              <div class="w-2 h-2 bg-orange-400 rounded-full"></div>
              {{ runningCount }} en cours
            </span>
            <span v-if="errorCount > 0" class="flex items-center gap-1">
              <div class="w-2 h-2 bg-red-400 rounded-full"></div>
              {{ errorCount }} en erreur
            </span>
            <span v-if="pendingCount > 0" class="flex items-center gap-1">
              <div class="w-2 h-2 bg-gray-400 rounded-full"></div>
              {{ pendingCount }} en attente
            </span>
          </div>
        </div>
      </div>

      <!-- Task List -->
      <div class="space-y-2">
        <div
          v-for="task in taskList"
          :key="task.type"
          class="flex items-center justify-between p-3 rounded-lg border transition-all duration-300"
          :class="getTaskClass(task)"
        >
          <!-- Task Info -->
          <div class="flex items-center gap-3">
            <div
              class="w-10 h-10 rounded-lg flex items-center justify-center flex-shrink-0"
              :class="getIconContainerClass(task.status)"
            >
              <i v-if="task.status === 'running'" class="fa fa-spinner-third animate-spin"></i>
              <i v-else :class="getTaskIcon(task.type)"></i>
            </div>

            <div class="min-w-0">
              <div class="flex items-center gap-2">
                <h3 class="font-semibold text-sm">{{ task.name }}</h3>
                <Badge
                  :variant="getStatusVariant(task.status)"
                  :label="getStatusLabel(task.status)"
                  size="xs"
                />
              </div>
              <p class="text-xs text-secondary truncate">{{ task.description }}</p>

              <!-- Token information for admins -->
              <div
                v-if="hasAdminAccess && getTokenInfo(task.type)?.hasTokenData"
                class="mt-1 flex items-center gap-3 text-xs text-secondary"
              >
                <span v-if="getTokenInfo(task.type)?.inputTokens">
                  <i class="fa fa-arrow-down text-blue-500"></i>
                  {{ formatTokens(getTokenInfo(task.type)?.inputTokens) }}
                </span>
                <span v-if="getTokenInfo(task.type)?.outputTokens">
                  <i class="fa fa-arrow-up text-green-500"></i>
                  {{ formatTokens(getTokenInfo(task.type)?.outputTokens) }}
                </span>
                <span v-if="getTokenInfo(task.type)?.totalCost" class="font-medium">
                  <i class="fa fa-coins text-yellow-500"></i>
                  {{ formatCost(getTokenInfo(task.type)?.totalCost) }}
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
              v-if="(canRestartTask(task) && canEditCompany) || isDev"
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
      <div v-if="hasErrorsOrPending && canEditCompany" class="mt-6 pt-6 border-t border-border-2">
        <div class="flex items-center justify-between">
          <div class="text-sm text-secondary">
            Des tâches peuvent être redémarrées ou ne sont pas encore lancées
          </div>
          <Button
            variant="secondary"
            size="sm"
            icon="fa fa-play"
            @click="startAllPendingTasks"
            :loading="isStartingAll"
          >
            Démarrer toutes les tâches
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
import { companyTasksQuery } from '@/queries/tasks'
import { useQuery } from '@pinia/colada'
import { useRestartTask } from '@/mutations/tasks'
import { useDebounceFn } from '@vueuse/core'
import { onUnmounted } from 'vue'
import { useCompanyPermissions } from '@/composables/useCompanyPermissions'
import { useAuthStore } from '@/stores/auth'
import Button from '@/components/ui/Button.vue'
import Badge from '@/components/ui/Badge.vue'
import Alert from '@/components/ui/Alert.vue'

interface TaskConfig {
  type: TaskType
  name: string
  description: string
}

const route = useRoute()
const companyId = computed(() => route.params.companyId as string)

const isDev = import.meta.env.DEV
const isOpen = ref(false)
const isRestarting = ref<TaskType | null>(null)
const isStartingAll = ref(false)

const { data: tasks, refetch: refetchTasks } = useQuery(companyTasksQuery, () => ({
  companyId: companyId.value,
}))

const pollingInterval = ref<NodeJS.Timeout | null>(null)
const { canEditCompany } = useCompanyPermissions()
const authStore = useAuthStore()

// Check if user has admin permissions to view token data
const hasAdminAccess = computed(() =>
  authStore.hasAnyRole(['admin.workspaces', 'admin.users', 'admin.all']),
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
    name: 'Profil',
    description: "Informations générales de l'entreprise",
  },
  {
    type: 'digital',
    name: 'Digital',
    description: 'Présence numérique et réseaux sociaux',
  },
  {
    type: 'csr',
    name: 'RSE',
    description: 'Responsabilité sociale et environnementale',
  },
  {
    type: 'press',
    name: 'Presse',
    description: 'Articles et communiqués de presse',
  },
  {
    type: 'timeline',
    name: 'Timeline',
    description: 'Historique et événements importants',
  },
  {
    type: 'products',
    name: 'Produits',
    description: 'Catalogue et gamme de produits',
  },
  {
    type: 'team',
    name: 'Équipe',
    description: 'Organigramme et membres clés',
  },
  {
    type: 'jobs',
    name: 'Emplois',
    description: "Offres d'emploi et recrutement",
  },
]

const restartTaskMutation = useRestartTask()
const { mutate: restart } = restartTaskMutation

// Initialize component when mounted
onMounted(async () => {
  // Start all pending tasks automatically if workflow was previously started
  performAutoRecovery()
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
  }
  return iconMap[taskType] || 'fas fa-question'
}

const getTaskClass = (task: { status: TaskStatus | null }): string => {
  const baseClasses = 'bg-bg1'

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
      return 'Terminée'
    case 'error':
      return 'Erreur'
    case 'running':
      return 'En cours'
    case 'pending':
      return 'En attente'
    default:
      return 'Non démarrée'
  }
}

// Task actions
const canRestartTask = (task: { status: TaskStatus | null }): boolean => {
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

// Parallel task execution - start all pending tasks at once
const performAutoRecovery = async () => {
  const currentTasks = tasks.value
  if (!currentTasks || currentTasks.length === 0) return

  // Early exit if all tasks succeeded
  const allSucceeded = currentTasks.every((t: TaskResponse) => t.status === 'succeeded')
  if (allSucceeded) {
    console.log('✅ All tasks succeeded - skipping auto-recovery')
    return
  }

  // Early exit if has running tasks
  const hasRunning = currentTasks.some((t: TaskResponse) => t.status === 'running')
  if (hasRunning) {
    console.log('⏳ Tasks are running - waiting for completion')
    return
  }

  // Early exit if no pending tasks
  const hasPending = currentTasks.some((t: TaskResponse) => t.status === 'pending')
  if (!hasPending) {
    console.log('⚠️ No pending tasks to start')
    return
  }

  console.log(`🔄 Starting all pending tasks in parallel...`)

  // Start ALL pending tasks at once (parallel execution)
  const pendingTasks = currentTasks.filter((t: TaskResponse) => t.status === 'pending')
  const startPromises = pendingTasks.map((task) => triggerTask(task.type))

  try {
    await Promise.all(startPromises)
    console.log(`✅ Started ${pendingTasks.length} tasks in parallel`)
  } catch (error) {
    console.error('❌ Error starting tasks in parallel:', error)
  }
}

// Debounced auto-recovery to prevent race conditions
const debouncedAutoRecovery = useDebounceFn(performAutoRecovery, 500)

// Watch for task updates and auto-progress
watch(
  tasks,
  (newTasks) => {
    // Perform debounced auto-recovery check on task changes
    debouncedAutoRecovery()
  },
  { deep: true },
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

// Task actions
const triggerTask = async (taskType: TaskType) => {
  try {
    // Find the existing task (pending or error)
    const existingTask = tasks.value?.find(
      (t: TaskResponse) => t.type === taskType && (t.status === 'pending' || t.status === 'error'),
    )

    if (existingTask) {
      console.log(`🔄 Starting task ${taskType} with ID:`, existingTask.id)
      await restart(existingTask.id)
      console.log(`✅ Task ${taskType} started successfully`)
    } else {
      console.warn(`⚠️ No pending or error task found for ${taskType}`)
    }
  } catch (error) {
    console.error(`❌ Error triggering task ${taskType}:`, error)
  }
}

const restartTask = async (taskType: TaskType) => {
  if (!canEditCompany.value) {
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

const startAllPendingTasks = async () => {
  if (!canEditCompany.value) {
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
