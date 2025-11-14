<template>
  <Teleport to="body">
    <Transition name="modal">
      <div
        v-if="modelValue"
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm"
        @click.self="emit('update:modelValue', false)"
      >
        <div
          class="bg-base-100 rounded-card border border-primary-stroke shadow-shadow-3 w-full max-w-4xl max-h-[90vh] overflow-hidden flex flex-col"
        >
          <!-- Header -->
          <div class="flex items-center justify-between p-6 border-b border-primary-stroke">
            <div class="flex items-center gap-3">
              <div class="w-10 h-10 rounded-full bg-primary/10 flex items-center justify-center">
                <i class="fas fa-bug text-secondary"></i>
              </div>
              <div>
                <h2 class="text-lg font-semibold">Workflow de recherche</h2>
                <p class="text-sm text-secondary">
                  {{ completedCount }}/{{ totalTasks }} tâches terminées
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
              <div class="w-full bg-base-200 rounded-full h-3 overflow-hidden flex">
                <!-- Completed segment -->
                <div
                  v-if="completedPercentage > 0"
                  class="bg-success-500 h-full transition-all duration-500 ease-out"
                  :style="{ width: `${completedPercentage}%` }"
                  :title="`${completedCount} tâches terminées (${Math.round(completedPercentage)}%)`"
                ></div>

                <!-- Running segment -->
                <div
                  v-if="runningPercentage > 0"
                  class="bg-warning-500 h-full transition-all duration-500 ease-out"
                  :style="{ width: `${runningPercentage}%` }"
                  :title="`${runningCount} tâches en cours (${Math.round(runningPercentage)}%)`"
                ></div>

                <!-- Error segment -->
                <div
                  v-if="errorPercentage > 0"
                  class="bg-error-500 h-full transition-all duration-500 ease-out"
                  :style="{ width: `${errorPercentage}%` }"
                  :title="`${errorCount} tâches en erreur (${Math.round(errorPercentage)}%)`"
                ></div>

                <!-- Blocked segment -->
                <div
                  v-if="blockedPercentage > 0"
                  class="bg-slate-500 h-full transition-all duration-500 ease-out"
                  :style="{ width: `${blockedPercentage}%` }"
                  :title="`${blockedCount} tâches bloquées (${Math.round(blockedPercentage)}%)`"
                ></div>

                <!-- Pending segment -->
                <div
                  v-if="pendingPercentage > 0"
                  class="bg-base-200 h-full transition-all duration-500 ease-out"
                  :style="{ width: `${pendingPercentage}%` }"
                  :title="`${pendingCount} tâches en attente (${Math.round(pendingPercentage)}%)`"
                ></div>
              </div>

              <!-- Status summary -->
              <div class="flex items-center justify-between mt-3 text-xs text-secondary">
                <div class="flex items-center gap-4">
                  <span class="flex items-center gap-1.5">
                    <div class="w-2 h-2 bg-success-500 rounded-full"></div>
                    {{ completedCount }} terminées
                  </span>
                  <span v-if="runningCount > 0" class="flex items-center gap-1.5">
                    <div class="w-2 h-2 bg-warning-500 rounded-full"></div>
                    {{ runningCount }} en cours
                  </span>
                  <span v-if="errorCount > 0" class="flex items-center gap-1.5">
                    <div class="w-2 h-2 bg-error-500 rounded-full"></div>
                    {{ errorCount }} en erreur
                  </span>
                  <span v-if="pendingCount > 0" class="flex items-center gap-1.5">
                    <div class="w-2 h-2 bg-secondary rounded-full"></div>
                    {{ pendingCount }} en attente
                  </span>
                  <span v-if="blockedCount > 0" class="flex items-center gap-1.5">
                    <div class="w-2 h-2 bg-slate-500 rounded-full"></div>
                    {{ blockedCount }} bloquées
                  </span>
                </div>
              </div>
            </div>

            <!-- Task List -->
            <div class="space-y-3">
              <div
                v-for="task in taskList"
                :key="task.type"
                class="flex items-center justify-between p-4 rounded-card border transition-all duration-300"
                :class="getTaskClass(task)"
              >
                <!-- Task Info -->
                <div class="flex items-center gap-3">
                  <div
                    class="w-12 h-12 rounded-full flex items-center justify-center flex-shrink-0"
                    :class="getIconContainerClass(task.status)"
                  >
                    <i v-if="task.status === 'running'" class="fas fa-spinner-third fa-spin"></i>
                    <i v-else :class="getTaskIcon(task.type)"></i>
                  </div>

                  <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-2 mb-1">
                      <h3 class="font-semibold text-sm">{{ task.name }}</h3>
                      <Tag
                        :variant="getStatusVariant(task.status)"
                        :label="getStatusLabel(task.status)"
                        size="xs"
                      />
                    </div>
                    <p class="text-xs text-secondary truncate">
                      {{ task.description }}
                    </p>

                    <!-- Token information for admins -->
                    <div
                      v-if="hasAdminAccess && getTokenInfo(task.type)?.hasTokenData"
                      class="mt-2 flex items-center gap-3 text-xs text-secondary"
                    >
                      <span v-if="getTokenInfo(task.type)?.inputTokens">
                        <i class="fas fa-arrow-down text-info-500"></i>
                        {{ formatTokens(getTokenInfo(task.type)?.inputTokens) }}
                      </span>
                      <span v-if="getTokenInfo(task.type)?.outputTokens">
                        <i class="fas fa-arrow-up text-success-500"></i>
                        {{ formatTokens(getTokenInfo(task.type)?.outputTokens) }}
                      </span>
                      <span v-if="getTokenInfo(task.type)?.totalCost" class="font-medium">
                        <i class="fas fa-coins text-warning-500"></i>
                        {{ formatCost(getTokenInfo(task.type)?.totalCost) }}
                      </span>
                    </div>

                    <!-- Error message -->
                    <div v-if="task.error && task.status === 'error'" class="mt-2">
                      <span class="text-xs text-error-500">{{ task.error }}</span>
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
            <div v-if="hasErrorsOrPending" class="mt-6 pt-6 border-t border-primary-stroke">
              <div class="flex items-center justify-between">
                <div class="text-sm text-secondary">
                  Des tâches peuvent être redémarrées ou ne sont pas encore lancées
                </div>
                <Button
                  variant="secondary"
                  size="sm"
                  icon="fa fa-play"
                  label="Démarrer toutes les tâches"
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
import type { TaskType, TaskStatus, TaskResponse } from '@/types/task'
import { computed, ref, watch, onUnmounted } from 'vue'
import { useRoute } from 'vue-router'
import { companyTasksQuery } from '@/queries/tasks'
import { useQuery } from '@pinia/colada'
import { useRestartTask } from '@/mutations/tasks'
import { useCompanyPermissions } from '@/composables/useCompanyPermissions'
import { useAuthStore } from '@/stores/auth'
import Button from '@/components/ui/Button.vue'
import Tag from '@/components/ui/Tag.vue'

