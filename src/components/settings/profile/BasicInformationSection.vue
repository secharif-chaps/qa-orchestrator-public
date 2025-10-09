<template>
  <div class="bg-base-100 border border-primary-stroke rounded-lg">
    <div class="px-6 py-4 border-b border-primary-stroke">
      <div class="flex items-center justify-between">
        <div>
          <h2 class="text-lg font-semibold">{{ $t('settings.profile.basic.title') }}</h2>
          <p class="text-sm text-primary-light-content mt-1">
            {{ $t('settings.profile.basic.description') }}
          </p>
        </div>
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
    <div class="px-6 py-6">
      <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-primary-light-content">{{
              $t('settings.profile.fields.username')
            }}</label>
            <p class="mt-1 text-sm">{{ user?.profile?.preferred_username || 'N/A' }}</p>
          </div>
          <div>
            <label class="block text-sm font-medium text-primary-light-content">{{
              $t('settings.profile.fields.email')
            }}</label>
            <p class="mt-1 text-sm">{{ user?.profile?.email || 'N/A' }}</p>
          </div>
        </div>
        <div class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-primary-light-content">{{
              $t('settings.profile.fields.firstName')
            }}</label>
            <p class="mt-1 text-sm">{{ user?.profile?.given_name || 'N/A' }}</p>
          </div>
          <div>
            <label class="block text-sm font-medium text-primary-light-content">{{
              $t('settings.profile.fields.lastName')
            }}</label>
            <p class="mt-1 text-sm">{{ user?.profile?.family_name || 'N/A' }}</p>
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

defineProps<Props>()
</script>
