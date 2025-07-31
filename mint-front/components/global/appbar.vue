<template>
  <div class="fixed top-0 w-full z-10 pl-4">
    <div class="bg-bg1 dark:bg-bg3 dark:border-b-2 dark:border-bg1  rounded-bl-2xl h-[68px] pr-6 shadow-md">
      <div class="flex items-center justify-between h-full">
        <NuxtLink to="/">
          <div class="flex items-center space-x-2 text-xl text-primary dark:text-white pl-6">
            <i class="fa fa-leaf"></i>
            <h1 class="font-extrabold">MINT</h1>
          </div>
        </NuxtLink>
        <div class="max-w-md grow">
          <!-- <OInput
            id="search"
            :placeholder="t('appbar.search')"
          /> -->
        </div>
        <div class="flex items-center gap-6">
          <!-- Dev mode workspace display -->
          <div v-if="isDev" class="px-3 py-1 bg-primary text-white rounded-full text-sm font-medium">
            <span v-if="workspaceLoading">Loading workspace...</span>
            <span v-else-if="currentWorkspace">{{ currentWorkspace.name }}</span>
            <span v-else>No workspace</span>
          </div>
          
          <div>
            <img :src="theme === 'light' ? logoLight : logoDark" class="h-10 w-auto" />
          </div>
          
          <button
            @click="handleLogout"
            class="flex items-center justify-center p-2 text-gray-600 dark:text-gray-300 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-400/50 rounded-lg transition-colors"
            title="Logout"
          >
            <i class="fa fa-sign-out-alt text-lg"></i>
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script lang="ts" setup>
import logoDark from '@/assets/logo_chaps_white.png'
import logoLight from '@/assets/logo_chaps.png'

const { signOut } = useAuth()

const { theme } = useTheme()

// Check if we're in dev mode
const isDev = computed(() => {
  return useRuntimeConfig().public.isDev
})

// Workspace data
const { fetchCurrentWorkspace } = useWorkspace()
const currentWorkspace = ref(null)
const workspaceLoading = ref(false)

// Fetch workspace on mount
onMounted(async () => {
  if (isDev.value) {
    try {
      const { currentWorkspace: workspace, loading, error } = await fetchCurrentWorkspace()
      currentWorkspace.value = workspace.value
      workspaceLoading.value = loading.value
      
      if (error.value) {
        console.error('Failed to load workspace in appbar:', error.value)
      }
    } catch (error) {
      console.error('Failed to load workspace in appbar:', error)
    }
  }
})

// handle logout
const handleLogout = async () => {
  try {
    await signOut()
  } catch (error) {
    console.error('Logout error:', error)
  }
}
</script>
