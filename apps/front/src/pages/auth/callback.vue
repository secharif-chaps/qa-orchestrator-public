<template>
  <div class="flex min-h-screen items-center justify-center">
    <div class="text-center">
      <div
        v-if="isLoading"
        class="mx-auto h-12 w-12 animate-spin rounded-full border-b-2 border-indigo-600"
      ></div>
      <p v-if="isLoading" class="mt-4 text-gray-600">
        {{ $t('common.auth.callback.processing', 'Processing login...') }}
      </p>
      <div v-if="error" class="text-red-600">
        <p>{{ $t('common.auth.callback.loginFailed', 'Login failed') }}: {{ error }}</p>
        <Button
          @click="$router.push('/login')"
          :label="$t('common.auth.callback.tryAgain', 'Try again')"
          variant="tertiary"
          class="mt-4"
        />
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { Button } from '@owlint/feathers-vue'

const { handleCallback } = useAuthStore()
const router = useRouter()

const isLoading = ref(true)
const error = ref<string | null>(null)

onMounted(async () => {
  try {
    await handleCallback()

    // Get intended redirect from URL state or default to home
    const urlParams = new URLSearchParams(window.location.search)
    const state = urlParams.get('state')
    let redirectTo = '/'

    // If state contains redirect info, parse it
    if (state) {
      try {
        const stateData = JSON.parse(atob(state))
        redirectTo = stateData.redirect || '/'
      } catch {
        // If state parsing fails, use default
        redirectTo = '/'
      }
    }

    // Redirect to intended route or home
    await router.push(redirectTo)
  } catch (err) {
    console.error('Callback error:', err)
    error.value = err instanceof Error ? err.message : 'Unknown error'
  } finally {
    isLoading.value = false
  }
})
</script>
