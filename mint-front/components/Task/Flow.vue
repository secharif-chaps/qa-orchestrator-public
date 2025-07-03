<template>
  <div class="bg-white rounded-lg overflow-hidden">


    <button
      class="w-full px-4 py-3 bg-white flex items-center justify-between text-left border-b border-gray-200 cursor-pointer"
      @click="isOpen = !isOpen"
      :class="{
        'border-b-0': !isOpen
      }"
    >
      <div class="flex items-center gap-3">
        <span class="font-medium">Workflow de recherche</span>
        <div class="flex items-center">
          <span class="text-xs text-gray-600 font-medium">{{ completedCount }}/{{ totalTasks }}</span>
        </div>
      </div>
      <i
        class="fa"
        :class="!isOpen ? 'fa-chevron-up' : 'fa-chevron-down'"
      ></i>
    </button>
    
    <div v-show="isOpen" class="p-6">
      <div class="h-96 w-full bg-gradient-to-br from-slate-50 to-slate-100 rounded-lg relative overflow-hidden">
        <VueFlow
          class="h-full"
          :nodes="flowNodes"
          :edges="flowEdges"
          :default-viewport="{ zoom: 1, x: -50, y: -20 }"
          @init="onFlowInit"
          :fit-view-on-init="true"
          :nodes-draggable="false"
          :zoom-on-scroll="false"
          :zoom-on-pinch="false"
          :pan-on-scroll="false"
        >
          <template #node-task="props">
            <NodesTaskNode v-bind="props" @trigger="triggerTask" @restart="restartTask" />
          </template>
          
          <Background 
            :pattern="BackgroundVariant.Dots" 
            :gap="16" 
            :size="1" 
            class="opacity-30"
          />
          
          <!-- Vue Flow Panel for controls -->
          <Panel position="top-right" class="p-2">
            <div class="bg-white rounded-lg shadow-lg p-3 flex flex-col gap-2 min-w-[200px]">
              <!-- <div class="text-sm font-medium text-gray-700 mb-1">Contrôles du workflow</div> -->
              
              <!-- Unified Status Progress Bar -->
              <div class="w-full">
                
                <!-- Segmented progress bar -->
                <div class="w-full bg-gray-200 rounded-full h-2 overflow-hidden flex">
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
                    class="bg-gray-200 h-full transition-all duration-500 ease-out"
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
import { VueFlow, useVueFlow, Panel, type Node, type Edge } from '@vue-flow/core'
import { Background, BackgroundVariant } from '@vue-flow/background'
import type { TaskType, TaskStatus, TaskCreate, TaskResponse } from '~/types/task'
import { OButton } from '@owlint/feathers-vue'

interface TaskNodeData {
  type: TaskType
  name: string
  description: string
  status: TaskStatus | null
  error: string | null
  canTrigger: boolean
}

interface Props {
  companyId: number
}



const props = defineProps<Props>()
const isOpen = ref(true)
const taskStore = useTaskStore()
const isWorkflowPaused = ref(false)
const { fitView } = useVueFlow()
const { fetchCompany, company } = useCompanyData()

// Define workflow configuration with dependencies - horizontal stepper layout
const workflowConfig = [
  // Phase 1: Parallel tasks (x=100, stacked vertically)
  { type: 'profile', name: 'Profil', description: 'Informations générales', dependencies: [], position: { x: 100, y: 50 }, first: true },
  { type: 'digital', name: 'Digital', description: 'Présence en ligne', dependencies: [], position: { x: 100, y: 130 }, first: true },
  { type: 'csr', name: 'RSE', description: 'Responsabilité sociale', dependencies: [], position: { x: 100, y: 210 }, first: true },
  { type: 'press', name: 'Presse', description: 'Articles de presse', dependencies: [], position: { x: 100, y: 290 }, first: true },
  
  // Phase 2: Timeline (x=300)
  { type: 'timeline', name: 'Timeline', description: 'Historique événements', dependencies: ['profile', 'digital', 'csr', 'press'], position: { x: 350, y: 170 } },
  
  // Phase 3: Products (x=500)
  { type: 'products', name: 'Produits', description: 'Catalogue produits', dependencies: ['timeline'], position: { x: 600, y: 170 } },
  
  // Phase 4: Team (x=700)
  { type: 'team', name: 'Équipe', description: 'Organigramme', dependencies: ['products'], position: { x: 850, y: 170 } },
  
  // Phase 5: Jobs (x=900)
  { type: 'jobs', name: 'Emplois', description: 'Offres d\'emploi', dependencies: ['team'], position: { x: 1100, y: 170 }, last: true }
]

