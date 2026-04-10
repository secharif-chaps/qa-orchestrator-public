<template>
  <div class="border-primary-lighter-stroke rounded-sm border bg-white">
    <div class="border-primary-lighter-stroke border-b px-6 py-4">
      <div class="flex items-center justify-between">
        <div>
          <h2 class="text-lg font-semibold">{{ $t('settings.profile.basic.title') }}</h2>
          <p class="text-neutral-black-font mt-1 text-sm">
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
      <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
        <div class="space-y-4">
          <div>
            <label class="text-neutral-black-font block text-sm font-medium">{{
              $t('settings.profile.fields.username')
            }}</label>
            <p class="mt-1 text-sm">{{ user?.profile?.preferred_username || 'N/A' }}</p>
          </div>
          <div>
            <label class="text-neutral-black-font block text-sm font-medium">{{
              $t('settings.profile.fields.email')
            }}</label>
            <p class="mt-1 text-sm">{{ user?.profile?.email || 'N/A' }}</p>
          </div>
        </div>
        <div class="space-y-4">
          <div>
            <label class="text-neutral-black-font block text-sm font-medium">{{
              $t('settings.profile.fields.firstName')
            }}</label>
            <p class="mt-1 text-sm">{{ user?.profile?.given_name || 'N/A' }}</p>
          </div>
          <div>
            <label class="text-neutral-black-font block text-sm font-medium">{{
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
