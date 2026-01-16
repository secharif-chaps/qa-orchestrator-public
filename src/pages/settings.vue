<template>
  <div class="flex flex-col gap-6">
    <!-- Header with Toggle Navigation -->
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
      <div>
        <h1 class="text-2xl font-bold">{{ $t('settings.title') }}</h1>
        <p class="text-secondary mt-1">{{ $t('settings.description') }}</p>
      </div>

      <!-- Navigation Toggle (only show when on a subpage) -->
      <div v-if="isOnSubpage" class="flex items-center">
        <Toggle  v-model="currentSection" :options="sectionOptions" />
      </div>
    </div>

    <!-- Main Content -->
    <div v-if="isOnSubpage">
      <RouterView />
    </div>

    <!-- Settings Index (cards linking to sections) -->
    <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
      <RouterLink
        v-for="section in sections"
        :key="section.id"
        :to="`/settings/${section.id}`"
        class="group bg-base-100 border border-primary-stroke rounded-card p-6 hover:border-primary/50 hover:shadow-shadow-2 transition-all duration-200 flex flex-col h-full"
      >
        <!-- Icon -->
        <div
          class="w-12 h-12 rounded-lg flex items-center justify-center transition-colors"
          :class="section.bgColor"
        >
          <i :class="[section.icon, 'text-xl', section.iconColor]"></i>
        </div>

        <!-- Content -->
        <div class="flex flex-col gap-2 mt-4 flex-1">
          <h3 class="text-lg font-semibold group-hover:text-primary transition-colors">
            {{ section.title }}
          </h3>
          <p class="text-sm text-secondary">
            {{ section.description }}
          </p>
        </div>

        <!-- Arrow - Always at bottom -->
        <div class="flex items-center text-secondary group-hover:text-primary transition-colors mt-4 pt-4 border-t border-primary-stroke/50">
          <span class="text-sm font-medium">{{ $t('settings.viewSection', 'Configure') }}</span>
          <i class="fas fa-arrow-right ml-2 text-xs transform group-hover:translate-x-1 transition-transform"></i>
        </div>
      </RouterLink>
    </div>
  </div>
</template>

<script setup lang="ts">
import { Toggle } from '@owlint/feathers-vue'
import { computed, watch } from 'vue'
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
      description: t('aiPreferences.settings.description'),
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
      title: t('settings.tabs.team', 'Team Management'),
      description: t('settings.team.cardDescription', 'Manage team members and permissions'),
      icon: 'fas fa-users',
      bgColor: 'bg-warning-light',
      iconColor: 'text-warning-light-content',
    },
  ]

  // Add credits section for managers
  if (canManageOrganization.value) {
    baseSections.push({
      id: 'credits',
      title: t('settings.tabs.credits', 'Crédits'),
      description: t('settings.credits.cardDescription', 'View credit usage and statistics'),
      icon: 'fas fa-coins',
      bgColor: 'bg-info-light',
      iconColor: 'text-info-light-content',
    })
  }

  return baseSections
})

// Toggle options for navigation
const sectionOptions = computed(() => {
  const baseOptions = [
    {
      value: 'appearance',
      icon: 'fas fa-palette',
      label: t('settings.tabs.appearance'),
    },
    {
      value: 'ai-preferences',
      icon: 'fas fa-magic',
      label: t('settings.tabs.ai-preferences'),
    },
    {
      value: 'security',
      icon: 'fas fa-shield-alt',
      label: t('settings.tabs.security'),
    },
    {
      value: 'team-management',
      icon: 'fas fa-users',
      label: t('settings.tabs.team', 'Team Management'),
    },
  ]

  // Add credits option for managers
  if (canManageOrganization.value) {
    baseOptions.push({
      value: 'credits',
      icon: 'fas fa-coins',
      label: t('settings.tabs.credits', 'Crédits'),
    })
  }

  return baseOptions
})

// Determine if we're on a subpage
const isOnSubpage = computed(() => {
  const path = route.path
  return path !== '/settings' && path !== '/settings/'
})

// Current section based on route
const currentSection = computed({
  get: () => {
    const path = route.path
    if (path.includes('/appearance')) return 'appearance'
    if (path.includes('/ai-preferences')) return 'ai-preferences'
    if (path.includes('/security')) return 'security'
    if (path.includes('/team-management')) return 'team-management'
    if (path.includes('/credits')) return 'credits'
    return 'appearance'
  },
  set: (value: string) => {
    router.push(`/settings/${value}`)
  },
})
</script>
