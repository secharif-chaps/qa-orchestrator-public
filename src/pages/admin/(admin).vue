<template>
  <div class="min-h-screen bg-bg3">
    <div class="container mx-auto px-4 py-8">
      <!-- Header -->
      <div class="mb-8">
        <h1 class="text-3xl font-bold">
          {{ $t('admin.dashboard.title', 'Admin Dashboard') }}
        </h1>
        <p class="text-secondary mt-2">
          {{ $t('admin.dashboard.description', 'Manage system features and settings') }}
        </p>
      </div>

      <!-- Admin Features Grid -->
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <!-- Workspace Management -->
        <div
          v-if="hasWorkspaceAccess"
          class="bg-bg1 rounded-lg shadow-sm border border-border-2 hover:shadow-md transition-all duration-200 cursor-pointer group"
          @click="navigateToWorkspaces"
        >
          <div class="p-6">
            <div class="flex items-center mb-4">
              <div
                class="w-12 h-12 bg-primary/10 text-primary rounded-lg flex items-center justify-center group-hover:bg-primary/20 transition-colors"
              >
                <i class="fa fa-building text-xl"></i>
              </div>
              <div class="ml-4">
                <h3 class="text-lg font-semibold">
                  {{ $t('admin.features.workspaces.title', 'Workspace Management') }}
                </h3>
                <Badge variant="primary" size="sm" label="Admin Required" />
              </div>
            </div>
            <p class="text-secondary text-sm mb-4">
              {{
                $t(
                  'admin.features.workspaces.description',
                  'Manage all workspaces, users, and workspace settings',
                )
              }}
            </p>
            <div class="flex items-center text-primary text-sm font-medium">
              <span>{{ $t('admin.features.manage', 'Manage') }}</span>
              <i class="fa fa-arrow-right ml-2 group-hover:translate-x-1 transition-transform"></i>
            </div>
          </div>
        </div>

        <!-- UI Demo -->
        <div
          class="bg-bg1 rounded-lg shadow-sm border border-border-2 hover:shadow-md transition-all duration-200 cursor-pointer group"
          @click="navigateToUiDemo"
        >
          <div class="p-6">
            <div class="flex items-center mb-4">
              <div
                class="w-12 h-12 bg-info/10 text-info rounded-lg flex items-center justify-center group-hover:bg-info/20 transition-colors"
              >
                <i class="fa fa-palette text-xl"></i>
              </div>
              <div class="ml-4">
                <h3 class="text-lg font-semibold">
                  {{ $t('admin.features.uiDemo.title', 'UI Components Demo') }}
                </h3>
                <Badge variant="info" size="sm" label="Developer Tool" />
              </div>
            </div>
            <p class="text-secondary text-sm mb-4">
              {{
                $t(
                  'admin.features.uiDemo.description',
                  'Preview and test all UI components and design system',
                )
              }}
            </p>
            <div class="flex items-center text-info text-sm font-medium">
              <span>{{ $t('admin.features.explore', 'Explore') }}</span>
              <i class="fa fa-arrow-right ml-2 group-hover:translate-x-1 transition-transform"></i>
            </div>
          </div>
        </div>

        <!-- Workflow Management -->
        <div
          v-if="hasWorkflowAccess"
          class="bg-bg1 rounded-lg shadow-sm border border-border-2 hover:shadow-md transition-all duration-200 cursor-pointer group"
          @click="navigateToWorkflows"
        >
          <div class="p-6">
            <div class="flex items-center mb-4">
              <div
                class="w-12 h-12 bg-success/10 text-success rounded-lg flex items-center justify-center group-hover:bg-success/20 transition-colors"
              >
                <i class="fa fa-project-diagram text-xl"></i>
              </div>
              <div class="ml-4">
                <h3 class="text-lg font-semibold">
                  {{ $t('admin.features.workflows.title', 'Workflow Management') }}
                </h3>
                <Badge variant="success" size="sm" label="Active" />
              </div>
            </div>
            <p class="text-secondary text-sm mb-4">
              {{
                $t(
                  'admin.features.workflows.description',
                  'Configure and manage automated workflows and processes',
                )
              }}
            </p>
            <div class="flex items-center text-success text-sm font-medium">
              <span>{{ $t('admin.features.configure', 'Configure') }}</span>
              <i class="fa fa-arrow-right ml-2 group-hover:translate-x-1 transition-transform"></i>
            </div>
          </div>
        </div>

        <!-- Cost Analysis -->
        <div
          v-if="hasWorkflowAccess"
          class="bg-bg1 rounded-lg shadow-sm border border-border-2 hover:shadow-md transition-all duration-200 cursor-pointer group"
          @click="navigateToCosts"
        >
          <div class="p-6">
            <div class="flex items-center mb-4">
              <div
                class="w-12 h-12 bg-error/10 text-error rounded-lg flex items-center justify-center group-hover:bg-error/20 transition-colors"
              >
                <i class="fa fa-dollar-sign text-xl"></i>
              </div>
              <div class="ml-4">
                <h3 class="text-lg font-semibold">
                  {{ $t('admin.features.costs.title', 'Cost Analysis') }}
                </h3>
                <Badge variant="error" size="sm" label="Token Tracking" />
              </div>
            </div>
            <p class="text-secondary text-sm mb-4">
              {{
                $t(
                  'admin.features.costs.description',
                  'Monitor token usage and costs for MINT screening workflows',
                )
              }}
            </p>
            <div class="flex items-center text-error text-sm font-medium">
              <span>{{ $t('admin.features.analyze', 'Analyze') }}</span>
              <i class="fa fa-arrow-right ml-2 group-hover:translate-x-1 transition-transform"></i>
            </div>
          </div>
        </div>
      </div>

      <!-- Access Restricted Message -->
      <div v-if="!hasAnyAdminAccess" class="mt-8">
        <Alert
          variant="warning"
          title="Limited Access"
          message="You have access to basic admin features. Contact your administrator for additional permissions."
          icon="fa fa-lock"
        />
      </div>
    </div>
  </div>
</template>

<route lang="yaml">
meta:
  permissions:
    - admin
</route>

<script setup lang="ts">
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import Badge from '@/components/ui/Badge.vue'
import Alert from '@/components/ui/Alert.vue'

const router = useRouter()
const authStore = useAuthStore()

// Permission checks
const hasWorkspaceAccess = computed(() => authStore.hasPermission('admin.workspaces'))
const hasWorkflowAccess = computed(() => authStore.hasPermission('admin.workflow'))
const hasAnyAdminAccess = computed(() => hasWorkspaceAccess.value || hasWorkflowAccess.value)

// Navigation methods
const navigateToWorkspaces = () => {
  router.push('/admin/workspaces')
}

const navigateToUiDemo = () => {
  router.push('/ui-demo')
}

const navigateToWorkflows = () => {
  router.push('/admin/workflows')
}

const navigateToCosts = () => {
  router.push('/admin/costs')
}
</script>
