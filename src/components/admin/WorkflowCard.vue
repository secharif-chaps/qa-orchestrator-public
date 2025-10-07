<template>
  <!-- Backdrop Overlay (when zoomed) -->
  <Teleport to="body">
    <div
      v-if="isAnimating || isZoomed"
      :class="[
        'fixed inset-0 z-40 transition-all duration-300 ease-out',
        isZoomed && !isExiting
          ? 'bg-black/20 backdrop-blur-sm opacity-100'
          : 'bg-transparent backdrop-blur-none opacity-0',
      ]"
      @click="exitZoomMode"
    />
  </Teleport>

  <!-- Floating Card (when zoomed) -->
  <Teleport to="body">
    <div
      v-if="isAnimating || isZoomed"
      ref="floatingCardRef"
      class="bg-base-100 rounded-lg shadow-2xl border border-primary-stroke fixed z-50"
    >
      <div class="p-6">
        <!-- Header -->
        <div class="flex items-center justify-between mb-4">
          <div class="flex items-center">
            <!-- Icon -->
            <div
              :class="[
                'w-12 h-12 rounded-lg flex items-center justify-center transition-colors',
                statusConfig.iconBg,
              ]"
            >
              <i :class="[iconClass, 'text-xl', statusConfig.iconColor]"></i>
            </div>
            <div class="ml-4">
              <h3 class="text-lg font-semibold">{{ workflow.title }}</h3>
              <Badge
                :variant="statusConfig.variant"
                :label="statusConfig.label"
                :icon="statusConfig.badgeIcon"
                size="sm"
              />
            </div>
          </div>

          <!-- Edit Toggle Button -->
          <Button
            :variant="isEditing ? 'primary' : 'tertiary'"
            :icon="isEditing ? 'fa fa-times' : 'fa fa-pen'"
            icon-only
            size="sm"
            @click="toggleEdit"
          />
        </div>

        <!-- Content -->
        <div class="space-y-4">
          <!-- Workflow ID -->
          <div>
            <label class="block text-sm font-medium text-base mb-2">
              {{ $t('admin.workflows.workflowId', 'Workflow ID') }}
            </label>
            <Input
              v-if="isEditing"
              v-model="editData.workflow_id"
              :placeholder="$t('admin.workflows.workflowIdPlaceholder', 'Enter Dify workflow ID')"
              icon="fa fa-project-diagram"
              size="sm"
              clearable
            />
            <div v-else class="text-sm text-primary-light-content bg-base-200 px-3 py-2 rounded-md">
              {{ workflow.workflow_id || $t('admin.workflows.notConfigured', 'Not configured') }}
            </div>
          </div>

          <!-- API Key -->
          <div>
            <label class="block text-sm font-medium text-base mb-2">
              {{ $t('admin.workflows.apiKey', 'API Key') }}
            </label>
            <Input
              v-if="isEditing"
              v-model="editData.api_key"
              type="password"
              :placeholder="$t('admin.workflows.apiKeyPlaceholder', 'Enter Dify API key')"
              icon="fa fa-key"
              size="sm"
              clearable
            />
            <div v-else class="text-sm text-primary-light-content bg-base-200 px-3 py-2 rounded-md font-mono">
              {{
                workflow.api_key_obfuscated || $t('admin.workflows.notConfigured', 'Not configured')
              }}
            </div>
          </div>

          <!-- LLM Selection -->
          <div>
            <label class="block text-sm font-medium text-base mb-2">
              {{ $t('admin.workflows.llm', 'Language Model') }}
            </label>
            <select
              v-if="isEditing"
              v-model="editData.llm"
              class="w-full px-3 py-2 rounded-md bg-base-200 border border-border text-primary text-sm focus:outline-none focus:ring-2 focus:ring-primary/50"
            >
              <option value="claude">Claude</option>
              <option value="mistral">Mistral</option>
            </select>
            <div v-else class="text-sm text-primary-light-content bg-base-200 px-3 py-2 rounded-md">
              {{ workflow.llm ? capitalizeFirst(workflow.llm) : 'Claude' }}
            </div>
          </div>

          <!-- Action Buttons (Edit Mode Only) -->
          <div v-if="isEditing" class="flex items-center gap-3 pt-2">
            <Button
              variant="primary"
              icon="fa fa-save"
              label="Save"
              size="sm"
              :loading="loading"
              :disabled="!hasChanges"
              @click="saveChanges"
            />
            <Button
              variant="secondary"
              icon="fa fa-times"
              label="Cancel"
              size="sm"
              :disabled="loading"
              @click="cancelEdit"
            />
          </div>
        </div>
      </div>
    </div>
  </Teleport>

  <!-- Placeholder Card (maintains grid layout) -->
  <div
    v-if="isAnimating || isZoomed"
    class="bg-base-200/50 rounded-lg border-2 border-dashed border-primary-stroke min-h-[200px] flex items-center justify-center transition-all duration-300"
  >
    <div class="text-center text-primary-light-content/60">
      <i class="fa fa-edit text-2xl mb-2"></i>
      <p class="text-sm">Editing...</p>
    </div>
  </div>

  <!-- Card Container (normal state) -->
  <div
    v-else
    ref="cardRef"
    class="bg-base-100 rounded-lg shadow-sm border border-primary-stroke hover:shadow-md transition-shadow duration-200"
  >
    <div class="p-6">
      <!-- Header -->
      <div class="flex items-center justify-between mb-4">
        <div class="flex items-center">
          <!-- Icon -->
          <div
            :class="[
              'w-12 h-12 rounded-lg flex items-center justify-center transition-colors',
              statusConfig.iconBg,
            ]"
          >
            <i :class="[iconClass, 'text-xl', statusConfig.iconColor]"></i>
          </div>
          <div class="ml-4">
            <h3 class="text-lg font-semibold">{{ workflow.title }}</h3>
            <Badge
              :variant="statusConfig.variant"
              :label="statusConfig.label"
              :icon="statusConfig.badgeIcon"
              size="sm"
            />
          </div>
        </div>

        <!-- Edit Toggle Button -->
        <Button
          :variant="isEditing ? 'primary' : 'tertiary'"
          :icon="isEditing ? 'fa fa-times' : 'fa fa-pen'"
          icon-only
          size="sm"
          @click="toggleEdit"
        />
      </div>

      <!-- Content -->
      <div class="space-y-4">
        <!-- Workflow ID -->
        <div>
          <label class="block text-sm font-medium text-base mb-2">
            {{ $t('admin.workflows.workflowId', 'Workflow ID') }}
          </label>
          <div class="text-sm text-primary-light-content bg-base-200 px-3 py-2 rounded-md">
            {{ workflow.workflow_id || $t('admin.workflows.notConfigured', 'Not configured') }}
          </div>
        </div>

        <!-- API Key -->
        <div>
          <label class="block text-sm font-medium text-base mb-2">
            {{ $t('admin.workflows.apiKey', 'API Key') }}
          </label>
          <div class="text-sm text-primary-light-content bg-base-200 px-3 py-2 rounded-md font-mono">
            {{
              workflow.api_key_obfuscated || $t('admin.workflows.notConfigured', 'Not configured')
            }}
          </div>
        </div>

        <!-- LLM Selection -->
        <div>
          <label class="block text-sm font-medium text-base mb-2">
            {{ $t('admin.workflows.llm', 'Language Model') }}
          </label>
          <div class="text-sm text-primary-light-content bg-base-200 px-3 py-2 rounded-md">
            {{ workflow.llm ? capitalizeFirst(workflow.llm) : 'Claude' }}
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, watch, nextTick, onMounted, onUnmounted } from 'vue'
import type { WorkflowConfig } from '@/api/workflows'
import Badge from '@/components/ui/Badge.vue'
import Button from '@/components/ui/Button.vue'
import Input from '@/components/ui/Input.vue'

