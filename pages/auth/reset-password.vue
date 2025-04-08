<template>
  <div class="min-h-screen flex items-center justify-center bg-gray-50 py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-md w-full space-y-8">
      <div>
        <h2 class="mt-6 text-center text-3xl font-extrabold text-gray-900">
          Réinitialiser votre mot de passe
        </h2>
      </div>
      <form class="mt-8 space-y-6" @submit.prevent="handleResetPassword">
        <div class="rounded-md shadow-sm -space-y-px">
          <div>
            <label for="password" class="sr-only">Nouveau mot de passe</label>
            <OInput
              id="password"
              v-model="password"
              type="password"
              required
              class="appearance-none rounded-none relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-t-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 focus:z-10 sm:text-sm"
              placeholder="Nouveau mot de passe"
            />
          </div>
          <div>
            <label for="confirmPassword" class="sr-only">Confirmer le mot de passe</label>
            <OInput
              id="confirmPassword"
              v-model="confirmPassword"
              type="password"
              required
              class="appearance-none rounded-none relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-b-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 focus:z-10 sm:text-sm"
              placeholder="Confirmer le mot de passe"
            />
          </div>
        </div>

        <div>
          <OButton
            type="submit"
            :loading="loading"
            class="group relative w-full flex justify-center py-2 px-4 border border-transparent text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
          >
            Réinitialiser le mot de passe
          </OButton>
        </div>

        <div class="text-center">
          <NuxtLink to="/auth/login" class="font-medium text-indigo-600 hover:text-indigo-500">
            Retour à la connexion
          </NuxtLink>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import { useAuth } from '#imports'
import { useRouter, useRoute } from 'vue-router'

const router = useRouter()
const route = useRoute()
const { resetPassword } = useAuth()

const password = ref('')
const confirmPassword = ref('')
const loading = ref(false)

const isValid = computed(() => {
  return password.value === confirmPassword.value && password.value.length >= 8
})

const handleResetPassword = async () => {
  if (!isValid.value) {
    // TODO: Afficher un message d'erreur
    return
  }

  try {
    loading.value = true
    const token = route.query.token as string
    
    if (!token) {
      throw new Error('Token de réinitialisation manquant')
    }

    await resetPassword({
      token,
      password: password.value
    })
    
    // Rediriger vers la page de connexion
    router.push('/auth/login')
  } catch (error) {
    console.error('Erreur:', error)
    // TODO: Afficher un message d'erreur à l'utilisateur
  } finally {
    loading.value = false
  }
}
</script> 