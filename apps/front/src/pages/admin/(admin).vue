<template>
  <div class="min-h-screen">
    <div>
      <!-- Header -->
      <div class="mb-8">
        <h1 class="text-3xl font-bold">
          {{ $t('admin.dashboard.title') }}
        </h1>
        <p class="text-neutral-black-font mt-2">
          {{ $t('admin.dashboard.description') }}
        </p>
      </div>

      <!-- Admin Features Grid -->
      <div class="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3">
        <Card
          v-for="feature in visibleFeatures"
          :key="feature.id"
          ring="accent"
          class="group ring-accent-400 cursor-pointer ring-0 ring-offset-white hover:shadow-none hover:ring-4 hover:ring-offset-2"
          @click="feature.navigate()"
        >
          <div class="p-6">
            <div class="mb-4 flex items-center">
              <Badge class="shrink-0" variant="secondary" color="sage" :icon="feature.icon" />
              <div class="ml-4">
                <h3 class="text-lg font-semibold">
                  {{ feature.title }}
                </h3>
                <Tag color="sage" size="sm" :label="feature.badgeLabel" />
              </div>
            </div>

            <p class="text-neutral-black-font mb-4 text-sm">
              {{ feature.description }}
            </p>
            <div class="transition-transform group-hover:translate-x-2">
              <div class="text-accent-600 flex items-center text-sm font-medium">
                <span>{{ feature.action }}</span>
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
          :description="$t('admin.dashboard.limitedAccess.message')"
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
    - admin.costs
    - admin.tasks
</route>

<script setup lang="ts">
import Card from '@/components/ui/Card.vue'
import { useAuthStore } from '@/stores/auth'
import { Alert, Badge, Tag } from '@owlint/feathers-vue'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'

const router = useRouter()
const authStore = useAuthStore()
const { t } = useI18n()

interface AdminFeature {
  id: string
  title: string
  description: string
  icon: string
  iconBgColor: string
  iconTextColor: string
  iconHoverBgColor: string
  ringColor: string
  badgeVariant: 'primary' | 'teal' | 'success' | 'warning'
  badgeLabel: string
  action: string
  actionTextColor: string
  permission?: string
  navigate: () => void
}

const features = computed((): AdminFeature[] => [
  {
    id: 'organizations',
    title: t('admin.features.organizations.title'),
    description: t('admin.features.organizations.description'),
    icon: 'fa fa-building',
    iconBgColor: 'bg-primary/10',
    iconTextColor: 'text-sage-content',
    iconHoverBgColor: 'group-hover:bg-primary/20',
    ringColor: 'ring-primary/50',
    badgeVariant: 'primary',
    badgeLabel: t('admin.adminRequired'),
    action: t('admin.features.manage'),
    actionTextColor: 'text-sage-content',
    permission: 'admin.organizations',
    navigate: () => router.push('/admin/organizations'),
  },
  {
    id: 'users',
    title: t('admin.features.users.title'),
    description: t('admin.features.users.description'),
    icon: 'fa fa-users',
    iconBgColor: 'bg-secondary/10',
    iconTextColor: 'text-almond-600',
    iconHoverBgColor: 'group-hover:bg-secondary/20',
    ringColor: 'ring-accent/50',
    badgeVariant: 'primary',
    badgeLabel: t('admin.adminRequired'),
    action: t('admin.features.manage'),
    actionTextColor: 'text-almond-600',
    permission: 'admin.organizations',
    navigate: () => router.push('/admin/users'),
  },
  {
    id: 'usage',
    title: t('admin.features.usage.title'),
    description: t('admin.features.usage.description'),
    icon: 'fa fa-chart-line',
    iconBgColor: 'bg-info/10',
    iconTextColor: 'text-info',
    iconHoverBgColor: 'group-hover:bg-info/20',
    ringColor: 'ring-info/50',
    badgeVariant: 'primary',
    badgeLabel: t('admin.adminRequired'),
    action: t('admin.features.view'),
    actionTextColor: 'text-info',
    permission: 'admin.organizations',
    navigate: () => router.push('/admin/usage'),
  },
  {
    id: 'tasks',
    title: t('admin.features.tasks.title'),
    description: t('admin.features.tasks.description'),
    icon: 'fa fa-tasks',
    iconBgColor: 'bg-info/10',
    iconTextColor: 'text-info',
    iconHoverBgColor: 'group-hover:bg-info/20',
    ringColor: 'ring-info/50',
    badgeVariant: 'primary',
    badgeLabel: t('admin.adminRequired'),
    action: t('admin.features.monitor'),
    actionTextColor: 'text-info',
    permission: 'admin.tasks',
    navigate: () => router.push('/admin/tasks'),
  },
])

const visibleFeatures = computed(() => {
  return features.value.filter(
    (feature) => !feature.permission || authStore.hasPermission(feature.permission),
  )
})

const hasAnyAdminAccess = computed(() => visibleFeatures.value.length > 0)
</script>
