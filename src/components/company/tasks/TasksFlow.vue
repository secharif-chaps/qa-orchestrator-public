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
      <div class="h-96 w-full bg-bg1 border border-border-2 rounded-lg relative overflow-hidden">
        <VueFlow
          class="h-full"
          :nodes="flowNodes"
          :edges="flowEdges"
          :default-viewport="{ zoom: 0.9, x: -25, y: -0 }"
          @init="onFlowInit"
          :fit-view-on-init="false"
          :nodes-draggable="true"
          :zoom-on-scroll="false"
          :zoom-on-pinch="false"
          :pan-on-scroll="false"
        >
          <template #node-task="props">
            <TaskNode v-bind="props" @trigger="triggerTask" @restart="restartTask" />
          </template>

          <Background pattern="dots" :gap="16" :size="1" class="opacity-30" />

          <!-- Vue Flow Panel for controls -->
          <Panel position="top-right" class="p-2" v-if="completedCount < 8">
            <div
              class="bg-white dark:bg-slate-900 rounded-lg shadow-lg p-3 flex flex-col gap-2 min-w-[200px]"
            >
              <!-- <div class="text-sm font-medium text-gray-700 mb-1">Contrôles du workflow</div> -->

              <!-- Unified Status Progress Bar -->
              <div class="w-full">
                <!-- Segmented progress bar -->
                <div
                  class="w-full bg-gray-200 dark:bg-gray-800 rounded-full h-2 overflow-hidden flex"
                >
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
              </div>

              <!-- Control buttons -->
              <!-- <div class="flex flex-col gap-1">
                <OButton
                  type="secondary"
                  v-if="!workflowStarted || (!isWorkflowRunning && !isWorkflowComplete)"
                  @click="startWorkflow"
                  class=""
                >
                  <i class="fa fa-play mr-1"></i>
                  {{ workflowStarted ? 'Reprendre' : 'Démarrer le workflow' }}
                </OButton>
                
                <OButton
                  type="tertiary"
                  v-if="workflowStarted && (hasErrors || !isWorkflowRunning)"
                  @click="performAutoRecovery"
                  class="text-xs"
                >
                  <i class="fa fa-refresh mr-1"></i>
                  Auto-correction
                </OButton>
              </div> -->
            </div>
          </Panel>
        </VueFlow>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { VueFlow, useVueFlow, Panel, type Node, type Edge, MarkerType } from '@vue-flow/core'
import { Background } from '@vue-flow/background'
import type { TaskType, TaskStatus, TaskResponse } from '@/types/task'
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { companyTasksQuery } from '@/queries/tasks'
import TaskNode from './TaskNode.vue'
import { useQuery } from '@pinia/colada'
import { useRestartTask } from '@/mutations/tasks'
import { useDebounceFn } from '@vueuse/core'
import { onUnmounted } from 'vue'

interface TaskNodeData {
  type: TaskType
  name: string
  description: string
  status: TaskStatus | null
  error: string | null
  canTrigger: boolean
}

const route = useRoute()
const companyId = computed(() => route.params.companyId as string)

const isOpen = ref(false)

const { data: tasks, refetch: refetchTasks } = useQuery(companyTasksQuery, () => ({
  companyId: companyId.value,
}))

const isWorkflowPaused = ref(false)
const autoRecoveryLock = ref(false)
const pollingInterval = ref<NodeJS.Timeout | null>(null)
const { fitView } = useVueFlow()

// Define workflow configuration with dependencies - horizontal stepper layout
const workflowConfig = [
  // Phase 1: Parallel tasks (x=100, stacked vertically)
  {
    type: 'profile',
    name: 'Profil',
    description: 'Informations générales',
    dependencies: [],
    position: { x: 100, y: 50 },
    first: true,
  },
  {
    type: 'digital',
    name: 'Digital',
    description: 'Présence en ligne',
    dependencies: [],
    position: { x: 100, y: 130 },
    first: true,
  },
  {
    type: 'csr',
    name: 'RSE',
    description: 'Responsabilité sociale',
    dependencies: [],
    position: { x: 100, y: 210 },
    first: true,
  },
  {
    type: 'press',
    name: 'Presse',
    description: 'Articles de presse',
    dependencies: [],
    position: { x: 100, y: 290 },
    first: true,
  },

  // Phase 2: Timeline (x=300)
  {
    type: 'timeline',
    name: 'Timeline',
    description: 'Historique événements',
    dependencies: ['profile', 'digital', 'csr', 'press'],
    position: { x: 350, y: 170 },
  },

  // Phase 3: Products (x=500)
  {
    type: 'products',
    name: 'Produits',
    description: 'Catalogue produits',
    dependencies: ['timeline'],
    position: { x: 600, y: 170 },
  },

  // Phase 4: Team (x=700)
  {
    type: 'team',
    name: 'Équipe',
    description: 'Organigramme',
    dependencies: ['products'],
    position: { x: 850, y: 170 },
  },

  // Phase 5: Jobs (x=900)
  {
    type: 'jobs',
    name: 'Emplois',
    description: "Offres d'emploi",
    dependencies: ['team'],
    position: { x: 1100, y: 170 },
    last: true,
  },
]

