<template>
  <div class="bg-white rounded-lg overflow-hidden">
    <button
      class="w-full px-4 py-3 bg-white flex items-center justify-between text-left border-b border-gray-200 cursor-pointer"
      @click="isOpen = !isOpen"
      :class="{
        'border-b-0': !isOpen
      }"
    >
      <span class="font-medium">Workflow de recherche</span>
      <i
        class="fa"
        :class="!isOpen ? 'fa-chevron-up' : 'fa-chevron-down'"
      ></i>
    </button>
    
    <div v-show="isOpen" class="p-6">
      <ul class="steps steps-vertical w-full">
        <li 
          v-for="(step, index) in workflowSteps" 
          :key="step.id"
          class="step"
          :class="getStepClass(step)"
        >
          <div class="flex flex-col items-start w-full pl-4">
            <div class="flex items-center justify-between w-full mb-2">
              <div class="flex items-center gap-3">
                <div 
                  class="flex items-center justify-center w-8 h-8 rounded-full"
                  :class="getIconContainerClass(step)"
                >
                  <i 
                    :class="getTaskIcon(step.type)"
                    class="text-sm"
                  ></i>
                  <i 
                    v-if="step.status === 'running'"
                    :class="getTaskIcon(step.type)"
                    class="text-sm absolute animate-ping"
                  ></i>
                </div>
                <div>
                  <h3 class="font-medium capitalize">{{ step.name }}</h3>
                  <p class="text-sm text-gray-500">{{ step.description }}</p>
                </div>
              </div>
              
              <div class="flex items-center gap-2">
                <span 
                  v-if="step.status"
                  class="px-2 py-1 text-xs rounded-full font-medium"
                  :class="getStatusBadgeClass(step.status)"
                >
                  {{ getStatusText(step.status) }}
                </span>
                
                <button
                  v-if="canTriggerStep(step, index)"
                  @click="triggerStep(step)"
                  class="btn btn-sm btn-primary"
                >
                  <i class="fa fa-play mr-1"></i>
                  Lancer
                </button>
                
                <button
                  v-if="step.status === 'error' || step.status === 'succeeded'"
                  @click="restartStep(step)"
                  class="btn btn-sm btn-ghost"
                >
                  <i class="fa fa-refresh mr-1"></i>
                  Relancer
                </button>
              </div>
            </div>
            
            <!-- Error message -->
            <div v-if="step.status === 'error' && step.error" class="w-full mt-2">
              <div class="alert alert-error">
                <i class="fa fa-exclamation-triangle"></i>
                <span class="text-sm">{{ step.error }}</span>
              </div>
            </div>
            
            <!-- Progress indicator for running tasks -->
            <div v-if="step.status === 'running'" class="w-full mt-2">
              <progress class="progress progress-primary w-full"></progress>
            </div>
          </div>
        </li>
      </ul>
      
      <!-- Global controls -->
      <div class="flex justify-between items-center mt-6 pt-4 border-t">
        <div class="text-sm text-gray-600">
          {{ completedSteps }}/{{ workflowSteps.length }} tâches terminées
        </div>
        <div class="flex gap-2">
          <button
            v-if="!isWorkflowRunning && !isWorkflowComplete"
            @click="startWorkflow"
            class="btn btn-primary"
          >
            <i class="fa fa-play mr-2"></i>
            Démarrer le workflow
          </button>
          <button
            v-if="isWorkflowRunning"
            @click="pauseWorkflow"
            class="btn btn-warning"
          >
            <i class="fa fa-pause mr-2"></i>
            Pause
          </button>
          <button
            v-if="hasErrors"
            @click="retryFailedSteps"
            class="btn btn-error"
          >
            <i class="fa fa-refresh mr-2"></i>
            Relancer les erreurs
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import type { TaskType, TaskStatus, TaskCreate, TaskResponse } from '~/types/task'

interface WorkflowStep {
  id: string
  type: TaskType
  name: string
  description: string
  status: TaskStatus | null
  error: string | null
  dependencies: string[]
}

interface Props {
  companyId: number
}

const props = defineProps<Props>()
const isOpen = ref(false)
const taskStore = useTaskStore()
const isWorkflowPaused = ref(false)

// Define workflow steps in order
const workflowSteps = ref<WorkflowStep[]>([
  {
    id: 'profile',
    type: 'profile',
    name: 'Profil',
    description: 'Informations générales de l\'entreprise',
    status: null,
    error: null,
    dependencies: []
  },
  {
    id: 'digital',
    type: 'digital',
    name: 'Présence digitale',
    description: 'Analyse de la présence en ligne',
    status: null,
    error: null,
    dependencies: []
  },
  {
    id: 'csr',
    type: 'csr',
    name: 'RSE',
    description: 'Responsabilité sociale et environnementale',
    status: null,
    error: null,
    dependencies: []
  },
  {
    id: 'press',
    type: 'press',
    name: 'Presse',
    description: 'Articles et mentions presse',
    status: null,
    error: null,
    dependencies: []
  },
  {
    id: 'timeline',
    type: 'timeline',
    name: 'Timeline',
    description: 'Historique et événements clés',
    status: null,
    error: null,
    dependencies: ['profile', 'digital', 'csr', 'press']
  },
  {
    id: 'products',
    type: 'products',
    name: 'Produits',
    description: 'Catalogue et offres produits',
    status: null,
    error: null,
    dependencies: ['timeline']
  },
  {
    id: 'team',
    type: 'team',
    name: 'Équipe',
    description: 'Organigramme et dirigeants',
    status: null,
    error: null,
    dependencies: ['products']
  },
  {
    id: 'jobs',
    type: 'jobs',
    name: 'Emplois',
    description: 'Offres d\'emploi et opportunités',
    status: null,
    error: null,
    dependencies: ['team']
  }
])