// Fetch tasks when component is mounted
onMounted(async () => {
  
  await taskStore.fetchCompanyTasks(props.companyId)
  
  // Initialize previous task statuses to avoid unnecessary refreshes on mount
  const initialTasks = taskStore.getCompanyTasks(props.companyId)
  
  initialTasks.forEach((task: TaskResponse) => {
    previousTaskStatuses.value.set(task.type, task.status)
  })
  
  // Perform auto-recovery check on mount
  performAutoRecovery()
})

// Watch for company ID changes
watch(() => props.companyId, async (newId) => {
  
  await taskStore.fetchCompanyTasks(newId)
  
  // Reset previous task statuses for new company
  previousTaskStatuses.value.clear()
  const newTasks = taskStore.getCompanyTasks(newId)
  
  newTasks.forEach((task: TaskResponse) => {
    previousTaskStatuses.value.set(task.type, task.status)
  })
  
  // Perform auto-recovery for new company
  performAutoRecovery()
})

// Get tasks from store
const tasks = computed(() => taskStore.getCompanyTasks(props.companyId))

// Watch for task updates and auto-progress (only if workflow was manually started)
const workflowStarted = ref(false)
const previousTaskStatuses = ref<Map<string, TaskStatus | null>>(new Map())

watch(tasks, (newTasks, oldTasks) => {
  
  
  // Check for newly succeeded tasks and refresh company data
  newTasks.forEach((task: TaskResponse) => {
    const previousStatus = previousTaskStatuses.value.get(task.type)
    if (task.status === 'succeeded' && previousStatus !== 'succeeded') {
      // Refresh company data when a task succeeds
      fetchCompany()
    }
    // Update the previous status
    previousTaskStatuses.value.set(task.type, task.status)
  })

  // Perform auto-recovery check on task changes
  performAutoRecovery()
}, { deep: true })

// Helper function to get task status
const getTaskStatus = (taskType: TaskType): TaskStatus | null => {
  const task = tasks.value.find((t: TaskResponse) => t.type === taskType)
  return task?.status || null
}

// Helper function to get task error
const getTaskError = (taskType: TaskType): string | null => {
  const task = tasks.value.find((t: TaskResponse) => t.type === taskType)
  return task?.error || null
}

// Check if task can be triggered
const canTriggerTask = (taskType: TaskType): boolean => {
  const status = getTaskStatus(taskType)
  
  if (status === 'running' || status === 'succeeded') {
    return false
  }
  
  const config = workflowConfig.find(c => c.type === taskType)
  if (!config) {
    return false
  }
  
  // First 4 tasks (no dependencies) can always be triggered if not running/succeeded
  if (config.dependencies.length === 0) {
    return true
  }
  
  // For other tasks, check if all dependencies are succeeded
  const dependencyStatuses = config.dependencies.map(depType => ({
    type: depType,
    status: getTaskStatus(depType as TaskType)
  }))
  
  const allDepsSucceeded = config.dependencies.every(depType => 
    getTaskStatus(depType as TaskType) === 'succeeded'
  )

  
  return allDepsSucceeded
}

// Create flow nodes
const flowNodes = computed<Node<TaskNodeData>[]>(() => {
  return workflowConfig.map(config => ({
    id: config.type,
    type: 'task',
    position: config.position,
    data: {
      type: config.type,
      name: config.name,
      description: config.description,
      status: getTaskStatus(config.type),
      error: getTaskError(config.type),
      canTrigger: canTriggerTask(config.type),
      first: config.first,
      last: config.last
    }
  }))
})

