<template>
  <div class="bg-bg1 border border-border-2 rounded-lg">
    <div class="px-6 py-4 border-b border-border-2">
      <h2 class="text-lg font-semibold">{{ $t('account.profile.auth.title') }}</h2>
      <p class="text-sm text-secondary mt-1">{{ $t('account.profile.auth.description') }}</p>
    </div>
    <div class="px-6 py-6">
      <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-secondary">{{ $t('account.profile.fields.userId') }}</label>
            <p class="mt-1 text-sm font-mono break-all">{{ user?.profile?.sub || 'N/A' }}</p>
          </div>
          <div>
            <label class="block text-sm font-medium text-secondary">{{ $t('account.profile.fields.expiresAt') }}</label>
            <p class="mt-1 text-sm">{{ formatDate(user?.expires_at) }}</p>
          </div>
        </div>
        <div class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-secondary">{{ $t('account.profile.fields.issuedAt') }}</label>
            <p class="mt-1 text-sm">{{ formatDate(user?.profile?.iat) }}</p>
          </div>
          <div>
            <label class="block text-sm font-medium text-secondary">{{ $t('account.profile.fields.sessionState') }}</label>
            <OBadge 
              :color="user?.expired ? 'red' : 'green'"
            >
            {{ user?.expired ? $t('account.profile.status.expired') : $t('account.profile.status.active') }}
            </OBadge>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { OBadge } from '@owlint/feathers-vue'

interface User {
  expired?: boolean
  expires_at?: number
  profile?: {
    sub?: string
    iat?: number
  }
}

interface Props {
  user: User | null
}

const props = defineProps<Props>()

const formatDate = (timestamp?: number) => {
  if (!timestamp) return 'N/A'
  
  // Handle both Unix timestamp (seconds) and JavaScript timestamp (milliseconds)
  const date = new Date(timestamp * 1000 > Date.now() ? timestamp * 1000 : timestamp)
  
  if (isNaN(date.getTime())) return 'Invalid Date'
  
  return date.toLocaleString()
}
</script>