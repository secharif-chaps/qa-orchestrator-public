<template>
  <div v-if="isDev" class="bg-base-100 border border-primary-stroke rounded-lg">
    <div class="px-6 py-4 border-b border-primary-stroke">
      <h2 class="text-lg font-semibold">{{ $t('settings.profile.roles.title') }}</h2>
      <p class="text-sm text-primary-light-content mt-1">
        {{ $t('settings.profile.roles.description') }}
      </p>
    </div>
    <div class="px-6 py-6">
      <div class="flex flex-col gap-2">
        <div class="flex flex-wrap gap-2">
          <span
            v-for="permission in userPermissions"
            :key="permission"
            class="bg-base-200/50 text-sm px-2 py-1 rounded-md"
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

const { userRoles, userPermissions } = useAuthStore()
</script>
