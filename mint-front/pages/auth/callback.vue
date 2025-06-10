<template>
  <div class="min-h-screen flex items-center justify-center">
    <div class="text-center">
      <div v-if="isLoading" class="animate-spin rounded-full h-12 w-12 border-b-2 border-indigo-600 mx-auto"></div>
      <p v-if="isLoading" class="mt-4 text-gray-600">Processing login...</p>
      <div v-if="error" class="text-red-600">
        <p>Login failed: {{ error }}</p>
        <button @click="$router.push('/login')" class="mt-4 text-indigo-600 hover:text-indigo-800">
          Try again
        </button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
definePageMeta({
  layout: 'unauthenticated',
  auth: false
})

const { handleCallback } = useAuth()
const router = useRouter()

const isLoading = ref(true)
const error = ref<string | null>(null)

onMounted(async () => {
  try {
    await handleCallback()
    // Redirect to home page after successful login
    await router.push('/')
  } catch (err) {
    console.error('Callback error:', err)
    error.value = err instanceof Error ? err.message : 'Unknown error'
  } finally {
    isLoading.value = false
  }
})
</script>