<template>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
      <!-- Header -->
      <div class="mb-8">
        <h1 class="text-3xl font-bold text-slate-900 dark:text-slate-100">{{ $t('account.title') }}</h1>
        <p class="text-slate-600 dark:text-slate-400 mt-2">{{ $t('account.description') }}</p>
      </div>

      <div class="lg:grid lg:grid-cols-4 lg:gap-8">
        <!-- Desktop Sidebar Navigation -->
        <div class="hidden lg:block lg:col-span-1">
          <nav class="space-y-1 sticky top-8">
            <NuxtLink
              v-for="tab in tabs"
              :key="tab.id"
              :to="`/account/${tab.id}`"
              class="group flex items-center px-3 py-2 text-sm font-medium rounded-md transition-colors"
              :class="currentTab === tab.id 
                ? 'bg-indigo-100 dark:bg-slate-800 text-indigo-700 dark:text-slate-100 border-indigo-500 dark:border-slate-700' 
                : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 hover:bg-slate-50 dark:hover:bg-slate-800'"
            >
              <i :class="tab.icon" class="mr-3 text-sm"></i>
              {{ $t(`account.tabs.${tab.id}`) }}
            </NuxtLink>
          </nav>
        </div>

        <!-- Mobile Tab Navigation -->
        <div class="lg:hidden mb-6">
          <Tabs.Root :model-value="currentTab" @update:model-value="handleTabChange">
            <Tabs.List class="flex space-x-1 rounded-lg bg-slate-100 p-1">
              <Tabs.Trigger
                v-for="tab in tabs"
                :key="tab.id"
                :value="tab.id"
                class="flex-1 rounded-md py-2 text-sm font-medium transition-colors focus:outline-none focus:ring-2 focus:ring-indigo-500 data-[state=active]:bg-white data-[state=active]:text-indigo-700 dark:data-[state=active]:bg-slate-800 dark:data-[state=active]:text-white data-[state=active]:shadow-sm"
              >
                <i :class="tab.icon" class="mr-2"></i>
                {{ $t(`account.tabs.${tab.id}`) }}
              </Tabs.Trigger>
            </Tabs.List>
          </Tabs.Root>
        </div>

        <!-- Main Content -->
        <div class="lg:col-span-3">
          <NuxtPage />
        </div>
      </div>

  </div>
</template>

<script setup lang="ts">
import { Tabs } from 'reka-ui/namespaced'

const route = useRoute()

const tabs = [
  { id: 'profile', name: 'account-profile', icon: 'fas fa-user', },
  { id: 'appearance', name: 'account-appearance', icon: 'fas fa-palette' },
  { id: 'security', name: 'account-security', icon: 'fas fa-shield-alt' }
]

const currentTab = computed(() => {
  return tabs.find(tab => tab.name === route.name)?.id || 'profile'
})

const handleTabChange = (value: string) => {
  navigateTo(`/account/${value}`)
}

// Redirect to profile if no tab specified
if (route.path === '/account' || route.path === '/account/') {
  await navigateTo('/account/profile')
}

definePageMeta({
  title: 'Account Settings'
})
</script>