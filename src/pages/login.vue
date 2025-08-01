<template>
  <div class="min-h-screen flex items-center justify-center bg-bg3 px-4 sm:px-6 lg:px-8">
    <div class="max-w-md w-full space-y-8">
      <div>
        <div class="flex justify-center">
          <i class="fa-solid fa-leaf text-primary text-7xl"></i>
        </div>
        <h2 class="mt-6 text-center text-3xl font-extrabold">Sign in to your account</h2>
      </div>
      <div class="mt-8">
        <div v-if="error" class="text-red-600 text-sm text-center mb-4">
          {{ error }}
        </div>

        <div>
          <button
            @click="handleLogin"
            :disabled="isLoading"
            class="group relative w-full flex justify-center py-2 px-4 border border-transparent text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed"
          >
            <span v-if="isLoading" class="absolute left-0 inset-y-0 flex items-center pl-3">
              <svg
                class="animate-spin h-5 w-5 text-white"
                xmlns="http://www.w3.org/2000/svg"
                fill="none"
                viewBox="0 0 24 24"
              >
                <circle
                  class="opacity-25"
                  cx="12"
                  cy="12"
                  r="10"
                  stroke="currentColor"
                  stroke-width="4"
                ></circle>
                <path
                  class="opacity-75"
                  fill="currentColor"
                  d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                ></path>
              </svg>
            </span>
            {{ isLoading ? 'Signing in...' : 'Sign in with Keycloak' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { useAuth } from '@/composables/useAuth'
import { ref } from 'vue'

const { signIn } = useAuth()

const error = ref('')
const isLoading = ref(false)

const handleLogin = async () => {
  try {
    isLoading.value = true
    error.value = ''

    // Redirect to Keycloak login
    await signIn()
  } catch (err) {
    console.error('Login error:', err)
    error.value = 'An error occurred during login'
    isLoading.value = false
  }
}
</script>
