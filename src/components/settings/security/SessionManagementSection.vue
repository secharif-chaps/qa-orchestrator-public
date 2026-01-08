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
        <SessionItem
          v-if="currentSession"
          :session="currentSession"
          :is-current="true"
        />

        <!-- Other Sessions -->
        <SessionItem
          v-for="session in otherSessions"
          :key="session.id"
          :session="session"
          :is-current="false"
          @revoke="handleRevokeSession"
        />

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
import { Alert, Button } from '@owlint/feathers-vue'
import SessionItem from './SessionItem.vue'
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

function handleRevokeSession(sessionId: string) {
  emit('revokeSession', sessionId)
}

function handleSignOutAllDevices() {
  emit('signOutAllDevices')
}
</script>
