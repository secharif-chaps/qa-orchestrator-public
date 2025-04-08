<template>
  <div class="min-h-screen flex items-center justify-center bg-gray-50 py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-md w-full space-y-8">
      <div>
        <h2 class="mt-6 text-center text-3xl font-extrabold text-gray-900">
          Connexion à votre compte
        </h2>
      </div>
      <form class="mt-8 space-y-6" @submit.prevent="handleLogin">
        <div class="rounded-md shadow-sm -space-y-px">
          <div>
            <label for="email" class="sr-only">Email</label>
            <OInput
              id="email"
              v-model="email"
              type="email"
              required
              class="appearance-none rounded-none relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-t-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 focus:z-10 sm:text-sm"
              placeholder="Email"
            />
          </div>
          <div>
            <label for="password" class="sr-only">Mot de passe</label>
            <OInput
              id="password"
              v-model="password"
              type="password"
              required
              class="appearance-none rounded-none relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-b-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 focus:z-10 sm:text-sm"
              placeholder="Mot de passe"
            />
          </div>
        </div>

        <div class="flex items-center justify-between">
          <div class="text-sm">
            <NuxtLink to="/auth/forgot-password" class="font-medium text-indigo-600 hover:text-indigo-500">
              Mot de passe oublié ?
            </NuxtLink>
          </div>
        </div>

        <div>
          <OButton
            type="submit"
            :loading="loading"
            class="group relative w-full flex justify-center py-2 px-4 border border-transparent text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
          >
            Se connecter
          </OButton>
        </div>

        <div class="text-center">
          <p class="text-sm text-gray-600">
            Pas encore de compte ?
            <NuxtLink to="/auth/signup" class="font-medium text-indigo-600 hover:text-indigo-500">
              S'inscrire
            </NuxtLink>
          </p>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { useAuth } from '#imports'
import { useRouter, useRoute } from 'vue-router'

const router = useRouter()
const route = useRoute()
const { signIn } = useAuth()

const email = ref('')
const password = ref('')
const loading = ref(false)

const handleLogin = async () => {
  try {
    loading.value = true
    await signIn({
      email: email.value,
      password: password.value
    })
    
    // Rediriger vers la page mémorisée ou le dashboard
    const redirectTo = route.query.redirect as string || '/dashboard'
    router.push(redirectTo)
  } catch (error) {
    console.error('Erreur de connexion:', error)
    // TODO: Afficher un message d'erreur à l'utilisateur
  } finally {
    loading.value = false
  }
}
</script> 