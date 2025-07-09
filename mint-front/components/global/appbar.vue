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
          <div>
            <img :src="theme === 'light' ? logoLight : logoDark" class="h-10 w-auto" />
          </div>
          <NuxtLink 
            to="/profile"
            class="flex items-center justify-center p-2 text-gray-600 dark:text-gray-400 hover:text-indigo-600 hover:bg-indigo-50 dark:hover:bg-indigo-900 rounded-lg transition-colors"
            title="Profile"
          >
            <i class="fa fa-user text-lg"></i>
          </NuxtLink>
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

// handle logout
const handleLogout = async () => {
  try {
    await signOut()
  } catch (error) {
    console.error('Logout error:', error)
  }
}
</script>
