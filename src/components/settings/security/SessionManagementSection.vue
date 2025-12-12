<template>
  <div class="bg-base-100 border border-primary-stroke rounded-card">
    <div class="px-6 py-4 border-b border-primary-stroke">
      <h2 class="text-lg font-semibold">{{ $t('settings.security.sessions.title') }}</h2>
      <p class="text-sm text-secondary mt-1">
        {{ $t('settings.security.sessions.description') }}
      </p>
    </div>
    <div class="px-6 py-6">
      <div class="flex flex-col gap-4">
        <!-- Current Session -->
        <div class="border border-base-300 bg-base-200 rounded-lg p-4">
          <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
              <div
                class="w-10 h-10 rounded-lg bg-rose-100 border border-rose-200 text-rose-600 dark:bg-rose-900/30 dark:text-rose-400 flex items-center justify-center"
              >
                <i class="fas fa-desktop"></i>
              </div>
              <div>
                <h3 class="text-sm font-medium">
                  {{ $t('settings.security.sessions.current.title') }}
                </h3>
                <p class="text-xs text-secondary">{{ userAgent }}</p>
                <p class="text-xs text-secondary">
                  {{ $t('settings.security.sessions.current.lastActive') }}:
                  {{ formatDate(new Date()) }}
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
                <i :class="session.deviceIcon"></i>
              </div>
              <div>
                <h3 class="text-sm font-medium">{{ session.device }}</h3>
                <p class="text-xs text-secondary">{{ session.location }}</p>
                <p class="text-xs text-secondary">
                  {{ $t('settings.security.sessions.lastActive') }}:
                  {{ formatDate(session.lastActive) }}
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

        <!-- Sign Out All Devices -->
        <div class="pt-4 border-t border-primary-stroke">
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
import Tag from '@/components/ui/Tag.vue'
import { Button } from '@owlint/feathers-vue'

interface Session {
  id: string
  device: string
  deviceIcon: string
  location: string
  lastActive: Date
}

defineProps<{
  otherSessions: Session[]
  userAgent: string
}>()

const emit = defineEmits<{
  revokeSession: [sessionId: string]
  signOutAllDevices: []
}>()

function handleRevokeSession(sessionId: string) {
  emit('revokeSession', sessionId)
}

function handleSignOutAllDevices() {
  emit('signOutAllDevices')
}

function formatDate(date: Date) {
  return date.toLocaleString()
}
</script>
