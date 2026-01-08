<template>
  <div class="bg-base-100 border border-primary-stroke rounded-card">
    <div class="px-6 py-4 border-b border-primary-stroke">
      <h2 class="text-lg font-semibold">{{ $t('settings.security.sessions.title') }}</h2>
      <p class="text-sm text-secondary mt-1">
        {{ $t('settings.security.sessions.description') }}
      </p>
    </div>
    <div class="px-6 py-6">
      <!-- Loading State -->
      <div v-if="isLoading" class="flex flex-col gap-4">
        <div
          v-for="i in 3"
          :key="i"
          class="border border-primary-stroke rounded-lg p-4 animate-pulse"
        >
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-lg bg-base-200"></div>
            <div class="flex-1 flex flex-col gap-2">
              <div class="h-4 bg-base-200 rounded w-1/3"></div>
              <div class="h-3 bg-base-200 rounded w-1/4"></div>
            </div>
          </div>
        </div>
      </div>

      <!-- Error State -->
      <Alert
        v-else-if="error"
        variant="danger"
        :title="$t('settings.security.sessions.error')"
        :message="error.message || $t('settings.security.sessions.errorDescription')"
        icon="fa fa-exclamation-circle"
      />

      <!-- Sessions List -->
      <div v-else class="flex flex-col gap-4">
        <!-- Current Session -->
        <div
          v-if="currentSession"
          class="border border-sage-300 dark:border-base-300 bg-base-200 rounded-lg p-4"
        >
          <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
              <div
                class="w-10 h-10 rounded-lg bg-rose-100 border border-rose-200 text-rose-600 dark:bg-rose-900/30 dark:text-rose-400 flex items-center justify-center"
              >
                <i class="fas fa-desktop"></i>
              </div>
              <div>
                <h3 class="text-sm font-medium">
                  {{ $t('settings.security.sessions.webSession') }}
                </h3>
                <p class="text-xs text-secondary">
                  <i class="fas fa-globe mr-1"></i>{{ currentSession.ipAddress }}
                </p>
                <p class="text-xs text-secondary">
                  <i class="fas fa-clock mr-1"></i>{{ formatRelativeTime(currentSession.lastAccess) }}
                </p>
              </div>
            </div>
            <Tag variant="success" :label="$t('settings.security.sessions.current.badge')" />
          </div>
        </div>

        <!-- Other Sessions -->
        <div
          v-for="session in otherSessions"
          :key="session.id"
          class="border border-primary-stroke rounded-lg p-4"
        >
          <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
              <div class="w-10 h-10 rounded-lg bg-base-200 text-secondary flex items-center justify-center">
                <i class="fas fa-desktop"></i>
              </div>
              <div>
                <h3 class="text-sm font-medium">{{ $t('settings.security.sessions.webSession') }}</h3>
                <p class="text-xs text-secondary">
                  <i class="fas fa-globe mr-1"></i>{{ session.ipAddress }}
                </p>
                <p class="text-xs text-secondary">
                  <i class="fas fa-clock mr-1"></i>{{ formatRelativeTime(session.lastAccess) }}
                </p>
              </div>
            </div>
            <Button
              :label="$t('settings.security.actions.revoke')"
              variant="secondary"
              color="danger"
              size="sm"
              @click="() => handleRevokeSession(session.id)"
            />
          </div>
        </div>

        <!-- No Other Sessions -->
        <p
          v-if="otherSessions.length === 0 && currentSession"
          class="text-sm text-secondary text-center py-4"
        >
          {{ $t('settings.security.sessions.noOtherSessions') }}
        </p>

        <!-- Sign Out All Devices -->
        <div v-if="otherSessions.length > 0" class="pt-4 border-t border-primary-stroke">
          <div class="flex items-center justify-between">
            <div>
              <h3 class="text-sm font-medium">
                {{ $t('settings.security.sessions.signOutAll.title') }}
              </h3>
              <p class="text-sm text-secondary">
                {{ $t('settings.security.sessions.signOutAll.description') }}
              </p>
            </div>
            <Button
              :label="$t('settings.security.sessions.signOutAll.title')"
              icon="fa fa-sign-out-alt"
              variant="secondary"
              color="danger"
              @click="handleSignOutAllDevices"
            />
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import Tag from '@/components/ui/Tag.vue'
import { Alert, Button } from '@owlint/feathers-vue'
import type { Session } from '@/types/account'

const props = defineProps<{
  sessions: Session[]
  currentSessionId?: string | null
  isLoading: boolean
  error?: Error | null
}>()

const emit = defineEmits<{
  revokeSession: [sessionId: string]
  signOutAllDevices: []
}>()

// Computed properties
const currentSession = computed(() => {
  return props.sessions.find((s) => s.isCurrent)
})

const otherSessions = computed(() => {
  return props.sessions.filter((s) => !s.isCurrent)
})

// Helper functions
function formatRelativeTime(dateString: string): string {
  const date = new Date(dateString)
  const now = new Date()
  const diffMs = now.getTime() - date.getTime()
  const diffMins = Math.floor(diffMs / 60000)
  const diffHours = Math.floor(diffMs / 3600000)
  const diffDays = Math.floor(diffMs / 86400000)

  if (diffMins < 1) return 'Just now'
  if (diffMins < 60) return `${diffMins} minute${diffMins > 1 ? 's' : ''} ago`
  if (diffHours < 24) return `${diffHours} hour${diffHours > 1 ? 's' : ''} ago`
  if (diffDays < 7) return `${diffDays} day${diffDays > 1 ? 's' : ''} ago`

  return date.toLocaleDateString()
}

function handleRevokeSession(sessionId: string) {
  emit('revokeSession', sessionId)
}

function handleSignOutAllDevices() {
  emit('signOutAllDevices')
}
</script>
