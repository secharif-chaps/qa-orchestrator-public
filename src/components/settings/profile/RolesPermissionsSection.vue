<template>
  <div v-if="isDev" class="bg-base-100 border-primary-stroke rounded-lg border">
    <div class="border-primary-stroke border-b px-6 py-4">
      <h2 class="text-lg font-semibold">{{ $t('settings.profile.roles.title') }}</h2>
      <p class="text-secondary mt-1 text-sm">
        {{ $t('settings.profile.roles.description') }}
      </p>
    </div>
    <div class="px-6 py-6">
      <div class="flex flex-col gap-2">
        <div class="flex flex-wrap gap-2">
          <span
            v-for="permission in userPermissions"
            :key="permission"
            class="bg-base-200/50 rounded-md px-2 py-1 text-sm"
          >
            {{ permission }}
          </span>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { useAuthStore } from '@/stores/auth'
import { computed } from 'vue'

const isDev = computed(() => import.meta.env.DEV)

const { userPermissions } = useAuthStore()
</script>
