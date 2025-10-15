<template>
  <div class="">
    <div class="mb-8">
      <h1 class="text-3xl font-bold">{{ $t('settings.title') }}</h1>
      <p class="text-secondary mt-2">{{ $t('settings.description') }}</p>
    </div>

    <div class="lg:grid lg:grid-cols-4 lg:gap-8">
      <!-- Desktop Sidebar Navigation -->
      <div class="hidden lg:block lg:col-span-1">
        <nav class="space-y-1 sticky top-8">
          <RouterLink
            v-for="tab in tabs"
            :key="tab.id"
            :to="`/settings/${tab.id}`"
            class="group flex items-center px-3 py-2 text-sm font-medium rounded-md transition-colors"
            :class="
              currentTab === tab.id
                ? 'bg-base-100 text-secondary border-primary'
                : 'text-secondary hover:text-secondary hover:bg-base-200'
            "
          >
            <i :class="tab.icon" class="mr-3 text-sm"></i>
            {{ $t(`settings.tabs.${tab.id}`) }}
          </RouterLink>
        </nav>
      </div>

      <!-- Mobile Tab Navigation -->
      <div class="lg:hidden mb-6">
        <Tabs.Root :model-value="currentTab" @update:model-value="handleTabChange">
          <Tabs.List class="flex space-x-1 rounded-lg bg-base-100 p-1">
            <Tabs.Trigger
              v-for="tab in tabs"
              :key="tab.id"
              :value="tab.id"
              class="flex-1 rounded-md py-2 text-sm font-medium transition-colors focus:outline-none focus:ring-2 focus:ring-indigo-500 data-[state=active]:bg-white data-[state=active]:text-indigo-700 dark:data-[state=active]:bg-slate-800 dark:data-[state=active]:text-white data-[state=active]:shadow-sm"
            >
              <i :class="tab.icon" class="mr-2"></i>
              {{ $t(`settings.tabs.${tab.id}`) }}
            </Tabs.Trigger>
          </Tabs.List>
        </Tabs.Root>
      </div>

      <!-- Main Content -->
      <div class="lg:col-span-3">
        <RouterView />
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { Tabs } from 'reka-ui/namespaced'
import { computed } from 'vue'
import { RouterView, useRoute, useRouter } from 'vue-router'

const route = useRoute()
const router = useRouter()

const tabs = [
  { id: 'profile', name: '/settings/profile', icon: 'fas fa-user' },
  { id: 'appearance', name: '/settings/appearance', icon: 'fas fa-palette' },
  { id: 'ai-preferences', name: '/settings/ai-preferences', icon: 'fas fa-magic' },
  { id: 'security', name: '/settings/security', icon: 'fas fa-shield-alt' },
]

const currentTab = computed(() => {
  return tabs.find((tab) => tab.name === route.name)?.id || 'profile'
})

const handleTabChange = (value: string) => {
  router.replace(`/settings/${value}`)
}

// Redirect to profile if no tab specified
if (route.path === '/settings' || route.path === '/settings/') {
  router.replace('/settings/profile')
}
</script>
