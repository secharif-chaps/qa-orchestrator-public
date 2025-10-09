<template>
  <div class="min-h-screen">
    <div>
      <!-- Header -->
      <div class="mb-8">
        <div class="flex items-center justify-between mb-6">
          <div>
            <h1 class="text-3xl font-bold">
              {{ $t('admin.workflows.title', 'Workflow Management') }}
            </h1>
            <p class="text-primary-light-content mt-2">
              {{
                $t(
                  'admin.workflows.description',
                  'Configure Dify workflow integrations for automated analysis tasks',
                )
              }}
            </p>
          </div>

          <!-- Back to Admin Dashboard -->
          <Button
            variant="tertiary"
            icon="fa fa-arrow-left"
            :label="$t('admin.dashboard.back', 'Back to Admin')"
            @click="$router.push('/admin')"
          />
        </div>

        <!-- Status Overview -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
          <div class="bg-base-100 rounded-lg border border-primary-stroke p-4">
            <div class="flex items-center">
              <Tag variant="success" icon="fa fa-check" size="sm" />
              <span class="ml-3 text-sm font-medium"
                >{{ activeCount }} {{ $t('admin.workflows.status.active', 'Active') }}</span
              >
            </div>
          </div>
          <div class="bg-base-100 rounded-lg border border-primary-stroke p-4">
            <div class="flex items-center">
              <Tag variant="warning" icon="fa fa-exclamation" size="sm" />
              <span class="ml-3 text-sm font-medium"
                >{{ partialCount }} {{ $t('admin.workflows.status.partial', 'Partial') }}</span
              >
            </div>
          </div>
          <div class="bg-base-100 rounded-lg border border-primary-stroke p-4">
            <div class="flex items-center">
              <Tag variant="slate" icon="fa fa-times" size="sm" />
              <span class="ml-3 text-sm font-medium"
                >{{ inactiveCount }}
                {{ $t('admin.workflows.status.notConfigured', 'Not Configured') }}</span
              >
            </div>
          </div>
        </div>
      </div>

      <!-- Loading State -->
      <div v-if="loading && !workflows.length" class="flex justify-center py-16">
        <div class="text-center">
          <i class="fa fa-spinner animate-spin text-4xl text-primary-light-content mb-4"></i>
          <p class="text-primary-light-content">
            {{ $t('admin.workflows.loading', 'Loading workflows...') }}
          </p>
        </div>
      </div>

      <!-- Error State -->
      <Alert
        v-else-if="error"
        variant="error"
        :title="$t('admin.workflows.error.title', 'Failed to Load Workflows')"
        :message="error"
        icon="fa fa-exclamation-triangle"
        class="mb-6"
      />

      <!-- Workflow Cards Grid -->
      <div v-else class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
        <WorkflowCard
          v-for="workflow in workflows"
          :key="workflow.task_type"
          :workflow="workflow"
          :loading="updatingWorkflow === workflow.task_type"
          @update="handleWorkflowUpdate"
        />
      </div>

      <!-- Success Toast -->
      <div
        v-if="showSuccessToast"
        class="fixed bottom-4 right-4 bg-success text-white px-4 py-3 rounded-lg shadow-lg transition-all duration-300 z-50"
      >
        <div class="flex items-center">
          <i class="fa fa-check-circle mr-2"></i>
          {{ $t('admin.workflows.updateSuccess', 'Workflow updated successfully!') }}
        </div>
      </div>
    </div>
  </div>
</template>

<route lang="yaml">
meta:
  permissions:
    - admin.workflows
</route>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { workflowsApi, type WorkflowConfig } from '@/api/workflows'
import Button from '@/components/ui/Button.vue'
import Alert from '@/components/ui/Alert.vue'
import Tag from '@/components/ui/Tag.vue'
import WorkflowCard from '@/components/admin/WorkflowCard.vue'

const router = useRouter()

// Reactive state
const workflows = ref<WorkflowConfig[]>([])
const loading = ref(false)
const error = ref<string | null>(null)
const updatingWorkflow = ref<string | null>(null)
const showSuccessToast = ref(false)

// Status counts
const activeCount = computed(
  () => workflows.value.filter((w) => w.workflow_id && w.has_api_key).length,
)
const partialCount = computed(
  () =>
    workflows.value.filter(
      (w) => (w.workflow_id || w.has_api_key) && !(w.workflow_id && w.has_api_key),
    ).length,
)
const inactiveCount = computed(
  () => workflows.value.filter((w) => !w.workflow_id && !w.has_api_key).length,
)

// Load workflows on mount
onMounted(async () => {
  await loadWorkflows()
})

// Load workflows from API
const loadWorkflows = async () => {
  try {
    loading.value = true
    error.value = null
    workflows.value = await workflowsApi.getWorkflows()
  } catch (err) {
    console.error('Failed to load workflows:', err)
    error.value = err instanceof Error ? err.message : 'An unexpected error occurred'
  } finally {
    loading.value = false
  }
}

// Handle workflow updates
const handleWorkflowUpdate = async (
  taskType: string,
  data: { workflow_id?: string | null; api_key?: string | null; llm?: 'claude' | 'mistral' | null },
) => {
  try {
    updatingWorkflow.value = taskType

    // Update via API
    const updatedWorkflow = await workflowsApi.updateWorkflow(taskType, data)

    // Update local state
    const index = workflows.value.findIndex((w) => w.task_type === taskType)
    if (index !== -1) {
      workflows.value[index] = updatedWorkflow
    }

    // Show success toast
    showSuccessToast.value = true
    setTimeout(() => {
      showSuccessToast.value = false
    }, 3000)
  } catch (err) {
    console.error('Failed to update workflow:', err)
    // You could show an error toast here
    alert('Failed to update workflow: ' + (err instanceof Error ? err.message : 'Unknown error'))
  } finally {
    updatingWorkflow.value = null
  }
}
</script>
