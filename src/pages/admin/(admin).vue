<template>
  <div class="min-h-screen bg-bg3">
    <div>
      <!-- Header -->
      <div class="mb-8">
        <h1 class="text-3xl font-bold">
          {{ $t('admin.dashboard.title', 'Admin Dashboard') }}
        </h1>
        <p class="text-secondary mt-2">
          {{ $t('admin.dashboard.description', 'Manage system features and settings') }}
        </p>
      </div>

      <!-- Sophie's Emergency Section -->
      <div
        v-if="hasWorkspaceAccess"
        class="mb-8 bg-gradient-to-br dark:from-red-950/50 dark:to-orange-950/50 from-red-50 to-orange-50 rounded-xl border-2 dark:border-red-500/50 border-red-300 p-8 relative overflow-hidden"
      >
        <!-- Animated Background Pattern -->
        <div class="absolute inset-0 opacity-10 dark:opacity-10">
          <div
            class="absolute inset-0"
            style="
              background-image: repeating-linear-gradient(
                45deg,
                transparent,
                transparent 35px,
                rgba(255, 0, 0, 0.1) 35px,
                rgba(255, 0, 0, 0.1) 70px
              );
            "
          ></div>
        </div>

        <!-- Content -->
        <div class="relative z-10">
          <!-- Title Section -->
          <div class="text-center mb-6">
            <div class="inline-flex items-center gap-3 mb-4">
              <i
                class="fa fa-radiation text-3xl dark:text-yellow-400 text-orange-500 animate-pulse"
              ></i>
              <h2 class="text-2xl font-bold dark:text-red-400 text-red-600">
                🚨 SOPHIE'S EMERGENCY CONTROL PANEL 🚨
              </h2>
              <i
                class="fa fa-radiation text-3xl dark:text-yellow-400 text-orange-500 animate-pulse"
              ></i>
            </div>
            <p class="dark:text-orange-300 text-orange-700 font-medium">
              Hey Sophie! 👋 Nicolas made this just for you while he's on vacation 🏖️
            </p>
            <p class="dark:text-orange-400/80 text-orange-600 text-sm mt-2">
              If tasks get stuck and the queue goes crazy, you have the power to fix it!
            </p>
          </div>

          <!-- Big Red Button Container -->
          <div class="flex flex-col items-center">
            <!-- Warning Signs -->
            <div class="flex gap-4 mb-4">
              <div
                class="dark:bg-yellow-900/50 bg-yellow-100 dark:border-yellow-500/50 border-yellow-600 border rounded px-3 py-1 flex items-center gap-2"
              >
                <i class="fa fa-exclamation-triangle dark:text-yellow-400 text-yellow-700"></i>
                <span class="dark:text-yellow-300 text-yellow-800 text-sm font-mono"
                  >DANGER ZONE</span
                >
              </div>
              <div
                class="dark:bg-red-900/50 bg-red-100 dark:border-red-500/50 border-red-400 border rounded px-3 py-1 flex items-center gap-2"
              >
                <i class="fa fa-skull-crossbones dark:text-red-400 text-red-600"></i>
                <span class="dark:text-red-300 text-red-700 text-sm font-mono"
                  >USE WITH CAUTION</span
                >
              </div>
            </div>

            <!-- The Big Red Button -->
            <div class="relative group">
              <!-- Glow effect -->
              <div
                class="absolute inset-0 bg-red-600 rounded-full blur-xl dark:opacity-50 opacity-30 group-hover:dark:opacity-75 group-hover:opacity-40 transition-opacity animate-pulse"
              ></div>

              <!-- Button -->
              <button
                @click="showConfirmDialog = true"
                :disabled="isExecuting"
                class="relative bg-gradient-to-b from-red-600 to-red-800 hover:from-red-500 hover:to-red-700 disabled:from-gray-600 disabled:to-gray-800 text-white font-bold py-8 px-12 rounded-full shadow-2xl transform transition-all duration-200 hover:scale-105 active:scale-95 border-4 dark:border-red-900 border-red-700 hover:dark:border-red-700 hover:border-red-600 disabled:border-gray-900"
              >
                <div class="flex flex-col items-center gap-2">
                  <i
                    :class="['text-4xl', isExecuting ? 'fa fa-spinner fa-spin' : 'fa fa-bomb']"
                  ></i>
                  <span class="text-lg uppercase tracking-wider">
                    {{ isExecuting ? 'EXECUTING...' : 'FIX EVERYTHING' }}
                  </span>
                  <span class="text-xs opacity-75">
                    {{ isExecuting ? 'Please wait...' : 'Fail stuck tasks & clear queue' }}
                  </span>
                </div>
              </button>
            </div>

            <!-- Status Messages -->
            <div v-if="lastExecutionResult" class="mt-6 w-full max-w-md">
              <Alert
                :variant="lastExecutionResult.success ? 'success' : 'error'"
                :title="
                  lastExecutionResult.success
                    ? '✅ Mission Accomplished!'
                    : '❌ Houston, we have a problem'
                "
                :message="lastExecutionResult.message"
                icon="fa fa-check-circle"
              />
            </div>

            <!-- Instructions -->
            <div class="mt-6 dark:bg-black/30 bg-red-100/50 rounded-lg p-4 max-w-md">
              <p class="dark:text-orange-300 text-orange-700 text-sm text-center">
                <i class="fa fa-info-circle mr-2"></i>
                This button will fail all stuck tasks and purge the message queue. Only use when
                tasks are really stuck!
              </p>
            </div>
          </div>
        </div>

        <!-- Confirmation Dialog -->
        <div
          v-if="showConfirmDialog"
          class="fixed inset-0 bg-black/80 backdrop-blur-sm flex items-center justify-center z-50"
          @click.self="showConfirmDialog = false"
        >
          <div
            class="bg-gradient-to-br dark:from-gray-900 dark:to-red-950 from-white to-red-50 rounded-xl p-8 max-w-xl border-2 dark:border-red-500/50 border-red-400 shadow-2xl transform animate-in"
          >
            <div class="text-center mb-6">
              <i
                class="fa fa-exclamation-triangle text-6xl dark:text-yellow-400 text-orange-500 mb-4 animate-bounce"
              ></i>
              <h3 class="text-2xl font-bold dark:text-red-400 text-red-600 mb-2">⚠️ CONFIRM ⚠️</h3>
              <p class="dark:text-orange-300 text-orange-700">
                Sophie, are you absolutely sure you want to activate the emergency protocol?
              </p>
              <p class="dark:text-orange-400/80 text-orange-600 text-sm mt-2">
                This will fail all stuck tasks and clear the entire queue!
              </p>
            </div>

            <div class="flex gap-4">
              <button
                @click="showConfirmDialog = false"
                class="flex-1 dark:bg-gray-700 bg-gray-200 dark:hover:bg-gray-600 hover:bg-gray-300 dark:text-white text-gray-800 font-bold py-3 px-6 rounded-lg transition-colors"
              >
                <i class="fa fa-times mr-2"></i>
                Abort Mission
              </button>
              <button
                @click="executeEmergencyProtocol"
                class="flex-1 bg-gradient-to-r from-red-600 to-orange-600 hover:from-red-500 hover:to-orange-500 text-white font-bold py-3 px-6 rounded-lg transition-all transform hover:scale-105"
              >
                <i class="fa fa-bomb mr-2"></i>
                NUKE EVERYTHING!
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Admin Features Grid -->
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <div
          v-for="feature in visibleFeatures"
          :key="feature.id"
          :class="[
            'bg-bg1 rounded-lg border border-border-2 hover:ring-4 ring-offset-2 transition-all duration-200 cursor-pointer group',
            feature.ringColor,
          ]"
          @click="feature.navigate()"
        >
          <div class="p-6">
            <div class="flex items-center mb-4">
              <div
                :class="[
                  'w-12 h-12 rounded-lg flex items-center justify-center transition-colors',
                  feature.iconBgColor,
                  feature.iconTextColor,
                  feature.iconHoverBgColor,
                ]"
              >
                <i :class="[feature.icon, 'text-xl']"></i>
              </div>
              <div class="ml-4">
                <h3 class="text-lg font-semibold">
                  {{ $t(feature.titleKey, feature.titleDefault) }}
                </h3>
                <Badge :variant="feature.badgeVariant" size="sm" :label="feature.badgeLabel" />
              </div>
            </div>
            <p class="text-secondary text-sm mb-4">
              {{ $t(feature.descriptionKey, feature.descriptionDefault) }}
            </p>
            <div :class="['flex items-center text-sm font-medium', feature.actionTextColor]">
              <span>{{ $t(feature.actionKey, feature.actionDefault) }}</span>
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
    - admin.workspaces
    - admin.workflows
    - admin.costs