// Create flow edges with animations
const flowEdges = computed<Edge[]>(() => {
  const edges: Edge[] = []
  
  workflowConfig.forEach(config => {
    config.dependencies.forEach(depType => {
      const sourceStatus = getTaskStatus(depType as TaskType)
      const targetStatus = getTaskStatus(config.type)
      
      edges.push({
        id: `${depType}-${config.type}`,
        source: depType,
        target: config.type,
        type: 'smoothstep',
        animated: sourceStatus === 'succeeded' && targetStatus === 'running',
        style: {
          stroke: sourceStatus === 'succeeded' ? 'var(--color-green-500)' : 'var(--color-slate-300)',
          strokeWidth: sourceStatus === 'succeeded' ? 3 : 2,
        },
        markerEnd: {
          type: 'arrowclosed',
          color: sourceStatus === 'succeeded' ? 'var(--color-green-500)' : 'var(--color-slate-300)',
        }
      })
    })
  })
  
  return edges
})

// Computed stats
const completedCount = computed(() => 
  tasks.value.filter(t => t.status === 'succeeded').length
)

const runningCount = computed(() => 
  tasks.value.filter(t => t.status === 'running').length
)

const errorCount = computed(() => 
  tasks.value.filter(t => t.status === 'error').length
)

const pendingCount = computed(() => {
  const existingTasks = new Set(tasks.value.map(t => t.type))
  const totalConfigTasks = workflowConfig.length
  const pendingFromExisting = tasks.value.filter(t => t.status === 'pending').length
  const notStartedTasks = totalConfigTasks - existingTasks.size
  return pendingFromExisting + notStartedTasks
})

const totalTasks = computed(() => workflowConfig.length)

const progressPercentage = computed(() => 
  totalTasks.value > 0 ? (completedCount.value / totalTasks.value) * 100 : 0
)

// Percentage calculations for segmented progress bar
const completedPercentage = computed(() => 
  totalTasks.value > 0 ? (completedCount.value / totalTasks.value) * 100 : 0
)

const runningPercentage = computed(() => 
  totalTasks.value > 0 ? (runningCount.value / totalTasks.value) * 100 : 0
)

const errorPercentage = computed(() => 
  totalTasks.value > 0 ? (errorCount.value / totalTasks.value) * 100 : 0
)

const pendingPercentage = computed(() => 
  totalTasks.value > 0 ? (pendingCount.value / totalTasks.value) * 100 : 0
)

const isWorkflowRunning = computed(() => 
  tasks.value.some(t => t.status === 'running')
)

const isWorkflowComplete = computed(() => 
  workflowConfig.every(config => {
    const status = getTaskStatus(config.type)
    return status === 'succeeded' || status === 'error'
  })
)

const hasErrors = computed(() => 
  tasks.value.some(t => t.status === 'error')
)

