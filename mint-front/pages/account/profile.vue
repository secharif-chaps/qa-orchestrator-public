<template>
  <div class="space-y-6">
    <!-- Loading State -->
    <div v-if="pending" class="flex justify-center items-center py-12">
      <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-indigo-600"></div>
    </div>

    <!-- Error State -->
    <div v-else-if="error" class="bg-red-50 dark:bg-red-900 border border-red-200 dark:border-red-800 rounded-lg p-6">
      <div class="flex">
        <div class="flex-shrink-0">
          <i class="fas fa-exclamation-circle text-red-400"></i>
        </div>
        <div class="ml-3">
          <h3 class="text-sm font-medium text-red-800">{{ $t('account.profile.error.title') }}</h3>
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

      <!-- Authentication Details -->
      <AuthenticationDetailsSection :user="user" />

      <!-- Roles and Permissions -->
      <RolesPermissionsSection :user-roles="userRoles" />

      <!-- Profile Actions -->
      <ProfileActionsSection 
        :user="user"
        :refreshing="refreshing"
        @refresh-user="refreshUser"
        @sign-out="handleSignOut"
      />
    </template>
  </div>
</template>

<script setup lang="ts">
import BasicInformationSection from '~/components/account/profile/BasicInformationSection.vue'
import AuthenticationDetailsSection from '~/components/account/profile/AuthenticationDetailsSection.vue'
import RolesPermissionsSection from '~/components/account/profile/RolesPermissionsSection.vue'
import ProfileActionsSection from '~/components/account/profile/ProfileActionsSection.vue'

const { user, getUser, signOut } = useAuth()
const refreshing = ref(false)

// Reactive states
const pending = ref(true)
const error = ref(null)

// Computed properties
const userRoles = computed(() => {
  if (!user.value?.profile) return []
  
  // Extract roles from different possible locations
  const realmRoles = user.value.profile.realm_access?.roles || []
  const resourceRoles = user.value.profile.resource_access ? 
    Object.values(user.value.profile.resource_access).flatMap(resource => resource.roles || []) : []
  
  return [...new Set([...realmRoles, ...resourceRoles])]
})

// Methods
const refreshUser = async () => {
  try {
    refreshing.value = true
    error.value = null
    await getUser()
  } catch (err) {
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
  } catch (err) {
    error.value = err.message || 'Failed to load user profile'
    console.error('Error loading user profile:', err)
  } finally {
    pending.value = false
  }
})

definePageMeta({
  title: 'Profile'
})
</script>