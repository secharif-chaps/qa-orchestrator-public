<template>
  <div class="flex flex-col gap-6">
    <!-- Session Management -->
    <SessionManagementSection
      :sessions="sessionsData?.sessions ?? []"
      :current-session-id="sessionsData?.currentSessionId"
      :is-loading="isLoadingSessions"
      :error="sessionsError as Error | null"
      @revoke-session="handleRevokeSession"
      @sign-out-all-devices="handleSignOutAllDevices"
    />

    <!-- Activity Log -->
    <ActivityLogSection
      :events="activityData?.events ?? []"
      :is-loading="isLoadingActivity"
      :error="activityError as Error | null"
      :has-more="activityData?.hasMore ?? false"
      @load-more="loadMoreActivity"
    />
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import { useQuery } from '@pinia/colada'
import SessionManagementSection from '@/components/settings/security/SessionManagementSection.vue'
import ActivityLogSection from '@/components/settings/security/ActivityLogSection.vue'
import { sessionsQuery, activityEventsQuery } from '@/queries/account'
import { useRevokeSession, useRevokeAllSessions } from '@/mutations/account'
import type { ActivityEventsParams } from '@/types/account'

// Activity pagination state
const activityPage = ref(1)
const activitySize = ref(20)

// Computed params for activity query
const activityParams = computed<ActivityEventsParams>(() => ({
  page: activityPage.value,
  size: activitySize.value,
}))

// Sessions query
const {
  data: sessionsData,
  isLoading: isLoadingSessions,
  error: sessionsError,
} = useQuery(sessionsQuery)

// Activity events query
const {
  data: activityData,
  isLoading: isLoadingActivity,
  error: activityError,
} = useQuery(activityEventsQuery, () => ({ params: activityParams.value }))

// Mutations
const { revokeSession } = useRevokeSession()
const { revokeAllSessions } = useRevokeAllSessions()

// Handlers
async function handleRevokeSession(sessionId: string) {
  try {
    await revokeSession(sessionId)
  } catch (error) {
    console.error('Failed to revoke session:', error)
  }
}

async function handleSignOutAllDevices() {
  try {
    // Keep current session active
    await revokeAllSessions(true)
  } catch (error) {
    console.error('Failed to sign out all devices:', error)
  }
}

function loadMoreActivity() {
  activityPage.value += 1
}
</script>