interface Props {
  workflow: WorkflowConfig
  loading?: boolean
}

const { workflow, loading = false } = defineProps<Props>()

const emit = defineEmits<{
  update: [taskType: string, data: { workflow_id?: string | null; api_key?: string | null; llm?: 'claude' | 'mistral' | null }]
}>()

// Edit state
const isEditing = ref(false)
const isZoomed = ref(false)
const isAnimating = ref(false)
const isExiting = ref(false)
const cardRef = ref<HTMLElement | null>(null)
const floatingCardRef = ref<HTMLElement | null>(null)
const originalRect = ref<DOMRect | null>(null)

const editData = ref({
  workflow_id: '',
  api_key: '',
  llm: 'claude' as 'claude' | 'mistral',
})

// Initialize edit data when workflow changes
watch(
  () => workflow,
  (newWorkflow) => {
    editData.value = {
      workflow_id: newWorkflow.workflow_id || '',
      api_key: '',
      llm: newWorkflow.llm || 'claude',
    }
  },
  { immediate: true },
)

// Workflow icon mapping
const iconMapping: Record<string, string> = {
  products: 'fa fa-box',
  timeline: 'fa fa-clock',
  profile: 'fa fa-user-circle',
  digital: 'fa fa-globe',
  jobs: 'fa fa-briefcase',
  csr: 'fa fa-heart',
  press: 'fa fa-newspaper',
  team: 'fa fa-users',
}