// Auto-recovery system to unstuck workflows
const performAutoRecovery = () => {
  console.log(`🔄 Performing auto-recovery check for company ${props.companyId}...`)
  
  const currentTasks = tasks.value
  const hasAnyTask = currentTasks.length > 0
  
  console.log(`📊 Current tasks state:`, {
    totalTasks: currentTasks.length,
    tasks: currentTasks.map(t => ({ type: t.type, status: t.status, id: t.id })),
    workflowStarted: workflowStarted.value,
    isWorkflowPaused: isWorkflowPaused.value
  })
  
  // Auto-start first 4 tasks if they are pending (since backend now creates all tasks automatically)
  const firstFourTasks = ['profile', 'digital', 'csr', 'press'] as TaskType[]
  console.log(`🎯 Checking first 4 tasks for auto-start...`)
  
  firstFourTasks.forEach(taskType => {
    const existingTask = currentTasks.find(t => t.type === taskType)
    
    console.log(`📋 Task ${taskType}:`, {
      exists: !!existingTask,
      status: existingTask?.status || 'not found',
      canTrigger: existingTask ? canTriggerTask(taskType) : false
    })
    
    if (existingTask && existingTask.status === 'pending' && canTriggerTask(taskType)) {
      console.log(`🚀 Auto-starting pending task: ${taskType}`)
      triggerTask(taskType)
      workflowStarted.value = true
    }
  })
  
  // If we have any task, assume workflow was started at some point
  if (hasAnyTask && !workflowStarted.value) {
    console.log('📝 Detected existing tasks - marking workflow as started')
    workflowStarted.value = true
  }
  
  // Check for stuck tasks (log only, no automatic restart)
  const stuckTasks = taskStore.getStuckTasks(props.companyId, 45) // 45 minutes timeout
  if (stuckTasks.length > 0) {
    console.log(`🔍 Found ${stuckTasks.length} stuck tasks`)
  }
  
  stuckTasks.forEach((task: TaskResponse) => {
    // Adjust for 2-hour timezone difference: subtract 120 minutes from server time
    const adjustedServerTime = new Date(new Date(task.updated_at).getTime() + (120 * 60 * 1000))
    const taskAge = Math.round((Date.now() - adjustedServerTime.getTime()) / 60000)
    console.warn(`⚠️ Detected stuck task: ${task.type} (running for ${taskAge} minutes, timezone-adjusted) - manual intervention may be needed`)
    // Note: Automatic restart disabled to prevent timezone issues
  })
  
  // Auto-progress workflow if it was started
  if (workflowStarted.value && !isWorkflowPaused.value) {
    console.log(`⏭️ Workflow started and not paused, checking auto-progress...`)
    autoProgressWorkflow()
  } else {
    console.log(`⏸️ Auto-progress skipped:`, {
      workflowStarted: workflowStarted.value,
      isWorkflowPaused: isWorkflowPaused.value
    })
  }
}

// Auto-progress workflow
const autoProgressWorkflow = () => {
  if (isWorkflowPaused.value) return
  
  // Find next available tasks to trigger
  for (const config of workflowConfig) {
    const currentStatus = getTaskStatus(config.type)
    if (canTriggerTask(config.type) && (currentStatus === 'pending')) {
      triggerTask(config.type)
      break // Only trigger one at a time for sequential flow
    }
  }
}

// Task actions
const triggerTask = async (taskType: TaskType) => {
  try {
    console.log(`🎬 Triggering task ${taskType} for company ${props.companyId}`)
    
    // Find the existing pending task
    const existingTask = tasks.value.find(t => t.type === taskType && t.status === 'pending')
    
    if (existingTask) {
      console.log(`📤 Starting existing pending task:`, existingTask)
      // Use the existing create_and_start_task method from the backend
      const result = await taskStore.createTask({
        type: taskType,
        status: 'pending',
        company_id: props.companyId
      })
      console.log(`✅ Task ${taskType} started successfully:`, result)
    } else {
      console.warn(`⚠️ No pending task found for ${taskType}`)
    }
    
  } catch (error) {
    console.error(`❌ Error triggering task ${taskType}:`, error)
  }
}

const restartTask = async (taskType: TaskType) => {
  try {
    const task = tasks.value.find((t: TaskResponse) => t.type === taskType)
    if (task) {
      await taskStore.restartTask(task.id)
    }
  } catch (error) {
    console.error('Error restarting task:', error)
  }
}

// Workflow controls
const startWorkflow = () => {
  workflowStarted.value = true
  isWorkflowPaused.value = false
  // Start with first available tasks (profile, digital, csr, press can start immediately)
  const firstTasks = workflowConfig.filter(config => config.dependencies.length === 0)
  firstTasks.forEach(config => {
    if (!getTaskStatus(config.type)) {
      triggerTask(config.type)
    }
  })
}

const pauseWorkflow = () => {
  isWorkflowPaused.value = true
}

const retryFailedTasks = () => {
  tasks.value
    .filter(t => t.status === 'error')
    .forEach(t => restartTask(t.type))
}

const resetWorkflow = () => {
  workflowStarted.value = false
  isWorkflowPaused.value = false
  // Clear all tasks from the store (this will refresh the UI)
  taskStore.clearCompanyTasks(props.companyId)
}

// Flow initialization
const onFlowInit = () => {
  // Keep the defined viewport without auto-fitting
}

// Clean up when component is unmounted
onUnmounted(() => {
  taskStore.clearCompanyTasks(props.companyId)
})
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