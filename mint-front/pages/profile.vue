<template>
  <div class=" container mx-auto">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
      <!-- Header -->
      <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900">User Profile</h1>
        <p class="text-gray-600 mt-2">Manage your account information and preferences</p>
      </div>

      <!-- Loading State -->
      <div v-if="pending" class="flex justify-center items-center py-12">
        <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-indigo-600"></div>
      </div>

      <!-- Error State -->
      <div v-else-if="error" class="bg-red-50 border border-red-200 rounded-lg p-6">
        <div class="flex">
          <div class="flex-shrink-0">
            <i class="fas fa-exclamation-circle text-red-400"></i>
          </div>
          <div class="ml-3">
            <h3 class="text-sm font-medium text-red-800">Error loading profile</h3>
            <div class="mt-2 text-sm text-red-700">
              <p>{{ error }}</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Profile Content -->
      <div v-else-if="user" class="space-y-6">
        <!-- Basic Information Card -->
        <div class="bg-white shadow rounded-lg overflow-hidden">
          <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-lg font-medium text-gray-900">Basic Information</h2>
          </div>
          <div class="px-6 py-4 space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700">Username</label>
                <p class="mt-1 text-sm text-gray-900">{{ user.profile?.preferred_username || 'N/A' }}</p>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700">Email</label>
                <p class="mt-1 text-sm text-gray-900">{{ user.profile?.email || 'N/A' }}</p>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700">First Name</label>
                <p class="mt-1 text-sm text-gray-900">{{ user.profile?.given_name || 'N/A' }}</p>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700">Last Name</label>
                <p class="mt-1 text-sm text-gray-900">{{ user.profile?.family_name || 'N/A' }}</p>
              </div>
            </div>
          </div>
        </div>

        <!-- Authentication Details Card -->
        <div class="bg-white shadow rounded-lg overflow-hidden">
          <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-lg font-medium text-gray-900">Authentication Details</h2>
          </div>
          <div class="px-6 py-4 space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700">User ID</label>
                <p class="mt-1 text-sm text-gray-900 font-mono break-all">{{ user.profile?.sub || 'N/A' }}</p>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700">Session State</label>
                <span class="mt-1 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                      :class="user.expired ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800'">
                  {{ user.expired ? 'Expired' : 'Active' }}
                </span>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700">Token Expires At</label>
                <p class="mt-1 text-sm text-gray-900">{{ formatDate(user.expires_at) }}</p>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700">Issued At</label>
                <p class="mt-1 text-sm text-gray-900">{{ formatDate(user.profile?.iat) }}</p>
              </div>
            </div>
          </div>
        </div>

        <!-- Roles and Permissions Card -->
        <div class="bg-white shadow rounded-lg overflow-hidden">
          <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-lg font-medium text-gray-900">Roles & Permissions</h2>
          </div>
          <div class="px-6 py-4">
            <div v-if="userRoles && userRoles.length > 0">
              <label class="block text-sm font-medium text-gray-700 mb-2">Assigned Roles</label>
              <div class="flex flex-wrap gap-2">
                <span v-for="role in userRoles"
                      :key="role"
                      class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-blue-100 text-blue-800">
                  {{ role }}
                </span>
              </div>
            </div>
            <div v-else>
              <p class="text-sm text-gray-500">No roles assigned</p>
            </div>
          </div>
        </div>

        <!-- Raw Token Data (Debug) -->
        <div class="bg-white shadow rounded-lg overflow-hidden" v-if="showDebugInfo">
          <div class="px-6 py-4 border-b border-gray-200">
            <div class="flex justify-between items-center">
              <h2 class="text-lg font-medium text-gray-900">Debug Information</h2>
              <button @click="showDebugInfo = false"
                      class="text-sm text-gray-500 hover:text-gray-700">
                <i class="fas fa-times"></i>
              </button>
            </div>
          </div>
          <div class="px-6 py-4">
            <pre class="text-xs bg-gray-50 p-4 rounded overflow-auto">{{ JSON.stringify(user, null, 2) }}</pre>
          </div>
        </div>

        <!-- Actions -->
        <div class="bg-white shadow rounded-lg overflow-hidden">
          <div class="px-6 py-4">
            <div class="flex flex-wrap gap-4">
              <button @click="refreshUser"
                      class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                <i class="fas fa-refresh mr-2"></i>
                Refresh Profile
              </button>
              <button @click="showDebugInfo = !showDebugInfo"
                      class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                <i class="fas fa-bug mr-2"></i>
                {{ showDebugInfo ? 'Hide' : 'Show' }} Debug Info
              </button>
              <button @click="handleSignOut"
                      class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                <i class="fas fa-sign-out-alt mr-2"></i>
                Sign Out
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
const { user, getUser, signOut } = useAuth()
const showDebugInfo = ref(false)

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
    pending.value = true
    error.value = null
    await getUser()
  } catch (err) {
    error.value = err.message || 'Failed to refresh user profile'
    console.error('Error refreshing user:', err)
  } finally {
    pending.value = false
  }
}

const handleSignOut = async () => {
  try {
    await signOut()
  } catch (err) {
    console.error('Sign out error:', err)
  }
}

const formatDate = (timestamp) => {
  if (!timestamp) return 'N/A'
  
  // Handle both Unix timestamp (seconds) and JavaScript timestamp (milliseconds)
  const date = new Date(timestamp * 1000 > Date.now() ? timestamp * 1000 : timestamp)
  
  if (isNaN(date.getTime())) return 'Invalid Date'
  
  return date.toLocaleString()
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

// Set page meta
definePageMeta({
  title: 'Profile - Mint'
})
</script>