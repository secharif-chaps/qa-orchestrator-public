<template>
  <div class="z-20 w-full">
    <div class="fixed top-0 z-20 h-[68px] w-full pr-6">
      <div class="flex h-full w-full items-center gap-3">
        <RouterLink to="/">
          <div
            class="text-sage-200 relative flex items-center space-x-2 pl-6 text-xl dark:text-white"
          >
            <img :src="logo" class="h-10! w-auto!" />
          </div>
        </RouterLink>
        <div class="flex grow gap-3">
          <div class="bg-sage-300 h-8 w-px" />
          <Breadcrumbs class="grow" />
        </div>

        <div class="flex items-center gap-3">
          <!-- Module badges -->
          <ModuleBadges v-if="organization && !isOrgLoading" :organization-id="organization.id" />

          <!-- Dev mode only theme toggle -->
          <Button
            v-if="isDebugUser"
            variant="tertiary"
            size="sm"
            :icon="isDark ? 'fa-sun' : 'fa-moon'"
            :title="$t('settings.appearance.theme.title')"
            @click="toggleTheme"
          />

          <UserMenu />
          <div class="bg-sage-300 h-4.5 w-px rounded-full" />
          <AppBarNav />
        </div>
      </div>
    </div>
  </div>
</template>

<script lang="ts" setup>
import logo_dark from '@/assets/CHAPSVISION_LOGO_DARK.svg'
import logo_light from '@/assets/CHAPSVISION_LOGO_LIGHT.svg'
import AppBarNav from '@/components/global/AppBarNav.vue'
import ModuleBadges from '@/components/global/ModuleBadges.vue'
import UserMenu from '@/components/global/UserMenu.vue'
import { useTheme } from '@/composables/useTheme'
import { currentOrganizationQuery } from '@/queries/organization'
import { useAuthStore } from '@/stores/auth'
import { useQuery } from '@pinia/colada'
import { Button } from '@owlint/feathers-vue'
import { computed } from 'vue'
import Breadcrumbs from '../ui/Breadcrumbs.vue'

const authStore = useAuthStore()
const { isDark, setTheme } = useTheme()

const logo = computed(() => (isDark.value ? logo_dark : logo_light))

const { data: organization, isLoading: isOrgLoading } = useQuery(() => currentOrganizationQuery())

const isDebugUser = computed(() => {
  const username = authStore.user?.profile?.preferred_username?.toLowerCase()
  return username === 'nmr' || username === 'suh' || username === 'nmr-cv'
})

const toggleTheme = () => {
  setTheme(isDark.value ? 'light' : 'dark')
}
</script>
