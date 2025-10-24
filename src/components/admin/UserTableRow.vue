<template>
  <div class="px-6 py-4 transition-colors hover:bg-base-200/50">
    <div class="grid grid-cols-12 gap-4 items-center">
      <!-- Username -->
      <div class="col-span-3">
        <div class="font-medium">{{ user.username }}</div>
        <div v-if="user.status === 'revoked'" class="text-xs text-error-light-content mt-1">
          <i class="fa fa-ban mr-1"></i>
          {{ $t('admin.users.status.revoked', 'Revoked') }}
        </div>
      </div>

      <!-- Email -->
      <div class="col-span-3">
        <div class="text-sm text-secondary">{{ user.email }}</div>
      </div>

      <!-- Workspace -->
      <div class="col-span-3">
        <span
          v-if="user.workspace_name"
          class="text-sm bg-primary-light text-primary-light-content border border-primary-stroke px-2 py-1 rounded"
        >
          {{ user.workspace_name }}
        </span>
        <span v-else class="text-sm text-secondary italic">
          {{ $t('admin.users.noWorkspace', 'No workspace') }}
        </span>
      </div>

      <!-- Created Date -->
      <div class="col-span-2">
        <div class="text-sm text-secondary">
          {{ formatDate(user.created_at) }}
        </div>
      </div>

      <!-- Actions -->
      <div class="col-span-1">
        <div class="flex justify-end">
          <Button
            icon-only
            variant="secondary"
            icon="fa fa-crosshairs"
            :label="$t('admin.users.changeWorkspace', 'Change workspace')"
            @click="$emit('assign-workspace', user)"
          />
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import Button from '@/components/ui/Button.vue'
import type { AdminUserResponse } from '@/types/admin-user'

interface Props {
  user: AdminUserResponse
}

defineProps<Props>()

defineEmits<{
  'assign-workspace': [user: AdminUserResponse]
}>()

// Format date helper
const formatDate = (dateString: string) => {
  return new Date(dateString).toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
  })
}
</script>
