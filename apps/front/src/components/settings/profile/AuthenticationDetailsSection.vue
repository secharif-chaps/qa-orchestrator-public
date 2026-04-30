<template>
  <div class="border-primary-lighter-stroke rounded-sm border bg-white">
    <div class="border-primary-lighter-stroke border-b px-6 py-4">
      <h2 class="text-lg font-semibold">{{ $t('settings.profile.auth.title') }}</h2>
      <p class="text-neutral-black-font mt-1 text-sm">
        {{ $t('settings.profile.auth.description') }}
      </p>
    </div>
    <div class="px-6 py-6">
      <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
        <div class="space-y-4">
          <div>
            <label class="text-neutral-black-font block text-sm font-medium">{{
              $t('settings.profile.fields.userId')
            }}</label>
            <p class="mt-1 font-mono text-sm break-all">{{ user?.profile?.sub || 'N/A' }}</p>
          </div>
          <div>
            <label class="text-neutral-black-font block text-sm font-medium">{{
              $t('settings.profile.fields.expiresAt')
            }}</label>
            <p class="mt-1 text-sm">{{ formatDate(user?.expires_at) }}</p>
          </div>
        </div>
        <div class="space-y-4">
          <div>
            <label class="text-neutral-black-font block text-sm font-medium">{{
              $t('settings.profile.fields.issuedAt')
            }}</label>
            <p class="mt-1 text-sm">{{ formatDate(user?.profile?.iat) }}</p>
          </div>
          <div>
            <label class="text-neutral-black-font block text-sm font-medium">{{
              $t('settings.profile.fields.sessionState')
            }}</label>
            <Tag
              :intent="user?.expired ? 'danger' : 'success'"
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
import { useDateTime } from '@/composables/useDateTime'
import { Tag } from '@owlint/feathers-vue'

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

const { formatDate: formatDateLocale } = useDateTime()

const formatDate = (timestamp?: number) => {
  if (!timestamp) return 'N/A'

  // Handle both Unix timestamp (seconds) and JavaScript timestamp (milliseconds)
  const date = new Date(timestamp * 1000 > Date.now() ? timestamp * 1000 : timestamp)

  if (isNaN(date.getTime())) return 'Invalid Date'

  return formatDateLocale(date, 'long')
}
</script>
