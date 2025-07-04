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
      <div class="bg-white dark:bg-slate-800 shadow rounded-lg">
        <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
          <div class="flex items-center justify-between">
            <div>
              <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">{{ $t('account.profile.basic.title') }}</h2>
              <p class="text-sm text-slate-600 dark:text-slate-400 mt-1">{{ $t('account.profile.basic.description') }}</p>
            </div>
            <OBadge 
              :color="user.expired ? 'red' : 'green'"
              :text="user.expired ? $t('account.profile.status.expired') : $t('account.profile.status.active')"
            >
              {{ user.expired ? $t('account.profile.status.expired') : $t('account.profile.status.active') }}
            </OBadge>
          </div>
        </div>
        <div class="px-6 py-6">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="space-y-4">
              <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('account.profile.fields.username') }}</label>
                <p class="mt-1 text-sm text-slate-900 dark:text-slate-400">{{ user.profile?.preferred_username || 'N/A' }}</p>
              </div>
              <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('account.profile.fields.email') }}</label>
                <p class="mt-1 text-sm text-slate-900 dark:text-slate-400">{{ user.profile?.email || 'N/A' }}</p>
              </div>
            </div>
            <div class="space-y-4">
              <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('account.profile.fields.firstName') }}</label>
                <p class="mt-1 text-sm text-slate-900 dark:text-slate-400">{{ user.profile?.given_name || 'N/A' }}</p>
              </div>
              <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('account.profile.fields.lastName') }}</label>
                <p class="mt-1 text-sm text-slate-900 dark:text-slate-400">{{ user.profile?.family_name || 'N/A' }}</p>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Authentication Details -->
      <div class="bg-white dark:bg-slate-800 shadow rounded-lg">
        <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
          <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">{{ $t('account.profile.auth.title') }}</h2>
          <p class="text-sm text-slate-600 dark:text-slate-400 mt-1">{{ $t('account.profile.auth.description') }}</p>
        </div>
        <div class="px-6 py-6">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="space-y-4">
              <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('account.profile.fields.userId') }}</label>
                <p class="mt-1 text-sm text-slate-900 dark:text-slate-400 font-mono break-all">{{ user.profile?.sub || 'N/A' }}</p>
              </div>
              <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('account.profile.fields.expiresAt') }}</label>
                <p class="mt-1 text-sm text-slate-900 dark:text-slate-400">{{ formatDate(user.expires_at) }}</p>
              </div>
            </div>
            <div class="space-y-4">
              <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('account.profile.fields.issuedAt') }}</label>
                <p class="mt-1 text-sm text-slate-900 dark:text-slate-400">{{ formatDate(user.profile?.iat) }}</p>
              </div>
              <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('account.profile.fields.sessionState') }}</label>
                <OBadge 
                  :color="user.expired ? 'red' : 'green'"
                  :text="user.expired ? $t('account.profile.status.expired') : $t('account.profile.status.active')"
                />
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Roles and Permissions -->
      <div class="bg-white dark:bg-slate-800 shadow rounded-lg">
        <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
          <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">{{ $t('account.profile.roles.title') }}</h2>
          <p class="text-sm text-slate-600 dark:text-slate-400 mt-1">{{ $t('account.profile.roles.description') }}</p>
        </div>
        <div class="px-6 py-6">
          <div v-if="userRoles && userRoles.length > 0">
            <div class="flex flex-wrap gap-2">
              <OBadge 
                v-for="role in userRoles"
                :key="role"
                color="blue"
                :text="role"
              />
            </div>
          </div>
          <div v-else>
            <p class="text-sm text-slate-500 dark:text-slate-400">{{ $t('account.profile.roles.none') }}</p>
          </div>
        </div>
      </div>

      <!-- Debug Information (Collapsible) -->
      <Collapsible.Root v-model:open="showDebugInfo">
        <div class="bg-white dark:bg-slate-800 shadow rounded-lg ">
          <Collapsible.Trigger class="w-full px-6 py-4 border-b border-slate-200 dark:border-slate-700 flex items-center justify-between hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">
            <div>
              <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">{{ $t('account.profile.debug.title') }}</h2>
              <p class="text-sm text-slate-600 dark:text-slate-400 mt-1">{{ $t('account.profile.debug.description') }}</p>
            </div>
            <i class="fas fa-chevron-down transition-transform" :class="showDebugInfo ? 'rotate-180' : ''"></i>
          </Collapsible.Trigger>
          <Collapsible.Content class="px-6 py-6">
            <pre class="text-xs bg-slate-50 dark:bg-slate-900 dark:text-slate-100 p-4 rounded overflow-auto">{{ JSON.stringify(user, null, 2) }}</pre>
          </Collapsible.Content>
        </div>
      </Collapsible.Root>

      <!-- Actions -->
      <div class="bg-white dark:bg-slate-800 shadow rounded-lg">
        <div class="px-6 py-6">
          <div class="flex flex-wrap gap-4">
            <OButton 
              :label="$t('account.profile.actions.refresh')"
              icon="fas fa-refresh"
              type="primary"
              color="primary"
              :loading="refreshing"
              @click="refreshUser"
            />
            <OButton 
              :label="$t('account.profile.actions.signOut')"
              icon="fas fa-sign-out-alt"
              type="secondary"
              color="red"
              @click="handleSignOut"
            />
          </div>
        </div>
      </div>
    </template>
  </div>
</template>

<script setup lang="ts">
import { OBadge, OButton } from '@owlint/feathers-vue'
import { Collapsible } from 'reka-ui/namespaced'

const { user, getUser, signOut } = useAuth()
const showDebugInfo = ref(false)
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

definePageMeta({
  title: 'Profile'
})
</script>