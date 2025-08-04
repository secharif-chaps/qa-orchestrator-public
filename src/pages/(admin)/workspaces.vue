<template>
  <div class="min-h-screen bg-bg3">
    <div class="container mx-auto px-4 py-8">
      <!-- Header -->
      <div class="mb-8 flex items-center justify-between">
        <div>
          <h1 class="text-3xl font-bold">
            {{ $t('workspace.admin.title', 'Workspace Management') }}
          </h1>
          <p class="text-secondary mt-2">
            {{ $t('workspace.admin.description', 'Manage all workspaces in the system') }}
          </p>
        </div>

        <button
          @click="$router.push('/workspaces/create')"
          class="bg-primary text-white px-6 py-3 rounded-lg hover:bg-primary/80 transition-colors flex items-center gap-2"
        >
          <i class="fa fa-plus"></i>
          {{ $t('workspace.create.button', 'Create Workspace') }}
        </button>
      </div>

      <!-- Error Alert -->
      <div
        v-if="error"
        class="mb-6 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg"
      >
        <div class="flex items-center gap-2">
          <i class="fa fa-exclamation-triangle"></i>
          <span class="font-medium">Error:</span>
          <span>{{ error.message }}</span>
        </div>
      </div>

      <!-- Loading State -->
      <div v-if="isLoading" class="bg-bg1 rounded-lg shadow-sm p-8 text-center">
        <div
          class="animate-spin rounded-full h-12 w-12 border-b-2 border-primary mx-auto mb-4"
        ></div>
        <p class="text-secondary">{{ $t('workspace.loading', 'Loading workspaces...') }}</p>
      </div>

      <!-- Workspaces List -->
      <div v-else-if="workspaces" class="bg-bg1 rounded-lg shadow-sm overflow-hidden">
        <!-- Table Header -->
        <div class="px-6 py-4 border-b border-border-2 bg-bg2">
          <div class="grid grid-cols-12 gap-4 text-sm font-medium text-secondary">
            <div class="col-span-3">{{ $t('workspace.name', 'Name') }}</div>
            <div class="col-span-2">{{ $t('workspace.slug', 'Slug') }}</div>
            <div class="col-span-3">{{ $t('workspace.table.description', 'Description') }}</div>
            <div class="col-span-2">{{ $t('workspace.created', 'Created') }}</div>
            <div class="col-span-1">{{ $t('workspace.members', 'Members') }}</div>
            <div class="col-span-1">{{ $t('workspace.actions', 'Actions') }}</div>
          </div>
        </div>

        <!-- Table Body -->
        <div class="divide-y divide-border-2">
          <div
            v-for="workspace in workspacesWithMemberCount"
            :key="workspace.id"
            class="px-6 py-4 hover:bg-bg2/50 transition-colors"
          >
            <div class="grid grid-cols-12 gap-4 items-center">
              <!-- Name -->
              <div class="col-span-3">
                <div class="font-medium">{{ workspace.name }}</div>
                <div v-if="workspace.id === 1" class="text-xs text-primary mt-1">
                  <i class="fa fa-star mr-1"></i>
                  {{ $t('workspace.default', 'Default workspace') }}
                </div>
              </div>

              <!-- Slug -->
              <div class="col-span-2">
                <code class="text-sm bg-bg3 px-2 py-1 rounded text-secondary">{{
                  workspace.slug
                }}</code>
              </div>

              <!-- Description -->
              <div class="col-span-3">
                <p class="text-secondary text-sm">
                  {{ workspace.description || $t('workspace.noDescription', 'No description') }}
                </p>
              </div>

              <!-- Created Date -->
              <div class="col-span-2">
                <div class="text-sm text-secondary">
                  {{ formatDate(workspace.created_at) }}
                </div>
              </div>

              <!-- Member Count -->
              <div class="col-span-1">
                <div class="text-center">
                  <span
                    class="inline-flex items-center justify-center w-8 h-8 bg-primary/10 text-primary rounded-full text-sm font-medium"
                  >
                    {{ workspace.memberCount }}
                  </span>
                </div>
              </div>

              <!-- Actions -->
              <div class="col-span-1">
                <div class="flex items-center gap-2">
                  <button
                    @click="viewWorkspace(workspace.id)"
                    class="text-primary hover:text-primary/80 transition-colors p-2"
                    :title="$t('workspace.view', 'View workspace')"
                  >
                    <i class="fa fa-eye"></i>
                  </button>

                  <button
                    @click="showDeleteModal(workspace)"
                    :disabled="workspace.id === 1"
                    class="text-red-600 hover:text-red-700 transition-colors p-2 disabled:opacity-50 disabled:cursor-not-allowed"
                    :title="
                      workspace.id === 1
                        ? $t('workspace.cannotDeleteDefault', 'Cannot delete default workspace')
                        : $t('workspace.delete', 'Delete workspace')
                    "
                  >
                    <i class="fa fa-trash"></i>
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Empty State -->
        <div v-if="workspaces.length === 0" class="p-12 text-center">
          <i class="fa fa-building text-4xl text-secondary/50 mb-4"></i>
          <h3 class="text-lg font-medium text-base mb-2">
            {{ $t('workspace.empty.title', 'No workspaces found') }}
          </h3>
          <p class="text-secondary mb-6">
            {{ $t('workspace.empty.description', 'Create your first workspace to get started') }}
          </p>
          <button
            @click="$router.push('/workspaces/create')"
            class="bg-primary text-white px-6 py-2 rounded-lg hover:bg-primary/80 transition-colors"
          >
            {{ $t('workspace.create.button', 'Create Workspace') }}
          </button>
        </div>
      </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <WorkspaceDeleteModal
      v-if="workspaceToDelete"
      :workspace="workspaceToDelete"
      @confirm="handleDelete"
      @cancel="workspaceToDelete = null"
    />
  </div>
