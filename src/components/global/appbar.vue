<template>
  <div class="fixed top-0 w-full z-10 pl-4">
    <div
      class="bg-bg1 dark:bg-bg3 dark:border-b-2 dark:border-bg1 rounded-bl-2xl h-[68px] pr-6 shadow-md"
    >
      <div class="flex items-center justify-between h-full">
        <RouterLink to="/">
          <div class="flex items-center space-x-2 text-xl text-primary dark:text-white pl-6">
            <i class="fas fa-leaf"></i>
            <h1 class="font-extrabold">Mint</h1>
          </div>
        </RouterLink>
        <div class="max-w-md grow">
          <!-- <OInput
            id="search"
            :placeholder="t('appbar.search')"
          /> -->
        </div>
        <div class="flex items-center gap-6">
          <!-- Workspace Badge -->
          <div v-if="workspace && !isLoading" class="flex items-center gap-2">
            <i class="fas fa-building text-secondary"></i>
            <OBadge color="primary" size="md">
              {{ workspace.name }}
            </OBadge>
          </div>
          
          <div>
            <img :src="theme === 'light' ? logoLight : logoDark" class="!h-10 !w-auto" />
          </div>

          <OButton type="tertiary" color="red" @click="handleLogout">
            <i class="fas fa-arrow-right-from-bracket"></i>
          </OButton>
        </div>
      </div>
    </div>
  </div>
</template>

<script lang="ts" setup>
import logoLight from '@/assets/logo_chaps.png'
import logoDark from '@/assets/logo_chaps_white.png'
import { useTheme } from '@/composables/useTheme'
import { useAuthStore } from '@/stores/auth'
import { OButton, OBadge } from '@owlint/feathers-vue'
import { useQuery } from '@pinia/colada'
import { currentWorkspaceQuery } from '@/queries/workspace'

const { signOut } = useAuthStore()

const { theme } = useTheme()

// Fetch current workspace
const { data: workspace, isLoading } = useQuery(currentWorkspaceQuery())

// handle logout
const handleLogout = async () => {
  try {
    await signOut()
  } catch (error) {
    console.error('Logout error:', error)
  }
}
</script>