const restartTaskMutation = useRestartTask()
const { mutate: restart } = restartTaskMutation

// Initialize component when mounted
onMounted(async () => {
  // Initialize previous task statuses to avoid unnecessary refreshes on mount
  const initialTasks = tasks.value || []

  initialTasks.forEach((task: TaskResponse) => {
    previousTaskStatuses.value.set(task.type, task.status)
  })

  // Perform auto-recovery check on mount (tasks already exist from backend)
  performAutoRecovery()

  setTimeout(() => {
    fitView({
      duration: 300,
    })
  }, 150)
})

// Watch for task updates and auto-progress (only if workflow was manually started)
const workflowStarted = ref(false)
const previousTaskStatuses = ref<Map<string, TaskStatus | null>>(new Map())

// This watcher will be defined after debouncedAutoRecovery is created

// Helper function to get task status
const getTaskStatus = (taskType: TaskType): TaskStatus | null => {
  // Fall back to server state
  const task = tasks.value?.find((t: TaskResponse) => t.type === taskType)
  return task?.status || null
}

// Helper function to get task error
const getTaskError = (taskType: TaskType): string | null => {
  const task = tasks.value?.find((t: TaskResponse) => t.type === taskType)
  return task?.error || null
}

// Check if task can be triggered (uses server state for logic decisions)
const canTriggerTask = (taskType: TaskType): boolean => {
  // Find the actual task (not just its status)
  const task = tasks.value?.find((t: TaskResponse) => t.type === taskType)

  // Task must exist and be in pending or error state to be triggered
  if (!task || (task.status !== 'pending' && task.status !== 'error')) {
    return false
  }

  const config = workflowConfig.find((c) => c.type === taskType)
  if (!config) {
    return false
  }

  // First 4 tasks (no dependencies) can always be triggered if pending/error
  if (config.dependencies.length === 0) {
    return true
  }

  // For dependent tasks, all dependencies must be succeeded (use actual task status for dependencies)
  const allDepsSucceeded = config.dependencies.every((depType) => {
    const depTask = tasks.value?.find((t) => t.type === depType)
    return depTask?.status === 'succeeded'
  })

  return allDepsSucceeded
}

// Create flow nodes
const flowNodes = computed<Node<TaskNodeData>[]>(() => {
  return workflowConfig.map((config) => ({
    id: config.type,
    type: 'task',
    position: config.position,
    data: {
      type: config.type as TaskType,
      name: config.name,
      description: config.description,
      status: getTaskStatus(config.type as TaskType),
      error: getTaskError(config.type as TaskType),
      canTrigger: canTriggerTask(config.type as TaskType),
      first: config.first,
      last: config.last,
    },
  }))
})

// Create flow edges with animations
const flowEdges = computed<Edge[]>(() => {
  const edges: Edge[] = []

  workflowConfig.forEach((config) => {
    config.dependencies.forEach((depType) => {
      const sourceStatus = getTaskStatus(depType as TaskType)
      const targetStatus = getTaskStatus(config.type as TaskType)

      edges.push({
        id: `${depType}-${config.type}`,
        source: depType,
        target: config.type,
        type: 'smoothstep',
        animated: sourceStatus === 'succeeded' && targetStatus === 'running',
        style: {
          stroke:
            sourceStatus === 'succeeded' ? 'var(--color-green-500)' : 'var(--color-slate-300)',
          strokeWidth: sourceStatus === 'succeeded' ? 3 : 2,
        },
        markerEnd: {
          type: 'arrowclosed' as MarkerType,
          color: sourceStatus === 'succeeded' ? 'var(--color-green-500)' : 'var(--color-slate-300)',
        },
      })
    })
  })

  return edges
})

// Computed stats
const completedCount = computed(
  () => tasks.value?.filter((t) => t.status === 'succeeded').length || 0,
)

watch(
  completedCount,
  (newCount) => {
    if (newCount < 8) {
      isOpen.value = true
    }
  },
  { immediate: true },
)

const runningCount = computed(
  () => tasks.value?.filter((t: TaskResponse) => t.status === 'running').length || 0,
)

const errorCount = computed(
  () => tasks.value?.filter((t: TaskResponse) => t.status === 'error').length || 0,
)

const pendingCount = computed(() => {
  const existingTasks = new Set(tasks.value?.map((t: TaskResponse) => t.type) || [])
  const totalConfigTasks = workflowConfig.length
  const pendingFromExisting = tasks.value?.filter((t) => t.status === 'pending').length || 0
  const notStartedTasks = totalConfigTasks - existingTasks.size
  return pendingFromExisting + notStartedTasks
})

const totalTasks = computed(() => workflowConfig.length)

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

