<template>
  <div class="min-h-screen">
    <div>
      <!-- Header -->
      <div class="mb-8">
        <h1 class="text-3xl font-bold">
          {{ $t('admin.dashboard.title', 'Admin Dashboard') }}
        </h1>
        <p class="text-primary-light-content mt-2">
          {{ $t('admin.dashboard.description', 'Manage system features and settings') }}
        </p>
      </div>

      <!-- Admin Features Grid -->
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <div
          v-for="feature in visibleFeatures"
          :key="feature.id"
          :class="[
            'bg-base-100 rounded-lg border border-primary-stroke hover:ring-4 ring-offset-2 transition-all duration-200 cursor-pointer group',
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
            <p class="text-primary-light-content text-sm mb-4">
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
          :title="$t('admin.dashboard.limitedAccess.title', 'Limited Access')"
          :message="
            $t(
              'admin.dashboard.limitedAccess.message',
              'You have access to basic admin features. Contact your administrator for additional permissions.',
            )
          "
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
    iconTextColor: 'text-primary-content',
    iconHoverBgColor: 'group-hover:bg-primary/20',
    ringColor: 'ring-primary/50',
    badgeVariant: 'primary',
    badgeLabel: 'Admin Required',
    actionKey: 'admin.features.manage',
    actionDefault: 'Manage',
    actionTextColor: 'text-primary-content',
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
