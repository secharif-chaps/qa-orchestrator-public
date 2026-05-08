<template>
  <div
    class="hover:bg-sage-100 dark:hover:bg-sage-800 -mx-2 flex cursor-pointer items-center gap-1.5 rounded-md px-2 py-1.5 transition-colors duration-200"
    @click="handleClick"
  >
    <!-- Icon + Text -->
    <div class="flex min-w-0 flex-1 items-center gap-3 pl-1">
      <!-- Module icon badge -->
      <div
        class="flex size-4.5 shrink-0 items-center justify-center rounded-xs p-px"
        :class="isWatchfile ? 'bg-cherry-alt' : 'bg-indigo-alt'"
      >
        <Icon
          :icon="isWatchfile ? 'fa-file-lines' : 'fa-buildings'"
          lib="far"
          class="text-xs"
          :class="isWatchfile ? 'text-cherry-font' : 'text-indigo-font'"
        />
      </div>

      <!-- Text content -->
      <div class="flex min-w-0 flex-1 flex-col items-start justify-center whitespace-nowrap">
        <p class="font-regular text-primary-dark-font w-full truncate text-base leading-5">
          {{ name }}
        </p>
        <p class="font-regular text-primary-font truncate text-sm leading-4">
          {{ $t('common.bulletSeparated', { left: folderName, right: timeAgo }) }}
        </p>
      </div>
    </div>

    <!-- Sharing tag -->
    <Tag
      intent="neutral"
      size="xs"
      :icon="isShared ? 'fa-regular fa-users' : 'fa-regular fa-lock'"
      :label="
        isShared
          ? $t('dashboard.home.recentProjects.badge.collaborative')
          : $t('dashboard.home.recentProjects.badge.private')
      "
    />
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import { Icon, Tag } from '@owlint/feathers-vue'
import { PROJECT_TYPES, type ProjectType } from '@/types/module'

interface Props {
  id: number
  name: string
  folderName: string
  folderId?: string | null
  timeAgo: string
  isShared?: boolean
  type?: ProjectType
}

const { id, folderId, isShared = false, type } = defineProps<Props>()

const router = useRouter()

const isWatchfile = computed(() => type === PROJECT_TYPES.WATCHFILE)

const handleClick = () => {
  if (folderId) {
    router.push(`/folders/${folderId}/companies/${id}`)
  }
}
</script>