const iconClass = computed(() => iconMapping[workflow.task_type] || 'fa fa-cog')

// Status configuration based on workflow state
const statusConfig = computed(() => {
  const hasWorkflowId = !!workflow.workflow_id
  const hasApiKey = workflow.has_api_key

  if (hasWorkflowId && hasApiKey) {
    return {
      variant: 'success' as const,
      label: 'Active',
      badgeIcon: 'fa fa-check',
      iconBg: 'bg-success/10 group-hover:bg-success/20',
      iconColor: 'text-success',
    }
  }

  if (hasWorkflowId || hasApiKey) {
    return {
      variant: 'warning' as const,
      label: 'Partial',
      badgeIcon: 'fa fa-exclamation',
      iconBg: 'bg-warning/10 group-hover:bg-warning/20',
      iconColor: 'text-warning',
    }
  }

  return {
    variant: 'slate' as const,
    label: 'Not Configured',
    badgeIcon: 'fa fa-times',
    iconBg: 'bg-slate/10 group-hover:bg-slate/20',
    iconColor: 'text-primary-light-content',
  }
})

// Check if there are changes to save
const hasChanges = computed(() => {
  const originalWorkflowId = workflow.workflow_id || ''
  const newWorkflowId = editData.value.workflow_id || ''
  const hasNewApiKey = !!editData.value.api_key
  const originalLlm = workflow.llm || 'claude'
  const newLlm = editData.value.llm

  return originalWorkflowId !== newWorkflowId || hasNewApiKey || originalLlm !== newLlm
})

// Enter zoom mode with smooth animation
const enterZoomMode = async () => {
  if (!cardRef.value) return

  // Start animation state - this hides original card and shows backdrop
  isAnimating.value = true
  isExiting.value = false

  // FLIP: First - capture initial state from grid card
  originalRect.value = cardRef.value.getBoundingClientRect()

  // Wait for floating card to appear
  await nextTick()

  if (!floatingCardRef.value) return

  // Calculate center position for floating card
  const viewportWidth = window.innerWidth
  const viewportHeight = window.innerHeight
  const cardWidth = originalRect.value.width
  const scaledWidth = cardWidth * 1.1

  // Center horizontally, but position a bit higher than center for better UX
  const centerX = (viewportWidth - scaledWidth) / 2
  const centerY = Math.max(40, (viewportHeight - 400) / 2) // Assume expanded height, min 40px from top

  // Position floating card at center but make it appear at original position via transform
  floatingCardRef.value.style.left = `${centerX}px`
  floatingCardRef.value.style.top = `${centerY}px`
  floatingCardRef.value.style.width = `${cardWidth}px`
  // Don't set height - let content determine the height naturally

  // Calculate offset from center to original position
  const deltaX = originalRect.value.left - centerX
  const deltaY = originalRect.value.top - centerY

  // Apply the inverse transform immediately (makes it appear at original position)
  floatingCardRef.value.style.transform = `translate(${deltaX}px, ${deltaY}px) scale(${1 / 1.1})`
  floatingCardRef.value.style.transition = 'none'

  // FLIP: Play - animate to final centered state
  requestAnimationFrame(() => {
    if (!floatingCardRef.value) return

    floatingCardRef.value.style.transition = 'transform 300ms cubic-bezier(0.2, 0, 0.2, 1)'
    floatingCardRef.value.style.transform = 'translate(0, 0) scale(1.1)'

    // Start blur animation gradually
    setTimeout(() => {
      isZoomed.value = true
    }, 50)

    // Enable editing after animation completes
    setTimeout(() => {
      isAnimating.value = false // Animation finished
    }, 300)

    isEditing.value = true
  })

  // Prevent body scroll when zoomed
  document.body.style.overflow = 'hidden'
}

