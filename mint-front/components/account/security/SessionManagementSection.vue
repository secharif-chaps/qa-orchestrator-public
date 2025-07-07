<template>
  <div class="bg-bg1 border border-border-2 rounded-lg">
    <div class="px-6 py-4 border-b border-border-2">
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
              @click="() => handleRevokeSession(session.id)"
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
              @click="handleSignOutAllDevices"
            />
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { OBadge, OButton } from '@owlint/feathers-vue'

interface Session {
  id: string
  device: string
  deviceIcon: string
  location: string
  lastActive: Date
}

interface Props {
  otherSessions: Session[]
  userAgent: string
}

const props = defineProps<Props>()

const emit = defineEmits<{
  revokeSession: [sessionId: string]
  signOutAllDevices: []
}>()

const handleRevokeSession = (sessionId: string) => {
  emit('revokeSession', sessionId)
}

const handleSignOutAllDevices = () => {
  emit('signOutAllDevices')
}

const formatDate = (date: Date) => {
  return date.toLocaleString()
}
</script>