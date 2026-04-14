<template>
  <div
    class="flex cursor-pointer items-center gap-1.5 bg-white transition-colors duration-200"
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
          {{ folderName }} • {{ timeAgo }}
        </p>
      </div>
    </div>

    <!-- Sharing tag -->
    <Tag
      variant="neutral"
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
import { Icon } from '@owlint/feathers-vue'
import Tag from '@/components/ui/Tag.vue'
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
