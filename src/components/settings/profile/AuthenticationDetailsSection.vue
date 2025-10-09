<template>
  <div class="bg-base-100 border border-primary-stroke rounded-lg">
    <div class="px-6 py-4 border-b border-primary-stroke">
      <h2 class="text-lg font-semibold">{{ $t('settings.profile.auth.title') }}</h2>
      <p class="text-sm text-primary-light-content mt-1">
        {{ $t('settings.profile.auth.description') }}
      </p>
    </div>
    <div class="px-6 py-6">
      <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-primary-light-content">{{
              $t('settings.profile.fields.userId')
            }}</label>
            <p class="mt-1 text-sm font-mono break-all">{{ user?.profile?.sub || 'N/A' }}</p>
          </div>
          <div>
            <label class="block text-sm font-medium text-primary-light-content">{{
              $t('settings.profile.fields.expiresAt')
            }}</label>
            <p class="mt-1 text-sm">{{ formatDate(user?.expires_at) }}</p>
          </div>
        </div>
        <div class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-primary-light-content">{{
              $t('settings.profile.fields.issuedAt')
            }}</label>
            <p class="mt-1 text-sm">{{ formatDate(user?.profile?.iat) }}</p>
          </div>
          <div>
            <label class="block text-sm font-medium text-primary-light-content">{{
              $t('settings.profile.fields.sessionState')
            }}</label>
            <Tag
              :variant="user?.expired ? 'error' : 'success'"
              :label="
                user?.expired
                  ? $t('settings.profile.status.expired')
                  : $t('settings.profile.status.active')
              "
            />
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import Tag from '@/components/ui/Tag.vue'

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

defineProps<Props>()

const formatDate = (timestamp?: number) => {
  if (!timestamp) return 'N/A'

  // Handle both Unix timestamp (seconds) and JavaScript timestamp (milliseconds)
  const date = new Date(timestamp * 1000 > Date.now() ? timestamp * 1000 : timestamp)

  if (isNaN(date.getTime())) return 'Invalid Date'

  return date.toLocaleString()
}
</script>
