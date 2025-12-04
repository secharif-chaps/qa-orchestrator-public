<template>
  <div class="space-y-6">
    <!-- Loading State -->
    <div v-if="pending" class="flex justify-center items-center py-12">
      <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-indigo-600"></div>
    </div>

    <!-- Error State -->
    <div
      v-else-if="error"
      class="bg-red-50 dark:bg-red-900 border border-red-200 dark:border-red-800 rounded-lg p-6"
    >
      <div class="flex">
        <div class="flex-shrink-0">
          <i class="fas fa-exclamation-circle text-red-400"></i>
        </div>
        <div class="ml-3">
          <h3 class="text-sm font-medium text-red-800">{{ $t('settings.profile.error.title') }}</h3>
          <div class="mt-2 text-sm text-red-700">
            <p>{{ error }}</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Profile Content -->
    <template v-else-if="user">
      <!-- Basic Information -->
      <BasicInformationSection :user="user" />

      <!-- Profile Actions -->
      <ProfileActionsSection
        :refreshing="refreshing"
        @refresh-user="refreshUser"
        @sign-out="handleSignOut"
      />
    </template>
  </div>
</template>

<script setup lang="ts">
import BasicInformationSection from '@/components/settings/profile/BasicInformationSection.vue'
import ProfileActionsSection from '@/components/settings/profile/ProfileActionsSection.vue'
import { useAuth } from '@/composables/useAuth'
import { ref, onMounted } from 'vue'

const { user, getUser, signOut } = useAuth()

const refreshing = ref(false)

// Reactive states
const pending = ref(true)
const error = ref(null)

// Methods
const refreshUser = async () => {
  try {
    refreshing.value = true
    error.value = null
    await getUser()
  } catch (err: any) {
    error.value = err.message || 'Failed to refresh user profile'
    console.error('Error refreshing user:', err)
  } finally {
    refreshing.value = false
  }
}

const handleSignOut = async () => {
  try {
    await signOut()
  } catch (err) {
    console.error('Sign out error:', err)
  }
}

// Initialize user data on mount
onMounted(async () => {
  try {
    await getUser()
  } catch (err: any) {
    error.value = err.message || 'Failed to load user profile'
    console.error('Error loading user profile:', err)
  } finally {
    pending.value = false
  }
})
</script>