// Exit zoom mode
const exitZoomMode = () => {
  if (!floatingCardRef.value || !originalRect.value) {
    isZoomed.value = false
    isAnimating.value = false
    isExiting.value = false
    document.body.style.overflow = ''
    return
  }

  // Start exit animation
  isAnimating.value = true
  isExiting.value = true

  // Start fade out blur immediately
  isZoomed.value = false

  // Calculate position back to original from current floating card position
  const centerX = parseFloat(floatingCardRef.value.style.left)
  const centerY = parseFloat(floatingCardRef.value.style.top)
  const deltaX = originalRect.value.left - centerX
  const deltaY = originalRect.value.top - centerY

  // Animate floating card back to original position
  floatingCardRef.value.style.transition = 'transform 300ms cubic-bezier(0.2, 0, 0.2, 1)'
  floatingCardRef.value.style.transform = `translate(${deltaX}px, ${deltaY}px) scale(${1 / 1.1})`

  // After animation completes, reset everything
  setTimeout(() => {
    isAnimating.value = false
    isExiting.value = false
    originalRect.value = null

    // Reset floating card styles (will be hidden by v-if anyway)
    if (floatingCardRef.value) {
      floatingCardRef.value.style.left = ''
      floatingCardRef.value.style.top = ''
      floatingCardRef.value.style.width = ''
      floatingCardRef.value.style.transform = ''
      floatingCardRef.value.style.transition = ''
    }

    // Restore body scroll
    document.body.style.overflow = ''
  }, 300)
}

// Toggle edit mode with zoom effect
const toggleEdit = async () => {
  if (isEditing.value) {
    cancelEdit()
  } else {
    await enterZoomMode()
  }
}

// Cancel editing
const cancelEdit = () => {
  isEditing.value = false
  exitZoomMode()
  // Reset edit data
  editData.value = {
    workflow_id: workflow.workflow_id || '',
    api_key: '',
    llm: workflow.llm || 'claude',
  }
}

// Save changes
const saveChanges = () => {
  if (!hasChanges.value) return

  const updateData: { workflow_id?: string | null; api_key?: string | null; llm?: 'claude' | 'mistral' | null } = {}

  // Include workflow_id if changed
  const originalWorkflowId = workflow.workflow_id || ''
  const newWorkflowId = editData.value.workflow_id || ''
  if (originalWorkflowId !== newWorkflowId) {
    updateData.workflow_id = newWorkflowId || null
  }

  // Include api_key if provided
  if (editData.value.api_key) {
    updateData.api_key = editData.value.api_key
  }

  // Include llm if changed
  const originalLlm = workflow.llm || 'claude'
  if (originalLlm !== editData.value.llm) {
    updateData.llm = editData.value.llm
  }

  emit('update', workflow.task_type, updateData)
  isEditing.value = false
  exitZoomMode()
}

// Helper function to capitalize first letter
const capitalizeFirst = (str: string) => {
  return str.charAt(0).toUpperCase() + str.slice(1)
}

// ESC key support
const handleEscKey = (event: KeyboardEvent) => {
  if (event.key === 'Escape' && isZoomed.value) {
    cancelEdit()
  }
}

onMounted(() => {
  document.addEventListener('keydown', handleEscKey)
})

onUnmounted(() => {
  document.removeEventListener('keydown', handleEscKey)
  // Restore body scroll if component unmounts while zoomed
  if (isZoomed.value) {
    document.body.style.overflow = ''
  }
})
</script>
