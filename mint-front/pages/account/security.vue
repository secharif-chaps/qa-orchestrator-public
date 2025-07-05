<template>
  <div class="space-y-6">
    <!-- Session Management -->
    <div class="bg-white dark:bg-slate-800 shadow rounded-lg">
      <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
        <h2 class="text-lg font-semibold">{{ $t('account.security.sessions.title') }}</h2>
        <p class="text-sm text-secondary mt-1">{{ $t('account.security.sessions.description') }}</p>
      </div>
      <div class="px-6 py-6">
        <div class="space-y-4">
          <!-- Current Session -->
          <div class="border border-primary/20 dark:border-primary bg-primary/10 dark:bg-slate-900 rounded-lg p-4">
            <div class="flex items-center justify-between">
              <div class="flex items-center space-x-3">
                <div class="flex-shrink-0">
                  <i class="fas fa-desktop text-primary"></i>
                </div>
                <div>
                  <h3 class="text-sm font-medium text-primary">{{ $t('account.security.sessions.current.title') }}</h3>
                  <p class="text-xs text-primary">{{ userAgent }}</p>
                  <p class="text-xs text-primary">{{ $t('account.security.sessions.current.lastActive') }}: {{ formatDate(new Date()) }}</p>
                </div>
              </div>
              <OBadge color="green" :text="$t('account.security.sessions.current.badge')" />
            </div>
          </div>

          <!-- Other Sessions -->
          <div v-for="session in otherSessions" :key="session.id" class="border border-slate-200 dark:border-slate-700 rounded-lg p-4">
            <div class="flex items-center justify-between">
              <div class="flex items-center space-x-3">
                <div class="flex-shrink-0">
                  <i :class="session.deviceIcon" class="text-secondary"></i>
                </div>
                <div>
                  <h3 class="text-sm font-medium">{{ session.device }}</h3>
                  <p class="text-xs text-secondary">{{ session.location }}</p>
                  <p class="text-xs text-secondary">{{ $t('account.security.sessions.lastActive') }}: {{ formatDate(session.lastActive) }}</p>
                </div>
              </div>
              <OButton 
                :label="$t('account.security.actions.revoke')"
                type="secondary"
                color="red"
                size="sm"
                @click="() => revokeSession(session.id)"
              />
            </div>
          </div>

          <!-- Sign Out All Devices -->
          <div class="pt-4 border-t border-slate-200 dark:border-slate-700">
            <div class="flex items-center justify-between">
              <div>
                <h3 class="text-sm font-medium">{{ $t('account.security.sessions.signOutAll.title') }}</h3>
                <p class="text-sm text-secondary">{{ $t('account.security.sessions.signOutAll.description') }}</p>
              </div>
              <OButton 
                :label="$t('account.security.sessions.signOutAll.title')"
                icon="fas fa-sign-out-alt"
                type="secondary"
                color="red"
                @click="signOutAllDevices"
              />
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Two-Factor Authentication -->
    <div class="bg-white dark:bg-slate-800 shadow rounded-lg">
      <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
        <h2 class="text-lg font-semibold">{{ $t('account.security.twoFactor.title') }}</h2>
        <p class="text-sm text-secondary mt-1">{{ $t('account.security.twoFactor.description') }}</p>
      </div>
      <div class="px-6 py-6">
        <div class="space-y-4">
          <!-- Authenticator App -->
          <div class="flex items-center justify-between p-4 border border-slate-200 dark:border-slate-700 rounded-lg">
            <div class="flex items-center space-x-3">
              <div class="flex-shrink-0">
                <i class="fas fa-mobile-alt text-secondary"></i>
              </div>
              <div>
                <h3 class="text-sm font-medium">{{ $t('account.security.twoFactor.authenticator.title') }}</h3>
                <p class="text-sm text-secondary">{{ $t('account.security.twoFactor.authenticator.description') }}</p>
              </div>
            </div>
            <div class="flex items-center space-x-2">
              <OBadge 
                :color="twoFactorEnabled ? 'green' : 'slate'" 
                :text="twoFactorEnabled ? $t('account.security.status.enabled') : $t('account.security.status.disabled')"
              >
                {{ twoFactorEnabled ? $t('account.security.status.enabled') : $t('account.security.status.disabled') }}
              </OBadge>
              <OButton 
                :label="twoFactorEnabled ? $t('account.security.actions.disable') : $t('account.security.actions.setup')"
                type="secondary"
                :color="twoFactorEnabled ? 'red' : 'primary'"
                size="sm"
                @click="toggleTwoFactor"
              />
            </div>
          </div>

          <!-- Security Keys -->
          <div class="flex items-center justify-between p-4 border border-slate-200 dark:border-slate-700 rounded-lg">
            <div class="flex items-center space-x-3">
              <div class="flex-shrink-0">
                <i class="fas fa-key text-secondary"></i>
              </div>
              <div>
                <h3 class="text-sm font-medium">{{ $t('account.security.twoFactor.securityKeys.title') }}</h3>
                <p class="text-sm text-secondary">{{ $t('account.security.twoFactor.securityKeys.description') }}</p>
              </div>
            </div>
            <div class="flex items-center space-x-2">
              <OBadge 
                :color="securityKeysCount > 0 ? 'green' : 'slate'" 
                :text="securityKeysCount > 0 ? `${securityKeysCount} ${$t('account.security.twoFactor.securityKeys.count')}` : $t('account.security.status.disabled')"
              >
                {{ securityKeysCount > 0 ? `${securityKeysCount} ${$t('account.security.twoFactor.securityKeys.count')}` : $t('account.security.status.disabled') }}
              </OBadge>
              <OButton 
                :label="$t('account.security.actions.manage')"
                type="secondary"
                color="primary"
                size="sm"
                @click="manageSecurityKeys"
              />
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Activity Log -->
    <div class="bg-white dark:bg-slate-800 shadow rounded-lg">
      <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
        <h2 class="text-lg font-semibold">{{ $t('account.security.activity.title') }}</h2>
        <p class="text-sm text-secondary mt-1">{{ $t('account.security.activity.description') }}</p>
      </div>
      <div class="px-6 py-6">
        <div class="space-y-3">
          <div v-for="activity in recentActivity" :key="activity.id" class="flex items-center space-x-3 p-3 border border-slate-200 dark:border-slate-700 rounded-lg">
            <div class="flex-shrink-0">
              <i :class="[activity.type === 'security' ? 'text-red-500 dark:text-red-400' : 'text-green-500 dark:text-green-400', activity.icon]"></i>
            </div>
            <div class="flex-1">
              <p class="text-sm font-medium">{{ activity.title }}</p>
              <p class="text-xs text-secondary">{{ activity.description }}</p>
              <p class="text-xs text-secondary">{{ formatDate(activity.timestamp) }}</p>
            </div>
            <div v-if="activity.location" class="text-xs text-secondary">
              {{ activity.location }}
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Account Recovery -->
    <div class="bg-white dark:bg-slate-800 shadow rounded-lg">
      <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
        <h2 class="text-lg font-semibold">{{ $t('account.security.recovery.title') }}</h2>
        <p class="text-sm text-secondary mt-1">{{ $t('account.security.recovery.description') }}</p>
      </div>
      <div class="px-6 py-6">
        <div class="space-y-4">
          <!-- Backup Codes -->
          <div class="flex items-center justify-between p-4 border border-slate-200 dark:border-slate-700 rounded-lg">
            <div class="flex items-center space-x-3">
              <div class="flex-shrink-0">
                <i class="fas fa-shield-alt text-secondary"></i>
              </div>
              <div>
                <h3 class="text-sm font-medium">{{ $t('account.security.recovery.backupCodes.title') }}</h3>
                <p class="text-sm text-secondary">{{ $t('account.security.recovery.backupCodes.description') }}</p>
              </div>
            </div>
            <div class="flex items-center space-x-2">
              <OBadge 
                :color="backupCodesGenerated ? 'green' : 'yellow'" 
                :text="backupCodesGenerated ? $t('account.security.status.generated') : $t('account.security.status.notGenerated')"
              />
              <OButton 
                :label="backupCodesGenerated ? $t('account.security.actions.regenerate') : $t('account.security.actions.generate')"
                type="secondary"
                color="primary"
                size="sm"
                @click="generateBackupCodes"
              />
            </div>
          </div>

          <!-- Recovery Email -->
          <div class="flex items-center justify-between p-4 border border-slate-200 dark:border-slate-700 rounded-lg">
            <div class="flex items-center space-x-3">
              <div class="flex-shrink-0">
                <i class="fas fa-envelope text-secondary"></i>
              </div>
              <div>
                <h3 class="text-sm font-medium">{{ $t('account.security.recovery.email.title') }}</h3>
                <p class="text-sm text-secondary">{{ recoveryEmail || $t('account.security.recovery.email.notSet') }}</p>
              </div>
            </div>
            <OButton 
              :label="recoveryEmail ? $t('account.security.actions.update') : $t('account.security.actions.add')"
              type="secondary"
              color="primary"
              size="sm"
              @click="updateRecoveryEmail"
            />
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { OBadge, OButton } from '@owlint/feathers-vue'

