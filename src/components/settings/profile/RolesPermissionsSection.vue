<template>
  <div v-if="isDev" class="bg-bg1 border border-border-2 rounded-lg">
    <div class="px-6 py-4 border-b border-border-2">
      <h2 class="text-lg font-semibold">{{ $t('settings.profile.roles.title') }}</h2>
      <p class="text-sm text-secondary mt-1">{{ $t('settings.profile.roles.description') }}</p>
    </div>
    <div class="px-6 py-6">
      <div class="flex flex-col gap-2">
        <div class="flex flex-wrap gap-2" v-if="userRoles && userRoles.length > 0">
          <OBadge v-for="role in userRoles" :key="role" color="blue" :text="role">
            {{ role }}
          </OBadge>
        </div>
        <div class="flex flex-wrap gap-2">
          <OBadge
            v-for="permission in userPermissions"
            :key="permission"
            color="rose"
            :text="permission"
          >
            {{ permission }}
          </OBadge>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { useAuthStore } from '@/stores/auth'
import { OBadge } from '@owlint/feathers-vue'
import { computed } from 'vue'

const isDev = computed(() => import.meta.env.DEV)

const { userRoles, userPermissions } = useAuthStore()
</script>