</route>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import Badge from '@/components/ui/Badge.vue'
import Alert from '@/components/ui/Alert.vue'

const router = useRouter()
const authStore = useAuthStore()

// Sophie's Emergency Section State
const showConfirmDialog = ref(false)
const isExecuting = ref(false)
const lastExecutionResult = ref<{
  success: boolean
  message: string
} | null>(null)

// Sophie's Emergency Protocol
const executeEmergencyProtocol = async () => {
  showConfirmDialog.value = false
  isExecuting.value = true
  lastExecutionResult.value = null

  const apiUrl = import.meta.env.VITE_BACKEND_API || 'http://localhost:3000'

  try {
    const response = await fetch(`${apiUrl}/api/admin/tasks/fail-stuck`, {
      method: 'POST',
      headers: {
        Authorization: `Bearer ${authStore.accessToken}`,
        'Content-Type': 'application/json',
      },
    })

    if (response.ok) {
      const data = await response.json()
      lastExecutionResult.value = {
        success: true,
        message: `Successfully failed ${data.tasks_failed} stuck tasks and cleared the queue! ${data.queue_status}. Nicolas would be proud! 🎉`,
      }
    } else if (response.status === 403) {
      lastExecutionResult.value = {
        success: false,
        message: 'Access denied! You need admin.workspaces permission to use this feature.',
      }
    } else {
      lastExecutionResult.value = {
        success: false,
        message: 'Something went wrong. Maybe try turning it off and on again? 🤷',
      }
    }
  } catch (error) {
    lastExecutionResult.value = {
      success: false,
      message: `Network error: ${error}. The internet might be broken! 😱`,
    }
  } finally {
    isExecuting.value = false
  }
}

