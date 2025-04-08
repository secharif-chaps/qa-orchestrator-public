<template>
  <div class="min-h-screen flex items-center justify-center bg-gray-50 py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-md w-full space-y-8">
      <div>
        <h2 class="mt-6 text-center text-3xl font-extrabold text-gray-900">
          Mot de passe oublié
        </h2>
        <p class="mt-2 text-center text-sm text-gray-600">
          Entrez votre email pour recevoir un lien de réinitialisation
        </p>
      </div>
      <form class="mt-8 space-y-6" @submit.prevent="handleForgotPassword">
        <div>
          <label for="email" class="sr-only">Email</label>
          <OInput
            id="email"
            v-model="email"
            type="email"
            required
            class="appearance-none rounded relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 focus:z-10 sm:text-sm"
            placeholder="Email"
          />
        </div>

        <div>
          <OButton
            type="submit"
            :loading="loading"
            class="group relative w-full flex justify-center py-2 px-4 border border-transparent text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
          >
            Envoyer le lien
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
import { ref } from 'vue'
import { useAuth } from '#imports'

const { forgotPassword } = useAuth()

const email = ref('')
const loading = ref(false)

const handleForgotPassword = async () => {
  try {
    loading.value = true
    await forgotPassword({
      email: email.value
    })
    
    // TODO: Afficher un message de succès
    console.log('Email de réinitialisation envoyé')
  } catch (error) {
    console.error('Erreur:', error)
    // TODO: Afficher un message d'erreur à l'utilisateur
  } finally {
    loading.value = false
  }
}
</script> 