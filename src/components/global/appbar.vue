<template>
  <div class="w-full z-20">
    <div class="dark bg-sage-950 fixed top-0 w-full z-20 h-[68px] pr-6">
      <div class="flex items-center justify-between h-full">
        <RouterLink to="/">
          <div
            class="flex items-center space-x-2 text-xl text-sage-200 dark:text-white pl-6 relative"
          >
            <img :src="logo_small" class="!h-10 !w-auto" />
            <h1>
              <span> ChapsMind </span>
            </h1>
          </div>
        </RouterLink>
        <div class="max-w-md grow"></div>
        <div class="flex items-center gap-4">
          <!-- Module badges -->
          <ModuleBadges v-if="organization && !isLoading" :organization-id="organization.id" />

          <!-- <div>
            <img :src="logo" class="!h-10 !w-auto" />
          </div> -->

          <!-- Dev mode only theme toggle -->
          <VButton
            v-if="isDebugUser"
            variant="tertiary"
            :icon="isDark ? 'fa fa-sun' : 'fa fa-moon'"
            @click="toggleTheme"
          />

          <!-- Dev mode only language toggle -->
          <VButton
            v-if="isDebugUser"
            variant="tertiary"
            icon="fa fa-language"
            @click="toggleLocale"
          />

          <!-- Admin button - only visible to users with admin.organizations permission -->
          <VButton
            v-if="hasAdminPermission"
            variant="tertiary"
            icon="fa fa-shield"
            @click="$router.push('/admin')"
          />

          <VButton
            variant="tertiary"
            icon="fa fa-arrow-right-from-bracket"
            @click="handleLogout"
          />

          <div class="w-px h-4 bg-sage-600 dark:bg-sage-400"></div>

          <Button
            :variant="isTokensActive ? 'accent' : 'tertiary'"
            dark
            :icon="'fa fa-circle-dollar'"
            icon-only
            @click="toggleTokens"
          />
          <Button
            :variant="isChaapseActive ? 'accent' : 'tertiary'"
            dark
            :icon="'fa fa-robot'"
            icon-only
            @click="toggleChapse"
          />

          <Button
            :variant="isNotificationsActive ? 'accent' : 'tertiary'"
            dark
            :icon="'fa fa-bell'"
            icon-only
            @click="toggleNotifications"
          />
          <Button
            :variant="isFoldersActive ? 'accent' : 'tertiary'"
            dark
            :icon="'fa fa-grip-lines'"
            icon-only
            @click="toggleFolders"
          />
        </div>
      </div>
    </div>
  </div>
</template>

<script lang="ts" setup>
import logo_small from '@/assets/CHAPSVISION_LOGO_ChapsVision_logo_icone_amande.svg'
import { useTheme } from '@/composables/useTheme'
import { useAuthStore } from '@/stores/auth'
import { useSidebarStore } from '@/stores/sidebar'
import { Button as VButton } from '@owlint/feathers-vue'
import Button from '@/components/ui/Button.vue'
import { useQuery } from '@pinia/colada'
import { currentOrganizationQuery } from '@/queries/organization'
import ModuleBadges from '@/components/global/ModuleBadges.vue'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

const authStore = useAuthStore()
const { signOut } = authStore
const sidebarStore = useSidebarStore()

const { isDark, setTheme } = useTheme()
const { locale } = useI18n()

// Permission checks for navigation buttons
const hasAdminPermission = computed(() => authStore.hasPermission('admin.organizations'))

const isDebugUser = computed(() => {
  const username = authStore.user?.profile?.preferred_username?.toLowerCase()
  return username === 'nmr' || username === 'suh' || username === 'nmr-cv'
})

// Theme toggle function
const toggleTheme = () => {
  setTheme(isDark.value ? 'light' : 'dark')
}

// Language toggle function
const toggleLocale = () => {
  locale.value = locale.value === 'en-US' ? 'fr-FR' : 'en-US'
}

// Fetch current organization
const { data: organization, isLoading } = useQuery(currentOrganizationQuery, () => ({}))

// Sidebar toggle handlers
const isTokensActive = computed(() => sidebarStore.state === 'tokens')
const isChaapseActive = computed(() => sidebarStore.state === 'chapse')
const isNotificationsActive = computed(() => sidebarStore.state === 'notifications')
const isFoldersActive = computed(() => sidebarStore.state === 'folders')

const toggleTokens = () => sidebarStore.toggleState('tokens')
const toggleChapse = () => sidebarStore.toggleState('chapse')
const toggleNotifications = () => sidebarStore.toggleState('notifications')
const toggleFolders = () => sidebarStore.toggleState('folders')

// handle logout
const handleLogout = async () => {
  try {
    await signOut()
  } catch (error) {
    console.error('Logout error:', error)
  }
}
</script>