interface TaskConfig {
  type: TaskType
  name: string
  description: string
}

interface Props {
  modelValue: boolean
}

const props = defineProps<Props>()

const emit = defineEmits<{
  'update:modelValue': [value: boolean]
}>()

const route = useRoute()
const companyId = computed(() => route.params.companyId as string)

const isRestarting = ref<TaskType | null>(null)
const isStartingAll = ref(false)

const { data: tasks, refetch: refetchTasks } = useQuery(companyTasksQuery, () => ({
  companyId: companyId.value,
}))

const pollingInterval = ref<NodeJS.Timeout | null>(null)
const { canCreateCompany } = useCompanyPermissions()
const authStore = useAuthStore()

// Check if user has admin permissions to view token data
const hasAdminAccess = computed(() =>
  authStore.hasAnyRole(['admin.organizations', 'admin.users', 'admin.all']),
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
  {
    type: 'data_collection',
    name: 'Collecte de données',
    description: 'Collecte de données structurées',
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
    case 'blocked':
      return 'slate'
    default:
      return 'accent'
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
    case 'blocked':
      return 'En attente (bloquée)'
    default:
      return 'Non démarrée'
  }
}

// Check if current user is a debug user
const isDebugUser = computed(() => {
  const username = authStore.user?.profile?.preferred_username?.toLowerCase()
  return username === 'nmr' || username === 'suh'
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

// Polling logic for running tasks
const hasRunningTasks = computed(() => tasks.value?.some((t) => t.status === 'running') || false)

const startPolling = () => {
  if (pollingInterval.value) return // Already polling

  pollingInterval.value = setInterval(() => {
    if (hasRunningTasks.value) {
      refetchTasks()
    } else {
      stopPolling()
    }
  }, 10000) // Poll every 10 seconds
}

const stopPolling = () => {
  if (pollingInterval.value) {
    clearInterval(pollingInterval.value)
    pollingInterval.value = null
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

const blockedPercentage = computed(() =>
  totalTasks.value > 0 ? (blockedCount.value / totalTasks.value) * 100 : 0,
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
