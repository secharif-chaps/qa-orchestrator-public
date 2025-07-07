<template>
  <div class="bg-bg1 border border-border-2 rounded-lg">
    <div class="px-6 py-4 border-b border-border-2 ">
      <div class="flex items-center justify-between">
        <div>
          <h2 class="text-lg font-semibold">{{ $t('account.profile.basic.title') }}</h2>
          <p class="text-sm text-secondary mt-1">{{ $t('account.profile.basic.description') }}</p>
        </div>
        <OBadge 
          :color="user?.expired ? 'red' : 'green'"
          :text="user?.expired ? $t('account.profile.status.expired') : $t('account.profile.status.active')"
        >
          {{ user?.expired ? $t('account.profile.status.expired') : $t('account.profile.status.active') }}
        </OBadge>
      </div>
    </div>
    <div class="px-6 py-6">
      <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-secondary">{{ $t('account.profile.fields.username') }}</label>
            <p class="mt-1 text-sm">{{ user?.profile?.preferred_username || 'N/A' }}</p>
          </div>
          <div>
            <label class="block text-sm font-medium text-secondary">{{ $t('account.profile.fields.email') }}</label>
            <p class="mt-1 text-sm">{{ user?.profile?.email || 'N/A' }}</p>
          </div>
        </div>
        <div class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-secondary">{{ $t('account.profile.fields.firstName') }}</label>
            <p class="mt-1 text-sm">{{ user?.profile?.given_name || 'N/A' }}</p>
          </div>
          <div>
            <label class="block text-sm font-medium text-secondary">{{ $t('account.profile.fields.lastName') }}</label>
            <p class="mt-1 text-sm">{{ user?.profile?.family_name || 'N/A' }}</p>
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
  profile?: {
    preferred_username?: string
    email?: string
    given_name?: string
    family_name?: string
  }
}

interface Props {
  user: User | null
}

const props = defineProps<Props>()
</script>