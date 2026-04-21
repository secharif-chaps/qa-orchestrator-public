<template>
  <RouterLink
    :to="activityRoute"
    class="hover:bg-sage-100 dark:hover:bg-sage-800 -mx-2 flex cursor-pointer items-center gap-2 self-stretch rounded-md px-2 py-1.5 transition-colors"
  >
    <AvatarInitials :name="activity.owner" />

    <div class="flex min-w-0 flex-1 flex-col items-start justify-center">
      <!-- User name + action + action icon -->
      <div class="flex items-center gap-0.5">
        <p
          class="font-regular text-neutral-black-font truncate text-base leading-5 dark:text-white"
        >
          {{ username }} {{ actionLabel }}
        </p>
        <Icon :icon="actionIcon" lib="far" class="text-primary-font text-xs" />
      </div>
      <!-- Resource name -->
      <p class="font-regular text-primary-font truncate text-sm leading-4">
        {{ activity.name }}
      </p>
      <!-- Timestamp -->
      <div class="text-neutral-font flex items-center gap-0.5 text-sm leading-4">
        <Icon icon="fa-clock" lib="far" class="text-xs" />
        <span>{{ time }}</span>
      </div>
    </div>
  </RouterLink>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { Icon } from '@owlint/feathers-vue'
import AvatarInitials from '@/components/ui/AvatarInitials.vue'
import type { Activity } from '@/types/organization'
import { formatRelativeTime } from '@/utils/time'

interface Props {
  activity: Activity
}

const { activity } = defineProps<Props>()

const { t } = useI18n()

const username = computed(() => activity.owner || 'Unknown')

// Icon represents the action performed (created = plus, modified = pen)
const actionLabel = computed(() => t('dashboard.home.recentActivities.created'))
const actionIcon = computed(() => (activity.type === 'folder' ? 'fa-folder' : 'fa-building'))

const time = computed(() => formatRelativeTime(activity.created_at))

const activityRoute = computed(() => {
  if (activity.type === 'folder' && activity.id) {
    return { name: '/folders/[folderId]/(folderId)' as const, params: { folderId: activity.id } }
  }
  // Company - needs both folderId and id to build the route
  if (activity.folder_id && activity.id) {
    return {
      name: '/folders/[folderId]/companies/[companyId]/' as const,
      params: { folderId: activity.folder_id, companyId: activity.id },
    }
  }

  return { name: '/folders/(list)' as const }
})
</script>
