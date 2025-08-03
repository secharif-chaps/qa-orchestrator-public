<template>
  <div class="space-y-6">
    <!-- Session Management -->
    <SessionManagementSection
      :other-sessions="otherSessions"
      :user-agent="userAgent"
      @revoke-session="revokeSession"
      @sign-out-all-devices="signOutAllDevices"
    />

    <!-- Two-Factor Authentication -->
    <TwoFactorSection
      :two-factor-enabled="twoFactorEnabled"
      :security-keys-count="securityKeysCount"
      @toggle-two-factor="toggleTwoFactor"
      @manage-security-keys="manageSecurityKeys"
    />

    <!-- Activity Log -->
    <ActivityLogSection :recent-activity="recentActivity" />

    <!-- Account Recovery -->
    <AccountRecoverySection
      :backup-codes-generated="backupCodesGenerated"
      :recovery-email="recoveryEmail"
      @generate-backup-codes="generateBackupCodes"
      @update-recovery-email="updateRecoveryEmail"
    />
  </div>
</template>

<script setup lang="ts">
import SessionManagementSection from '@/components/settings/security/SessionManagementSection.vue'
import TwoFactorSection from '@/components/settings/security/TwoFactorSection.vue'
import ActivityLogSection from '@/components/settings/security/ActivityLogSection.vue'
import AccountRecoverySection from '@/components/settings/security/AccountRecoverySection.vue'
import { ref, onMounted } from 'vue'

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
    lastActive: new Date(Date.now() - 2 * 60 * 60 * 1000), // 2 hours ago
  },
  {
    id: '2',
    device: 'Chrome on Windows',
    deviceIcon: 'fas fa-laptop',
    location: 'Lyon, France',
    lastActive: new Date(Date.now() - 24 * 60 * 60 * 1000), // 1 day ago
  },
])

const recentActivity = ref([
  {
    id: '1',
    type: 'login',
    icon: 'fas fa-sign-in-alt',
    title: 'Successful login',
    description: 'Signed in from Chrome on macOS',
    location: 'Paris, France',
    timestamp: new Date(Date.now() - 30 * 60 * 1000), // 30 minutes ago
  },
  {
    id: '2',
    type: 'security',
    icon: 'fas fa-exclamation-triangle',
    title: 'Failed login attempt',
    description: 'Invalid password from unknown device',
    location: 'Unknown location',
    timestamp: new Date(Date.now() - 3 * 60 * 60 * 1000), // 3 hours ago
  },
  {
    id: '3',
    type: 'update',
    icon: 'fas fa-user-edit',
    title: 'Profile updated',
    description: 'Changed email preferences',
    location: 'Paris, France',
    timestamp: new Date(Date.now() - 2 * 24 * 60 * 60 * 1000), // 2 days ago
  },
])

// Methods
const revokeSession = (sessionId: string) => {
  otherSessions.value = otherSessions.value.filter((session) => session.id !== sessionId)
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

// Initialize user agent
onMounted(() => {
  if (typeof navigator !== 'undefined') {
    userAgent.value = navigator.userAgent
  }
})
</script>
