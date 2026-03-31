<template>
  <div class="min-h-screen">
    <div>
      <!-- Header -->
      <div class="mb-8">
        <h1 class="text-3xl font-bold">
          {{ $t('admin.dashboard.title') }}
        </h1>
        <p class="text-secondary mt-2">
          {{ $t('admin.dashboard.description') }}
        </p>
      </div>

      <!-- Admin Features Grid -->
      <div class="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3">
        <Card
          v-for="feature in visibleFeatures"
          :key="feature.id"
          ring="accent"
          class="group ring-offset-base-100 ring-accent-400 cursor-pointer ring-0 hover:shadow-none hover:ring-4 hover:ring-offset-2"
          @click="feature.navigate()"
        >
          <div class="p-6">
            <div class="mb-4 flex items-center">
              <Badge variant="secondary" color="sage" :icon="feature.icon" />
              <div class="ml-4">
                <h3 class="text-lg font-semibold">
                  {{ $t(feature.titleKey, feature.titleDefault) }}
                </h3>
                <Tag
                  variant="sage"
                  appearance="light"
                  size="sm"
                  :label="
                    $t(
                      feature.badgeLabel,
                      feature.badgeLabel === 'admin.adminRequired'
                        ? 'Admin Required'
                        : feature.badgeLabel,
                    )
                  "
                />
              </div>
            </div>

            <p class="text-secondary mb-4 text-sm">
              {{ $t(feature.descriptionKey, feature.descriptionDefault) }}
            </p>
            <div class="transition-transform group-hover:translate-x-2">
              <div class="text-accent-600 flex items-center text-sm font-medium">
                <span>{{ $t(feature.actionKey, feature.actionDefault) }}</span>
                <i class="fa fa-arrow-right ml-2"></i>
              </div>
            </div>
          </div>
        </Card>
      </div>

      <!-- Access Restricted Message -->
      <div v-if="!hasAnyAdminAccess" class="mt-8">
        <Alert
          variant="warning"
          :title="$t('admin.dashboard.limitedAccess.title')"
          :description="
            $t(
              'admin.dashboard.limitedAccess.message',
              'You have access to basic admin features. Contact your administrator for additional permissions.',
            )
          "
          icon="fa-lock"
        />
      </div>
    </div>
  </div>
</template>

<route lang="yaml">
meta:
  permissions:
    - admin.organizations
    - admin.workflows
    - admin.costs
    - admin.tasks
</route>

<script setup lang="ts">
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import Tag from '@/components/ui/Tag.vue'
import { Alert, Badge } from '@owlint/feathers-vue'
import Card from '@/components/ui/Card.vue'

const router = useRouter()
const authStore = useAuthStore()

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
    id: 'organizations',
    titleKey: 'admin.features.organizations.title',
    titleDefault: 'Organization Management',
    descriptionKey: 'admin.features.organizations.description',
    descriptionDefault: 'Manage organization modules, tokens, and settings',
    icon: 'fa fa-building',
    iconBgColor: 'bg-primary/10',
    iconTextColor: 'text-sage-content',
    iconHoverBgColor: 'group-hover:bg-primary/20',
    ringColor: 'ring-primary/50',
    badgeVariant: 'primary',
    badgeLabel: 'admin.adminRequired',
    actionKey: 'admin.features.manage',
    actionDefault: 'Manage',
    actionTextColor: 'text-sage-content',
    permission: 'admin.organizations',
    navigate: () => router.push('/admin/organizations'),
  },
  {
    id: 'users',
    titleKey: 'admin.features.users.title',
    titleDefault: 'User Management',
    descriptionKey: 'admin.features.users.description',
    descriptionDefault: 'Manage user organization assignments and user access',
    icon: 'fa fa-users',
    iconBgColor: 'bg-secondary/10',
    iconTextColor: 'text-almond-600',
    iconHoverBgColor: 'group-hover:bg-secondary/20',
    ringColor: 'ring-accent/50',
    badgeVariant: 'primary',
    badgeLabel: 'admin.adminRequired',
    actionKey: 'admin.features.manage',
    actionDefault: 'Manage',
    actionTextColor: 'text-almond-600',
    permission: 'admin.organizations',
    navigate: () => router.push('/admin/users'),
  },
  {
    id: 'usage',
    titleKey: 'admin.features.usage.title',
    titleDefault: 'Usage Dashboard',
    descriptionKey: 'admin.features.usage.description',
    descriptionDefault: 'View application usage metrics across all organizations',
    icon: 'fa fa-chart-line',
    iconBgColor: 'bg-info/10',
    iconTextColor: 'text-info',
    iconHoverBgColor: 'group-hover:bg-info/20',
    ringColor: 'ring-info/50',
    badgeVariant: 'primary',
    badgeLabel: 'admin.adminRequired',
    actionKey: 'admin.features.view',
    actionDefault: 'View',
    actionTextColor: 'text-info',
    permission: 'admin.organizations',
    navigate: () => router.push('/admin/usage'),
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
    id: 'tasks',
    titleKey: 'admin.features.tasks.title',
    titleDefault: 'Task Monitoring',
    descriptionKey: 'admin.features.tasks.description',
    descriptionDefault:
      'Monitor running tasks across all organizations and restart stuck processes',
    icon: 'fa fa-tasks',
    iconBgColor: 'bg-info/10',
    iconTextColor: 'text-info',
    iconHoverBgColor: 'group-hover:bg-info/20',
    ringColor: 'ring-info/50',
    badgeVariant: 'primary',
    badgeLabel: 'admin.adminRequired',
    actionKey: 'admin.features.monitor',
    actionDefault: 'Monitor',
    actionTextColor: 'text-info',
    permission: 'admin.tasks',
    navigate: () => router.push('/admin/tasks'),
  },
]

const visibleFeatures = computed(() => {
  return features.filter(
    (feature) => !feature.permission || authStore.hasPermission(feature.permission),
  )
})

const hasAnyAdminAccess = computed(() => visibleFeatures.value.length > 0)
</script>
