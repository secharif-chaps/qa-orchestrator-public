<template>
  <div
    class="rounded-sm border p-4"
    :class="
      isCurrent
        ? 'border-sage-300 dark:border-primary-lighter-stroke bg-primary-lightest'
        : 'border-primary-lighter-stroke'
    "
  >
    <div class="flex items-center justify-between">
      <div class="flex items-center gap-3">
        <div
          class="flex h-10 w-10 items-center justify-center rounded-sm"
          :class="
            isCurrent
              ? 'border border-rose-200 bg-rose-100 text-rose-600 dark:bg-rose-900/30 dark:text-rose-400'
              : 'bg-primary-lightest text-neutral-black-font'
          "
        >
          <i class="fas fa-desktop"></i>
        </div>
        <div>
          <h3 class="text-sm font-medium">
            {{ $t('settings.security.sessions.webSession') }}
          </h3>
          <p class="text-neutral-black-font text-xs">
            <i class="fas fa-globe mr-1"></i>{{ session.ipAddress }}
          </p>
          <p class="text-neutral-black-font text-xs">
            <i class="fas fa-clock mr-1"></i>{{ formatRelativeTime(session.lastAccess) }}
          </p>
        </div>
      </div>
      <Tag
        v-if="isCurrent"
        variant="success"
        :label="$t('settings.security.sessions.current.badge')"
      />
      <Button
        v-else
        :label="$t('settings.security.actions.revoke')"
        variant="secondary"
        color="danger"
        size="sm"
        @click="handleRevoke"
      />
    </div>
  </div>
</template>

<script setup lang="ts">
import Tag from '@/components/ui/Tag.vue'
import type { Session } from '@/types/account'
import { Button } from '@owlint/feathers-vue'

const props = defineProps<{
  session: Session
  isCurrent: boolean
}>()

const emit = defineEmits<{
  revoke: [sessionId: string]
}>()

function formatRelativeTime(dateString: string): string {
  const date = new Date(dateString)
  const now = new Date()
  const diffMs = now.getTime() - date.getTime()
  const diffMins = Math.floor(diffMs / 60000)
  const diffHours = Math.floor(diffMs / 3600000)
  const diffDays = Math.floor(diffMs / 86400000)

  if (diffMins < 1) return 'Just now'
  if (diffMins < 60) return `${diffMins} minute${diffMins > 1 ? 's' : ''} ago`
  if (diffHours < 24) return `${diffHours} hour${diffHours > 1 ? 's' : ''} ago`
  if (diffDays < 7) return `${diffDays} day${diffDays > 1 ? 's' : ''} ago`

  return date.toLocaleDateString()
}

function handleRevoke() {
  emit('revoke', props.session.id)
}
</script>
