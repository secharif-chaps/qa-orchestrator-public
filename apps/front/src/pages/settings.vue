<template>
  <div class="flex flex-col gap-6">
    <!-- Header with Tab Navigation -->
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
      <div>
        <h1 class="text-2xl font-bold">{{ $t('settings.title') }}</h1>
        <p class="text-secondary mt-1">{{ $t('settings.description') }}</p>
      </div>

      <!-- Navigation Tabs (only show when on a subpage) -->
      <div v-if="isOnSubpage" class="flex items-center">
        <Tab :tabs="tabOptions" />
      </div>
    </div>

    <!-- Main Content -->
    <div v-if="isOnSubpage">
      <RouterView />
    </div>

    <!-- Settings Index (cards linking to sections) -->
    <div v-else class="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3">
      <RouterLink
        v-for="section in sections"
        :key="section.id"
        :to="`/settings/${section.id}`"
        class="group bg-base-100 border-primary-stroke rounded-card hover:border-primary/50 hover:shadow-shadow-2 flex h-full flex-col border p-6 transition-all duration-200"
      >
        <!-- Icon -->
        <div
          class="flex h-12 w-12 items-center justify-center rounded-lg transition-colors"
          :class="section.bgColor"
        >
          <i :class="[section.icon, 'text-xl', section.iconColor]"></i>
        </div>

        <!-- Content -->
        <div class="mt-4 flex flex-1 flex-col gap-2">
          <h3 class="group-hover:text-primary text-lg font-semibold transition-colors">
            {{ section.title }}
          </h3>
          <p class="text-secondary text-sm">
            {{ section.description }}
          </p>
        </div>

        <!-- Arrow - Always at bottom -->
        <div
          class="text-secondary group-hover:text-primary border-primary-stroke/50 mt-4 flex items-center border-t pt-4 transition-colors"
        >
          <span class="text-sm font-medium">{{ $t('settings.viewSection') }}</span>
          <i
            class="fas fa-arrow-right ml-2 transform text-xs transition-transform group-hover:translate-x-1"
          ></i>
        </div>
      </RouterLink>
    </div>
  </div>
</template>

<script setup lang="ts">
import { Tab } from '@owlint/feathers-vue'
import { computed } from 'vue'
import { RouterView, RouterLink, useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '@/stores/auth'

const route = useRoute()
const router = useRouter()
const { t } = useI18n()
const authStore = useAuthStore()

// Check if user can manage organization (for credits tab)
const canManageOrganization = computed(() => authStore.hasPermission('organization.manage'))

// Section definitions
const sections = computed(() => {
  const baseSections = [
    {
      id: 'appearance',
      title: t('settings.tabs.appearance'),
      description: t('settings.appearance.theme.description'),
      icon: 'fas fa-palette',
      bgColor: 'bg-primary-light',
      iconColor: 'text-primary-light-content',
    },
    {
      id: 'ai-preferences',
      title: t('settings.tabs.ai-preferences'),
      description: t('settings.aiPreferences.settings.description'),
      icon: 'fas fa-magic',
      bgColor: 'bg-accent-light',
      iconColor: 'text-accent-light-content',
    },
    {
      id: 'security',
      title: t('settings.tabs.security'),
      description: t('settings.security.sessions.description'),
      icon: 'fas fa-shield-alt',
      bgColor: 'bg-success-light',
      iconColor: 'text-success-light-content',
    },
    {
      id: 'team-management',
      title: t('settings.tabs.team'),
      description: t('settings.team.cardDescription'),
      icon: 'fas fa-users',
      bgColor: 'bg-warning-light',
      iconColor: 'text-warning-light-content',
    },
  ]

  // Add credits section for managers
  if (canManageOrganization.value) {
    baseSections.push({
      id: 'credits',
      title: t('settings.tabs.credits'),
      description: t('settings.credits.cardDescription'),
      icon: 'fas fa-coins',
      bgColor: 'bg-info-light',
      iconColor: 'text-info-light-content',
    })
  }

  return baseSections
})

// Tab options for navigation
const tabOptions = computed(() => {
  const currentPath = route.path
  const navigateTo = (id: string) => router.push(`/settings/${id}`)

  const baseOptions = [
    {
      id: 'appearance',
      icon: 'fas fa-palette',
      title: t('settings.tabs.appearance'),
      isActive: currentPath.includes('/appearance'),
      click: () => navigateTo('appearance'),
    },
    {
      id: 'ai-preferences',
      icon: 'fas fa-magic',
      title: t('settings.tabs.ai-preferences'),
      isActive: currentPath.includes('/ai-preferences'),
      click: () => navigateTo('ai-preferences'),
    },
    {
      id: 'security',
      icon: 'fas fa-shield-alt',
      title: t('settings.tabs.security'),
      isActive: currentPath.includes('/security'),
      click: () => navigateTo('security'),
    },
    {
      id: 'team-management',
      icon: 'fas fa-users',
      title: t('settings.tabs.team'),
      isActive: currentPath.includes('/team-management'),
      click: () => navigateTo('team-management'),
    },
  ]

  // Add credits option for managers
  if (canManageOrganization.value) {
    baseOptions.push({
      id: 'credits',
      icon: 'fas fa-coins',
      title: t('settings.tabs.credits'),
      isActive: currentPath.includes('/credits'),
      click: () => navigateTo('credits'),
    })
  }

  return baseOptions
})

// Determine if we're on a subpage
const isOnSubpage = computed(() => {
  const path = route.path
  return path !== '/settings' && path !== '/settings/'
})
</script>
