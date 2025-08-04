<template>
  <div class="min-h-screen bg-bg3">
    <div class="container mx-auto px-4 py-8">
      <!-- Header -->
      <div class="mb-8">
        <div class="flex items-center gap-4 mb-4">
          <button
            @click="$router.push('/workspaces')"
            class="text-secondary hover:text-base transition-colors p-2"
          >
            <i class="fa fa-arrow-left"></i>
          </button>
          <div>
            <h1 class="text-3xl font-bold text-base">
              {{ workspace?.name || $t('workspace.detail.title', 'Workspace Details') }}
            </h1>
            <p class="text-secondary mt-2">
              {{
                workspace?.description ||
                $t('workspace.detail.description', 'Workspace information and settings')
              }}
            </p>
          </div>
        </div>
      </div>

      <!-- Loading State -->
      <div v-if="isLoading" class="bg-bg1 rounded-lg shadow-sm p-8 text-center">
        <div
          class="animate-spin rounded-full h-12 w-12 border-b-2 border-primary mx-auto mb-4"
        ></div>
        <p class="text-secondary">{{ $t('workspace.loading', 'Loading workspace...') }}</p>
      </div>

      <!-- Error State -->
      <div
        v-else-if="error"
        class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg"
      >
        <div class="flex items-center gap-2">
          <i class="fa fa-exclamation-triangle"></i>
          <span class="font-medium">Error:</span>
          <span>{{ error.message }}</span>
        </div>
      </div>

      <!-- Workspace Details -->
      <div v-else-if="workspace" class="space-y-6">
        <!-- Basic Info Card -->
        <div class="bg-bg1 rounded-lg shadow-sm p-6">
          <h2 class="text-xl font-semibold mb-4">
            {{ $t('workspace.detail.basicInfo', 'Basic Information') }}
          </h2>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
              <label class="block text-sm font-medium text-secondary mb-1">{{
                $t('workspace.name', 'Name')
              }}</label>
              <p class="text-base font-medium">{{ workspace.name }}</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-secondary mb-1">{{
                $t('workspace.slug', 'Slug')
              }}</label>
              <code class="text-sm bg-bg3 px-2 py-1 rounded">{{ workspace.slug }}</code>
            </div>
            <div class="md:col-span-2" v-if="workspace.description">
              <label class="block text-sm font-medium text-secondary mb-1">{{
                $t('workspace.description', 'Description')
              }}</label>
              <p class="text-base">{{ workspace.description }}</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-secondary mb-1">{{
                $t('workspace.created', 'Created')
              }}</label>
              <p class="text-base">{{ formatDate(workspace.created_at) }}</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-secondary mb-1">{{
                $t('workspace.updated', 'Last Updated')
              }}</label>
              <p class="text-base">{{ formatDate(workspace.updated_at) }}</p>
            </div>
          </div>
        </div>

        <!-- Placeholder for future sections -->
        <div class="bg-bg1 rounded-lg shadow-sm p-6">
          <h2 class="text-xl font-semibold mb-4">
            {{ $t('workspace.detail.members', 'Members') }}
          </h2>
          <div class="text-center p-8 text-secondary">
            <i class="fa fa-users text-4xl mb-4 opacity-50"></i>
            <p>
              {{
                $t(
                  'workspace.detail.membersPlaceholder',
                  'Member management will be implemented here',
                )
              }}
            </p>
          </div>
        </div>

        <div class="bg-bg1 rounded-lg shadow-sm p-6">
          <h2 class="text-xl font-semibold mb-4">
            {{ $t('workspace.detail.settings', 'Settings') }}
          </h2>
          <div class="text-center p-8 text-secondary">
            <i class="fa fa-cog text-4xl mb-4 opacity-50"></i>
            <p>
              {{
                $t(
                  'workspace.detail.settingsPlaceholder',
                  'Workspace settings will be implemented here',
                )
              }}
            </p>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<route lang="yaml">
meta:
  permissions:
    - admin.workspaces
</route>

<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import { useQuery } from '@pinia/colada'
import { allWorkspacesQuery } from '@/queries/workspace'

const route = useRoute()

const workspaceId = computed(() => parseInt(route.params.workspaceId as string))

// Query for workspace details
const { data: workspaces, isLoading, error } = useQuery(allWorkspacesQuery, () => ({}))

const workspace = computed(() =>
  workspaces.value?.find((workspace) => workspace.id === workspaceId.value),
)

// Format date helper
const formatDate = (dateString: string) => {
  return new Date(dateString).toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'long',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}
</script>