// Fetch tasks when component is mounted
onMounted(async () => {
  await taskStore.fetchCompanyTasks(props.companyId)
  updateStepStatuses()
})

// Watch for company ID changes
watch(() => props.companyId, async (newId) => {
  await taskStore.fetchCompanyTasks(newId)
  updateStepStatuses()
})

// Watch for task updates
const tasks = computed(() => taskStore.getCompanyTasks(props.companyId))
watch(tasks, () => {
  updateStepStatuses()
  // Auto-trigger next steps when dependencies are completed
  if (!isWorkflowPaused.value) {
    autoProgressWorkflow()
  }
}, { deep: true })

// Update step statuses from store
const updateStepStatuses = () => {
  workflowSteps.value.forEach(step => {
    const task = tasks.value.find((t: TaskResponse) => t.type === step.type)
    step.status = task?.status || null
    step.error = task?.error || null
  })
}

// Computed properties
const completedSteps = computed(() => {
  return workflowSteps.value.filter(step => 
    step.status === 'succeeded' || step.status === 'error'
  ).length
})

const isWorkflowRunning = computed(() => {
  return workflowSteps.value.some(step => step.status === 'running')
})

const isWorkflowComplete = computed(() => {
  return workflowSteps.value.every(step => 
    step.status === 'succeeded' || step.status === 'error'
  )
})

const hasErrors = computed(() => {
  return workflowSteps.value.some(step => step.status === 'error')
})

// Icon mapping for different task types
const getTaskIcon = (taskType: TaskType): string => {
  const iconMap: Record<TaskType, string> = {
    profile: 'fas fa-user',
    digital: 'fas fa-globe',
    timeline: 'fas fa-history',
    products: 'fas fa-box',
    jobs: 'fas fa-briefcase',
    csr: 'fas fa-leaf',
    press: 'fas fa-newspaper',
    team: 'fas fa-users'
  }
  return iconMap[taskType] || 'fas fa-question'
}

// Step styling
const getStepClass = (step: WorkflowStep): string => {
  if (step.status === 'succeeded') return 'step-primary'
  if (step.status === 'error') return 'step-error'
  if (step.status === 'running') return 'step-warning'
  return ''
}

const getIconContainerClass = (step: WorkflowStep): string => {
  const baseClasses = 'relative'
  if (step.status === 'succeeded') return `${baseClasses} bg-green-400 text-white`
  if (step.status === 'error') return `${baseClasses} bg-red-400 text-white`
  if (step.status === 'running') return `${baseClasses} bg-orange-400 text-white`
  return `${baseClasses} bg-slate-100 text-slate-400`
}

const getStatusBadgeClass = (status: TaskStatus): string => {
  switch (status) {
    case 'running':
      return 'bg-orange-100 text-orange-800'
    case 'succeeded':
      return 'bg-green-100 text-green-800'
    case 'error':
      return 'bg-red-100 text-red-800'
    case 'pending':
      return 'bg-gray-100 text-gray-800'
    default:
      return 'bg-gray-100 text-gray-800'
  }
}

const getStatusText = (status: TaskStatus): string => {
  switch (status) {
    case 'running':
      return 'En cours'
    case 'succeeded':
      return 'Terminé'
    case 'error':
      return 'Erreur'
    case 'pending':
      return 'En attente'
    default:
      return 'Non démarré'
  }
}

// Check if a step can be triggered
const canTriggerStep = (step: WorkflowStep, index: number): boolean => {
  // Can't trigger if already running or succeeded
  if (step.status === 'running' || step.status === 'succeeded') return false
  
  // Check if dependencies are met
  return step.dependencies.every(depId => {
    const depStep = workflowSteps.value.find(s => s.id === depId)
    return depStep?.status === 'succeeded'
  })
}

// Auto-progress workflow
const autoProgressWorkflow = () => {
  if (isWorkflowPaused.value) return
  
  // Find next available step to trigger
  for (const [index, step] of workflowSteps.value.entries()) {
    if (canTriggerStep(step, index) && !step.status) {
      triggerStep(step)
      break // Only trigger one step at a time
    }
  }
}

// Step actions
const triggerStep = async (step: WorkflowStep) => {
  try {
    const task: TaskCreate = {
      type: step.type,
      status: 'pending',
      company_id: props.companyId
    }
    await taskStore.createTask(task)
  } catch (error) {
    console.error('Error triggering step:', error)
  }
}

const restartStep = async (step: WorkflowStep) => {
  try {
    const task = tasks.value.find((t: TaskResponse) => t.type === step.type)
    if (task) {
      await taskStore.restartTask(task.id)
    }
  } catch (error) {
    console.error('Error restarting step:', error)
  }
}

// Workflow controls
const startWorkflow = () => {
  isWorkflowPaused.value = false
  // Start with first available step
  const firstStep = workflowSteps.value.find(step => canTriggerStep(step, 0))
  if (firstStep) {
    triggerStep(firstStep)
  }
}

const pauseWorkflow = () => {
  isWorkflowPaused.value = true
}

const retryFailedSteps = () => {
  workflowSteps.value
    .filter(step => step.status === 'error')
    .forEach(step => restartStep(step))
}

// Clean up when component is unmounted
onUnmounted(() => {
  taskStore.clearCompanyTasks(props.companyId)
})
</script>