// Auto-recovery system to unstuck workflows
const performAutoRecovery = async () => {
  if (autoRecoveryLock.value) {
    console.log('🔒 Auto-recovery already running, skipping...')
    return
  }

  autoRecoveryLock.value = true
  try {
    const currentTasks = tasks.value
    if (!currentTasks || currentTasks.length === 0) return

    // 🎯 EARLY EXIT 1: All tasks succeeded - nothing to do!
    const allSucceeded = currentTasks.every((t: TaskResponse) => t.status === 'succeeded')
    if (allSucceeded) {
      console.log('✅ All tasks succeeded - skipping auto-recovery')
      return
    }

    // 🎯 EARLY EXIT 2: Has running tasks - just wait for them to complete
    const hasRunning = currentTasks.some((t: TaskResponse) => t.status === 'running')
    if (hasRunning) {
      console.log('⏳ Tasks are running - waiting for completion')
      return
    }

    // 🎯 EARLY EXIT 3: Has errors but no pending - nothing to auto-start
    const hasPending = currentTasks.some((t: TaskResponse) => t.status === 'pending')
    if (!hasPending) {
      console.log('⚠️ No pending tasks to start')
      return
    }

    console.log(`🔄 Performing auto-recovery for pending tasks...`)

    // Check first 4 parallel tasks
    const firstFourTasks = ['profile', 'digital', 'csr', 'press'] as TaskType[]
    for (const taskType of firstFourTasks) {
      const task = currentTasks.find((t: TaskResponse) => t.type === taskType)
      if (task?.status === 'pending') {
        console.log(`🚀 Auto-starting: ${taskType}`)
        triggerTask(taskType)
        workflowStarted.value = true
        return // Start one at a time to avoid overwhelming the backend
      }
    }

    // Check dependent tasks in order
    const dependentTasks = ['timeline', 'products', 'team', 'jobs'] as TaskType[]
    for (const taskType of dependentTasks) {
      const task = currentTasks.find((t: TaskResponse) => t.type === taskType)
      if (task?.status === 'pending' && canTriggerTask(taskType)) {
        console.log(`🚀 Auto-starting dependent task: ${taskType}`)
        triggerTask(taskType)
        workflowStarted.value = true
        return // Start one at a time
      }
    }
  } finally {
    autoRecoveryLock.value = false
  }
}

// Debounced auto-recovery to prevent race conditions
const debouncedAutoRecovery = useDebounceFn(performAutoRecovery, 500)

// Watch for task updates and auto-progress
watch(
  tasks,
  (newTasks) => {
    // Check for newly succeeded tasks and refresh company data
    newTasks?.forEach((task: TaskResponse) => {
      const previousStatus = previousTaskStatuses.value.get(task.type)
      if (task.status === 'succeeded' && previousStatus !== 'succeeded') {
        // Refresh company data when a task succeeds
      }

      // Update the previous status
      previousTaskStatuses.value.set(task.type, task.status)
    })

    // Perform debounced auto-recovery check on task changes
    debouncedAutoRecovery()
  },
  { deep: true },
)

// Auto-progress workflow - DEPRECATED: Now handled in performAutoRecovery
const autoProgressWorkflow = () => {
  // This function is no longer needed as performAutoRecovery handles all progression
  console.warn('autoProgressWorkflow called but is deprecated')
}

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
      try {
        // Use restart endpoint for both pending and error tasks (never create new ones)
        console.log(`🔄 About to call restart mutation with ID:`, existingTask.id)
        await restart(existingTask.id)
        console.log(`✅ Task ${taskType} started successfully:`, existingTask)
      } catch (apiError) {
        throw apiError
      }
    } else {
      console.warn(`⚠️ No pending or error task found for ${taskType}`)
      // Log current task state for debugging
      const currentTask = tasks.value?.find((t) => t.type === taskType)
      if (currentTask) {
        console.warn(`📋 Current task state:`, {
          type: currentTask.type,
          status: currentTask.status,
          id: currentTask.id,
        })
      } else {
        console.warn(`📋 No task found at all for type: ${taskType}`)
      }
    }
  } catch (error) {
    console.error(`❌ Error triggering task ${taskType}:`, error)
  }
}

const restartTask = async (taskType: TaskType) => {
  try {
    const task = tasks.value?.find((t: TaskResponse) => t.type === taskType)
    if (task) {
      await restart(task.id)
    }
  } catch (error) {
    console.error('Error restarting task:', error)
  }
}

// Flow initialization
const onFlowInit = () => {
  // Keep the defined viewport without auto-fitting
}
</script>

<style scoped>
/* Custom flow styling */
:deep(.vue-flow__node) {
  cursor: pointer;
}

:deep(.vue-flow__edge-path) {
  transition: all 0.3s ease;
}

:deep(.vue-flow__edge.animated .vue-flow__edge-path) {
  stroke-dasharray: 5;
  animation: dash 1s linear infinite;
}

@keyframes dash {
  to {
    stroke-dashoffset: -10;
  }
}
</style>
