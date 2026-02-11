<template>
  <RouterLink
    :to="activityRoute"
    class="flex items-start gap-3 rounded-lg p-2 -m-2 transition-colors hover:bg-base-200 cursor-pointer"
  >
    <!-- Icon with badge -->
    <div class="relative flex-shrink-0">
      <Badge variant="secondary" color="sage" :icon="icon" />
      <div class="absolute -bottom-0.5 -right-0.5">
        <Badge variant="secondary" color="sage" icon="fa fa-plus" size="xs" />
      </div>
    </div>

    <div class="flex-1 min-w-0">
      <!-- Company/Folder Name -->
      <p class="text-sm font-semibold text-gray-900 dark:text-white">
        {{ activity.name }}
      </p>
      <!-- Meta info: user and timestamp -->
      <div class="flex items-center gap-1 text-xs text-gray-500 dark:text-gray-400 mt-0.5">
        <span class="flex items-center gap-1">
          <i class="fa fa-clock"></i>
          <span>{{ time }}</span>
        </span>
        <span>{{ $t('home.recentActivities.by', { username: '@' + username }) }}</span>
      </div>
    </div>
  </RouterLink>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { Badge } from '@owlint/feathers-vue'
import type { Activity } from '@/types/organization'
import { formatRelativeTime } from '@/utils/time'

interface Props {
  activity: Activity
}

const { activity } = defineProps<Props>()

const icon = computed(() =>
  activity.type === 'company' ? 'fa fa-building' : 'fa fa-folder',
)

const username = computed(() => activity.owner || 'Unknown')

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