// Security states
const twoFactorEnabled = ref(false)
const securityKeysCount = ref(0)
const backupCodesGenerated = ref(false)
const recoveryEmail = ref('')

// Mock data
const userAgent = ref('')
const otherSessions = ref([
  {
    id: '1',
    device: 'iPhone 14 Pro',
    deviceIcon: 'fas fa-mobile-alt',
    location: 'Paris, France',
    lastActive: new Date(Date.now() - 2 * 60 * 60 * 1000) // 2 hours ago
  },
  {
    id: '2', 
    device: 'Chrome on Windows',
    deviceIcon: 'fas fa-laptop',
    location: 'Lyon, France',
    lastActive: new Date(Date.now() - 24 * 60 * 60 * 1000) // 1 day ago
  }
])

const recentActivity = ref([
  {
    id: '1',
    type: 'login',
    icon: 'fas fa-sign-in-alt',
    title: 'Successful login',
    description: 'Signed in from Chrome on macOS',
    location: 'Paris, France',
    timestamp: new Date(Date.now() - 30 * 60 * 1000) // 30 minutes ago
  },
  {
    id: '2',
    type: 'security',
    icon: 'fas fa-exclamation-triangle',
    title: 'Failed login attempt',
    description: 'Invalid password from unknown device',
    location: 'Unknown location',
    timestamp: new Date(Date.now() - 3 * 60 * 60 * 1000) // 3 hours ago
  },
  {
    id: '3',
    type: 'update',
    icon: 'fas fa-user-edit',
    title: 'Profile updated',
    description: 'Changed email preferences',
    location: 'Paris, France',
    timestamp: new Date(Date.now() - 2 * 24 * 60 * 60 * 1000) // 2 days ago
  }
])

// Methods
const revokeSession = (sessionId: string) => {
  otherSessions.value = otherSessions.value.filter(session => session.id !== sessionId)
  console.log(`Revoking session: ${sessionId}`)
}

const signOutAllDevices = () => {
  otherSessions.value = []
  console.log('Signing out all devices')
}

const toggleTwoFactor = () => {
  twoFactorEnabled.value = !twoFactorEnabled.value
  console.log(`Two-factor authentication ${twoFactorEnabled.value ? 'enabled' : 'disabled'}`)
}

const manageSecurityKeys = () => {
  console.log('Managing security keys')
}

const generateBackupCodes = () => {
  backupCodesGenerated.value = true
  console.log('Generating backup codes')
}

const updateRecoveryEmail = () => {
  console.log('Updating recovery email')
}

const formatDate = (date: Date) => {
  return date.toLocaleString()
}

// Initialize user agent
onMounted(() => {
  if (typeof navigator !== 'undefined') {
    userAgent.value = navigator.userAgent
  }
})

definePageMeta({
  title: 'Security'
})
</script>