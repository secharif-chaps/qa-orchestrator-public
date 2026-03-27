<template>
  <div class="bg-base-300 flex min-h-screen items-center justify-center px-4 sm:px-6 lg:px-8">
    <div class="w-full max-w-md space-y-8">
      <div>
        <div class="flex justify-center">
          <i class="fa-solid fa-leaf text-secondary text-7xl"></i>
        </div>
        <h2 class="mt-6 text-center text-3xl font-extrabold">
          {{ $t('common.login.heading', 'Sign in to your account') }}
        </h2>
      </div>
      <div class="mt-8 space-y-4">
        <div v-if="error" class="mb-4 text-center text-sm text-red-600">
          {{ error }}
        </div>

        <div>
          <Button
            @click="handleLogin"
            :disabled="isLoading"
            :label="
              isLoading
                ? $t('common.login.signingIn', 'Signing in...')
                : $t('common.login.signInButton', 'Sign in with Keycloak')
            "
            :loading="isLoading"
            variant="primary"
            class="w-full"
          />
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { useAuth } from '@/composables/useAuth'
import { ref } from 'vue'
import { Button } from '@owlint/feathers-vue'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()
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
    error.value = t('common.login.errors.genericError', 'An error occurred during login')
    isLoading.value = false
  }
}
</script>
