<template>
  <div class="min-h-screen">
    <div class="flex flex-col gap-4">
      <!-- Tab Navigation -->
      <div class="bg-base-100 border border-primary-stroke rounded-lg overflow-hidden">
        <div class="border-b border-primary-stroke">
          <div class="flex">
            <RouterLink
              v-for="tab in tabs"
              :key="tab.id"
              :to="tab.to"
              :active="activeTab === tab.id"
              :class="[
                'px-6 py-3 text-sm font-medium transition-all relative border-b-2',
                activeTab === tab.id
                  ? 'text-secondary bg-primary/5 border-primary'
                  : 'text-secondary hover:bg-base-200/50 border-transparent',
              ]"
            >
              <i :class="tab.icon" class="mr-2"></i>
              {{ tab.label }}
            </RouterLink>
          </div>
        </div>
        <div class="p-6">
          <RouterView />
        </div>
      </div>

      <!-- Team Users Tab -->
    </div>
    <!-- Team Settings Tab -->
  </div>
</template>

<route lang="yaml">
meta:
  permissions:
    - organization.read
</route>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useQuery } from '@pinia/colada'
import { currentOrganizationQuery } from '@/queries/organization'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()

// Get current organization
const { data: currentOrganization } = useQuery(currentOrganizationQuery, () => ({}))

// Tab management
const route = useRoute()
const activeTab = computed(() => {
  return route.path.split('/').pop() || 'users'
})

const tabs = computed(() => [
  {
    id: 'users',
    label: t('team.tabs.users', 'Team Users'),
    icon: 'fa fa-users',
    to: '/team/users',
  },
  {
    id: 'settings',
    label: t('team.tabs.settings', 'Settings'),
    icon: 'fa fa-cog',
    to: '/team/settings',
  },
  { id: 'apis', label: t('team.tabs.apis', 'External APIs'), icon: 'fa fa-plug', to: '/team/apis' },
])

const router = useRouter()
onMounted(() => {
  if (route.path === '/team') {
    // Redirect to settings instead of users (users tab is disabled)
    router.replace('/team/settings')
  }
})
</script>