</template>

<route lang="yaml">
meta:
  permissions:
    - admin.workspaces
</route>

<script setup lang="ts">
import { ref, computed } from 'vue'
import { useQuery } from '@pinia/colada'
import { useRouter } from 'vue-router'
import { allWorkspacesQuery } from '@/queries/workspace'
import { useDeleteWorkspace } from '@/mutations/workspace'
import type { WorkspaceResponse, WorkspaceListItem } from '@/types/workspace'
import WorkspaceDeleteModal from '@/components/workspace/WorkspaceDeleteModal.vue'

const router = useRouter()

// Queries
const { data: workspaces, isLoading, error } = useQuery(allWorkspacesQuery, () => ({}))

// Mutations
const { deleteWorkspace, isLoading: isDeleting } = useDeleteWorkspace()

// Delete modal state
const workspaceToDelete = ref<WorkspaceResponse | null>(null)

// Transform workspaces to include member count (hardcoded for now)
const workspacesWithMemberCount = computed<WorkspaceListItem[]>(() => {
  if (!workspaces.value) return []

  return workspaces.value.map((workspace) => ({
    ...workspace,
    memberCount: workspace.id === 1 ? 15 : Math.floor(Math.random() * 10) + 1, // Hardcoded for now
  }))
})

// Format date helper
const formatDate = (dateString: string) => {
  return new Date(dateString).toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
  })
}

// Actions
const viewWorkspace = (id: number) => {
  router.push(`/workspaces/${id}`)
}

const showDeleteModal = (workspace: WorkspaceResponse) => {
  workspaceToDelete.value = workspace
}

const handleDelete = async (id: number) => {
  try {
    await deleteWorkspace(id)
    workspaceToDelete.value = null
    // Success notification will be handled by the mutation
  } catch (error) {
    console.error('Failed to delete workspace:', error)
    // Error notification will be handled by the mutation
  }
}
</script>
