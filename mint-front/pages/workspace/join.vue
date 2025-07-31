

<template>
  <div class="min-h-screen flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-md w-full space-y-8">
      <div class="text-center">
        <div class="mx-auto h-12 w-12 bg-blue-100 rounded-full flex items-center justify-center">
          <i class="fas fa-building text-blue-600 text-xl"></i>
        </div>
        <h2 class="mt-6 text-3xl font-extrabold text-gray-900">
          Join Workspace
        </h2>
        <p class="mt-2 text-sm text-gray-600">
          You need to join a workspace to access the application
        </p>
      </div>
      
      <div class="mt-8 space-y-6">
        <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-200">
          <div class="text-center">
            <h3 class="text-lg font-medium text-gray-900 mb-2">
              ChapsVision Workspace
            </h3>
            <p class="text-sm text-gray-600 mb-6">
              Join the default workspace to start using the application
            </p>
            
            <OButton 
              :loading="isJoining"
              @click="handleJoinWorkspace"
              type="primary"
              size="lg"
              class="w-full"
            >
              <i class="fas fa-plus mr-2"></i>
              Join Workspace
            </OButton>
          </div>
        </div>
        
        <OAlert 
          v-if="error"
          type="error"
          :message="error"
          class="mt-4"
        />
        
        <OAlert 
          v-if="success"
          type="success"
          message="Successfully joined workspace! Redirecting..."
          class="mt-4"
        />
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { OButton, OAlert } from '@owlint/feathers-vue'

const { joinWorkspace } = useWorkspace()

const isJoining = ref(false)
const success = ref(false)
const error = ref<string | null>(null)

const handleJoinWorkspace = async () => {
  try {
    isJoining.value = true
    error.value = null
    
    const result = await joinWorkspace()
    
    if (result.error.value) {
      error.value = result.error.value.data?.detail || 'Failed to join workspace'
    } else {
      success.value = true
      await navigateTo('/companies')
    }
  } catch (err) {
    error.value = 'An unexpected error occurred'
  } finally {
    isJoining.value = false
  }
}
</script>