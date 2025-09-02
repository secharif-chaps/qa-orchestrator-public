<template>
  <div class="min-h-screen bg-bg3">
    <div class="flex flex-col gap-4">
      <!-- Workspace Header -->
      <div class="bg-bg1 p-6 rounded-lg border border-border-2">
        <div class="flex items-center gap-3">
          <div class="w-12 h-12 bg-primary/10 rounded-lg flex items-center justify-center">
            <i class="fa fa-building text-primary text-xl"></i>
          </div>
          <div>
            <h1 class="text-2xl font-bold text-primary">
              {{ currentWorkspace?.name || 'Workspace' }}
            </h1>
            <p class="text-sm text-secondary">
              {{ $t('team.workspace_description', 'Manage your workspace team and settings') }}
            </p>
          </div>
        </div>
      </div>

      <!-- Tab Navigation -->
      <div class="bg-bg1 border border-border-2 rounded-lg overflow-hidden">
        <div class="border-b border-border-2">
          <div class="flex">
            <RouterLink
              v-for="tab in tabs"
              :key="tab.id"
              :to="tab.to"
              :active="activeTab === tab.id"
              :class="[
                'px-6 py-3 text-sm font-medium transition-all relative border-b-2',
                activeTab === tab.id
                  ? 'text-primary bg-primary/5 border-primary'
                  : 'text-secondary hover:bg-bg2/50 border-transparent',
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
    - workspace.read
</route>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useQuery } from '@pinia/colada'
import { currentWorkspaceQuery } from '@/queries/workspace'
import { useRoute, useRouter } from 'vue-router'

// Get current workspace
const { data: currentWorkspace } = useQuery(currentWorkspaceQuery, () => ({}))

// Tab management
const route = useRoute()
const activeTab = computed(() => {
  return route.path.split('/').pop() || 'users'
})

const tabs = [
  { id: 'users', label: 'Team Users', icon: 'fa fa-users', to: '/team/users' },
  { id: 'settings', label: 'Settings', icon: 'fa fa-cog', to: '/team/settings' },
  { id: 'apis', label: 'External APIs', icon: 'fa fa-plug', to: '/team/apis' },
]

const router = useRouter()
onMounted(() => {
  if (route.path === '/team') {
    router.replace('/team/users')
  }
})
</script>
