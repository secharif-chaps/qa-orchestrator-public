<template>
  <div class="border-primary-lighter-stroke rounded-card border bg-white">
    <div class="border-primary-lighter-stroke border-b px-6 py-4">
      <h2 class="text-lg font-semibold">{{ $t('settings.security.sessions.title') }}</h2>
      <p class="text-neutral-black-font mt-1 text-sm">
        {{ $t('settings.security.sessions.description') }}
      </p>
    </div>
    <div class="px-6 py-6">
      <!-- Loading State -->
      <div v-if="isLoading" class="flex flex-col gap-4">
        <div
          v-for="i in 3"
          :key="i"
          class="border-primary-lighter-stroke animate-pulse rounded-sm border p-4"
        >
          <div class="flex items-center gap-3">
            <div class="bg-primary-lightest h-10 w-10 rounded-sm"></div>
            <div class="flex flex-1 flex-col gap-2">
              <div class="bg-primary-lightest h-4 w-1/3 rounded"></div>
              <div class="bg-primary-lightest h-3 w-1/4 rounded"></div>
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
        <SessionItem v-if="currentSession" :session="currentSession" :is-current="true" />

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
          class="text-neutral-black-font py-4 text-center text-sm"
        >
          {{ $t('settings.security.sessions.noOtherSessions') }}
        </p>

        <!-- Sign Out All Devices -->
        <div v-if="otherSessions.length > 0" class="border-primary-lighter-stroke border-t pt-4">
          <div class="flex items-center justify-between">
            <div>
              <h3 class="text-sm font-medium">
                {{ $t('settings.security.sessions.signOutAll.title') }}
              </h3>
              <p class="text-neutral-black-font text-sm">
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
import type { Session } from '@/types/account'
import { Alert, Button } from '@owlint/feathers-vue'
import { computed } from 'vue'
import SessionItem from './SessionItem.vue'

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
