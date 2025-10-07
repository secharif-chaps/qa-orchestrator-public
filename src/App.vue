<template>
  <div>
    <!-- Show loading state while auth is initializing -->
    <AuthLoader v-if="!authStore.initialized" />

    <!-- Show layouts based on auth state once initialized -->
    <template v-else>
      <DefaultLayout v-if="isAuthenticated">
        <RouterView />
      </DefaultLayout>

      <UnauthenticatedLayout v-else>
        <RouterView />
      </UnauthenticatedLayout>
    </template>
  </div>
  <PiniaColadaDevtools class="fixed !bottom-0 !left-0" position="bottom-left" />
</template>

<script setup lang="ts">
import { PiniaColadaDevtools } from '@pinia/colada-devtools'
import { computed, onMounted, watch } from 'vue'
import DefaultLayout from './layouts/DefaultLayout.vue'
import UnauthenticatedLayout from './layouts/UnauthenticatedLayout.vue'
import AuthLoader from './components/ui/AuthLoader.vue'
import { useAuthStore } from './stores/auth'
import { useSidebarStore } from './stores/sidebar'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'

const authStore = useAuthStore()
const sidebarStore = useSidebarStore()

const STORAGE_KEY = 'user-locale'
const { locale } = useI18n()

// Initialize auth store on app startup
onMounted(async () => {
  await authStore.initialize()

  // Load sidebar state from localStorage
  sidebarStore.loadState()

  if (typeof localStorage !== 'undefined') {
    const savedLocale = localStorage.getItem(STORAGE_KEY)
    if (savedLocale) {
      locale.value = savedLocale
    }
    // Load saved accent color
    const savedAccent = localStorage.getItem('accent-color')
    if (savedAccent) {
      document.documentElement.setAttribute('data-theme', savedAccent)
    }
  }
})

const router = useRouter()

const isAuthenticated = computed(() => authStore.isAuthenticated)

watch(
  () => authStore.isAuthenticated,
  (newIsAuthenticated) => {
    if (!newIsAuthenticated) {
      // redirect to login page
      router.push('/login')
    }
  },
)
</script>

<style>
/* Force Pinia Colada devtools to bottom-left */
:deep(.pinia-colada-devtools-button) {
  right: auto !important;
  left: 16px !important;
  bottom: 16px !important;
}

#open-devtools-button {
  right: auto !important;
  left: 16px !important;
  bottom: 16px !important;
}
</style>
