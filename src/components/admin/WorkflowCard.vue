<template>
  <div class="bg-bg1 rounded-lg shadow-sm border border-border-2 hover:shadow-md transition-all duration-200">
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
          <div v-else class="text-sm text-secondary bg-bg2 px-3 py-2 rounded-md">
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
          <div v-else class="text-sm text-secondary bg-bg2 px-3 py-2 rounded-md font-mono">
            {{ workflow.api_key_obfuscated || $t('admin.workflows.notConfigured', 'Not configured') }}
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
</template>

<script setup lang="ts">
import { ref, computed, watch } from 'vue'
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
  update: [taskType: string, data: { workflow_id?: string | null; api_key?: string | null }]
}>()

// Edit state
const isEditing = ref(false)
const editData = ref({
  workflow_id: '',
  api_key: '',
})

// Initialize edit data when workflow changes
watch(
  () => workflow,
  (newWorkflow) => {
    editData.value = {
      workflow_id: newWorkflow.workflow_id || '',
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
    iconColor: 'text-secondary',
  }
})

// Check if there are changes to save
const hasChanges = computed(() => {
  const originalWorkflowId = workflow.workflow_id || ''
  const newWorkflowId = editData.value.workflow_id || ''
  const hasNewApiKey = !!editData.value.api_key
  
  return originalWorkflowId !== newWorkflowId || hasNewApiKey
})

// Toggle edit mode
const toggleEdit = () => {
  if (isEditing.value) {
    cancelEdit()
  } else {
    isEditing.value = true
  }
}

// Cancel editing
const cancelEdit = () => {
  isEditing.value = false
  // Reset edit data
  editData.value = {
    workflow_id: workflow.workflow_id || '',
    api_key: '',
  }
}

// Save changes
const saveChanges = () => {
  if (!hasChanges.value) return
  
  const updateData: { workflow_id?: string | null; api_key?: string | null } = {}
  
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
  
  emit('update', workflow.task_type, updateData)
  isEditing.value = false
}
</script>