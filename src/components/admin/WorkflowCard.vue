<template>
  <!-- Backdrop Overlay (when zoomed) -->
  <Teleport to="body">
    <div
      v-if="isAnimating || isZoomed"
      :class="[
        'fixed inset-0 z-40 transition-all duration-300 ease-out',
        isZoomed && !isExiting
          ? 'bg-black/20 opacity-100 backdrop-blur-sm'
          : 'bg-transparent opacity-0 backdrop-blur-none',
      ]"
      @click="exitZoomMode"
    />
  </Teleport>

  <!-- Floating Card (when zoomed) -->
  <Teleport to="body">
    <div
      v-if="isAnimating || isZoomed"
      ref="floatingCardRef"
      class="bg-base-100 border-primary-stroke fixed z-50 rounded-lg border shadow-2xl"
    >
      <div class="p-6">
        <!-- Header -->
        <div class="mb-4 flex items-center justify-between">
          <div class="flex items-center">
            <!-- Icon -->
            <div
              :class="[
                'flex h-12 w-12 items-center justify-center rounded-lg transition-colors',
                statusConfig.iconBg,
              ]"
            >
              <i :class="[iconClass, 'text-xl', statusConfig.iconColor]"></i>
            </div>
            <div class="ml-4">
              <h3 class="text-lg font-semibold">{{ workflow.title }}</h3>
              <Tag
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
            size="sm"
            @click="toggleEdit"
          />
        </div>

        <!-- Content -->
        <div class="space-y-4">
          <!-- API Key -->
          <div>
            <label class="mb-2 block text-base text-sm font-medium">
              {{ $t('admin.workflows.apiKey', 'API Key') }}
            </label>
            <Input
              v-if="isEditing"
              id="workflow-api-key"
              v-model="editData.api_key"
              type="password"
              :placeholder="$t('admin.workflows.apiKeyPlaceholder', 'Enter Dify API key')"
              icon="fa-key"
            />
            <div v-else class="text-secondary bg-base-200 rounded-md px-3 py-2 font-mono text-sm">
              {{
                workflow.api_key_obfuscated || $t('admin.workflows.notConfigured', 'Not configured')
              }}
            </div>
          </div>

          <!-- Action Buttons (Edit Mode Only) -->
          <div v-if="isEditing" class="flex items-center gap-3 pt-2">
            <Button
              variant="primary"
              icon="fa fa-save"
              :label="t('admin.workflowCard.save', 'Save')"
              size="sm"
              :loading="loading"
              :disabled="!hasChanges"
              @click="saveChanges"
            />
            <Button
              variant="secondary"
              icon="fa fa-times"
              :label="t('admin.workflowCard.cancel', 'Cancel')"
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
    class="bg-base-200/50 border-primary-stroke flex min-h-[200px] items-center justify-center rounded-lg border-2 border-dashed transition-all duration-300"
  >
    <div class="text-secondary/60 text-center">
      <i class="fa fa-edit mb-2 text-2xl"></i>
      <p class="text-sm">{{ $t('admin.workflowCard.editing') }}</p>
    </div>
  </div>

  <!-- Card Container (normal state) -->
  <div
    v-else
    ref="cardRef"
    class="bg-base-100 border-primary-stroke rounded-lg border shadow-sm transition-shadow duration-200 hover:shadow-md"
  >
    <div class="p-6">
      <!-- Header -->
      <div class="mb-4 flex items-center justify-between">
        <div class="flex items-center">
          <!-- Icon -->
          <div
            :class="[
              'flex h-12 w-12 items-center justify-center rounded-lg transition-colors',
              statusConfig.iconBg,
            ]"
          >
            <i :class="[iconClass, 'text-xl', statusConfig.iconColor]"></i>
          </div>
          <div class="ml-4">
            <h3 class="text-lg font-semibold">{{ workflow.title }}</h3>
            <Tag
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
          size="sm"
          @click="toggleEdit"
        />
      </div>

      <!-- Content -->
      <div class="space-y-4">
        <!-- API Key -->
        <div>
          <label class="mb-2 block text-base text-sm font-medium">
            {{ $t('admin.workflows.apiKey', 'API Key') }}
          </label>
          <div class="text-secondary bg-base-200 rounded-md px-3 py-2 font-mono text-sm">
            {{
              workflow.api_key_obfuscated || $t('admin.workflows.notConfigured', 'Not configured')
            }}
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, watch, nextTick, onMounted, onUnmounted } from 'vue'
import { useI18n } from 'vue-i18n'
import type { WorkflowConfig } from '@/api/workflows'
import Tag from '@/components/ui/Tag.vue'
import { Button, Input } from '@owlint/feathers-vue'

const { t } = useI18n()

interface Props {
  workflow: WorkflowConfig
  loading?: boolean
}

const { workflow, loading = false } = defineProps<Props>()

const emit = defineEmits<{
  update: [
    taskType: string,
    data: {
      api_key?: string | null
    },
  ]
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
  api_key: '',
})

// Initialize edit data when workflow changes
watch(
  () => workflow,
  () => {
    editData.value = {
      api_key: '',
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
  const hasApiKey = workflow.has_api_key

  if (hasApiKey) {
    return {
      variant: 'success' as const,
      label: t('admin.workflows.status.active', 'Active'),
      badgeIcon: 'fa fa-check',
      iconBg: 'bg-success/10 group-hover:bg-success/20',
      iconColor: 'text-success',
    }
  }

  return {
    variant: 'slate' as const,
    label: t('admin.workflows.status.notConfigured', 'Not Configured'),
    badgeIcon: 'fa fa-times',
    iconBg: 'bg-slate/10 group-hover:bg-slate/20',
    iconColor: 'text-secondary',
  }
})

// Check if there are changes to save
const hasChanges = computed(() => {
  const hasNewApiKey = !!editData.value.api_key
  return hasNewApiKey
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
    api_key: '',
  }
}

// Save changes
const saveChanges = () => {
  if (!hasChanges.value) return

  const updateData: {
    api_key?: string | null
  } = {}

  // Include api_key if provided
  if (editData.value.api_key) {
    updateData.api_key = editData.value.api_key
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
