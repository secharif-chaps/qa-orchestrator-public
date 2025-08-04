<template>
  <div>
    <!-- Show loading state while auth is initializing -->
    <AuthLoader v-if="!authStore.initialized" />

    <!-- Show layouts based on auth state once initialized -->
    <template v-else>
      <DefaultLayout v-if="authStore.isAuthenticated">
        <RouterView />
      </DefaultLayout>

      <UnauthenticatedLayout v-else>
        <RouterView />
      </UnauthenticatedLayout>
    </template>
  </div>
  <PiniaColadaDevtools />
</template>

<script setup lang="ts">
import { PiniaColadaDevtools } from '@pinia/colada-devtools'
import { onMounted, watch } from 'vue'
import DefaultLayout from './layouts/DefaultLayout.vue'
import UnauthenticatedLayout from './layouts/UnauthenticatedLayout.vue'
import AuthLoader from './components/ui/AuthLoader.vue'
import { useAuthStore } from './stores/auth'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'

const authStore = useAuthStore()

const STORAGE_KEY = 'user-locale'
const { locale } = useI18n()

// Initialize auth store on app startup
onMounted(async () => {
  await authStore.initialize()

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

watch(
  () => authStore.isAuthenticated,
  (newIsAuthenticated) => {
    if (!newIsAuthenticated) {
      // redirect to login page
      routpush('/login')
    }
  },
)
</script>