interface AdminFeature {
  id: string
  titleKey: string
  titleDefault: string
  descriptionKey: string
  descriptionDefault: string
  icon: string
  iconBgColor: string
  iconTextColor: string
  iconHoverBgColor: string
  ringColor: string
  badgeVariant: 'primary' | 'teal' | 'success' | 'warning'
  badgeLabel: string
  actionKey: string
  actionDefault: string
  actionTextColor: string
  permission?: string
  navigate: () => void
}

const features: AdminFeature[] = [
  {
    id: 'workspaces',
    titleKey: 'admin.features.workspaces.title',
    titleDefault: 'Workspace Management',
    descriptionKey: 'admin.features.workspaces.description',
    descriptionDefault: 'Manage all workspaces, users, and workspace settings',
    icon: 'fa fa-building',
    iconBgColor: 'bg-primary/10',
    iconTextColor: 'text-primary',
    iconHoverBgColor: 'group-hover:bg-primary/20',
    ringColor: 'ring-primary/50',
    badgeVariant: 'primary',
    badgeLabel: 'Admin Required',
    actionKey: 'admin.features.manage',
    actionDefault: 'Manage',
    actionTextColor: 'text-primary',
    permission: 'admin.workspaces',
    navigate: () => router.push('/admin/workspaces'),
  },
  {
    id: 'ui-demo',
    titleKey: 'admin.features.uiDemo.title',
    titleDefault: 'UI Components Demo',
    descriptionKey: 'admin.features.uiDemo.description',
    descriptionDefault: 'Preview and test all UI components and design system',
    icon: 'fa fa-palette',
    iconBgColor: 'bg-teal-500/10',
    iconTextColor: 'text-teal-500',
    iconHoverBgColor: 'group-hover:bg-teal-500/20',
    ringColor: 'ring-teal-500/50',
    badgeVariant: 'teal',
    badgeLabel: 'Developer Tool',
    actionKey: 'admin.features.explore',
    actionDefault: 'Explore',
    actionTextColor: 'text-teal-500',
    navigate: () => router.push('/ui-demo'),
  },
  {
    id: 'workflows',
    titleKey: 'admin.features.workflows.title',
    titleDefault: 'Workflow Management',
    descriptionKey: 'admin.features.workflows.description',
    descriptionDefault: 'Configure and manage automated workflows and processes',
    icon: 'fa fa-project-diagram',
    iconBgColor: 'bg-success/10',
    iconTextColor: 'text-success',
    iconHoverBgColor: 'group-hover:bg-success/20',
    ringColor: 'ring-success/50',
    badgeVariant: 'success',
    badgeLabel: 'Active',
    actionKey: 'admin.features.configure',
    actionDefault: 'Configure',
    actionTextColor: 'text-success',
    permission: 'admin.workflows',
    navigate: () => router.push('/admin/workflows'),
  },
  {
    id: 'costs',
    titleKey: 'admin.features.costs.title',
    titleDefault: 'Cost Analysis',
    descriptionKey: 'admin.features.costs.description',
    descriptionDefault: 'Monitor token usage and costs for MINT screening workflows',
    icon: 'fa fa-dollar-sign',
    iconBgColor: 'bg-orange-500/10',
    iconTextColor: 'text-orange-500',
    iconHoverBgColor: 'group-hover:bg-orange-500/20',
    ringColor: 'ring-orange-500/50',
    badgeVariant: 'warning',
    badgeLabel: 'Token Tracking',
    actionKey: 'admin.features.analyze',
    actionDefault: 'Analyze',
    actionTextColor: 'text-orange-500',
    permission: 'admin.costs',
    navigate: () => router.push('/admin/costs'),
  },
]

const visibleFeatures = computed(() => {
  return features.filter(
    (feature) => !feature.permission || authStore.hasPermission(feature.permission),
  )
})

const hasAnyAdminAccess = computed(() => visibleFeatures.value.length > 0)
const hasWorkspaceAccess = computed(() => authStore.hasPermission('admin.workspaces'))
</script>
