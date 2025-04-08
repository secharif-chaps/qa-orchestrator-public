<template>
  <div class="min-h-screen bg-gray-50">
    <!-- Appbar -->
    <header class="bg-white shadow-sm">
      <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="flex h-16 justify-between">
          <div class="flex">
            <!-- Logo -->
            <div class="flex flex-shrink-0 items-center">
              <img class="h-8 w-auto" src="/logo.png" alt="Logo" />
            </div>
          </div>
          
          <!-- User menu -->
          <div class="flex items-center">
            <div class="relative ml-3">
              <div class="flex items-center">
                <img
                  class="h-8 w-8 rounded-full"
                  :src="user?.avatar || 'https://via.placeholder.com/32'"
                  alt=""
                />
                <span class="ml-2 text-sm font-medium text-gray-700">{{ user?.firstName }} {{ user?.lastName }}</span>
                <button
                  type="button"
                  class="ml-2 flex rounded-full bg-white text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                  @click="isMenuOpen = !isMenuOpen"
                >
                  <span class="sr-only">Ouvrir le menu utilisateur</span>
                  <i class="fas fa-chevron-down text-gray-400"></i>
                </button>
              </div>
              
              <!-- Menu déroulant -->
              <div
                v-if="isMenuOpen"
                class="absolute right-0 z-10 mt-2 w-48 origin-top-right rounded-md bg-white py-1 shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none"
                role="menu"
                aria-orientation="vertical"
                aria-labelledby="user-menu-button"
                tabindex="-1"
              >
                <button
                  class="flex w-full items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100"
                  role="menuitem"
                  tabindex="-1"
                  @click="handleLogout"
                >
                  <i class="fas fa-sign-out-alt mr-2 text-gray-400"></i>
                  Se déconnecter
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </header>

    <!-- Main content -->
    <div class="flex">
      <!-- Sidebar -->
      <div class="w-64 bg-white shadow-sm">
        <nav class="mt-5 px-2">
          <NuxtLink
            to="/dashboard"
            class="group flex items-center px-2 py-2 text-base font-medium rounded-md text-gray-600 hover:bg-gray-50 hover:text-gray-900"
            :class="{ 'bg-gray-100 text-gray-900': $route.path === '/dashboard' }"
          >
            <i class="fas fa-home mr-3"></i>
            Dashboard
          </NuxtLink>
          <!-- Add more navigation items here -->
        </nav>
      </div>

      <!-- Page content -->
      <main class="flex-1 py-6">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
          <slot />
        </div>
      </main>
    </div>
  </div>
</template>

<script setup lang="ts">
import { useAuth } from '#imports'
import { useAuthStore } from '~/stores/auth'
import { useRouter } from 'vue-router'

const { user } = useAuth()
const authStore = useAuthStore()
const router = useRouter()
const isMenuOpen = ref(false)

// Fermer le menu quand on clique en dehors
onClickOutside(document.body, () => {
  isMenuOpen.value = false
})

const handleLogout = async () => {
  try {
    await authStore.logout()
    router.push('/auth/login')
  } catch (error) {
    console.error('Erreur lors de la déconnexion:', error)
  }
}
</script> 