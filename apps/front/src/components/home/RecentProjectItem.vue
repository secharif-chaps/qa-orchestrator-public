<template>
  <div
    class="flex cursor-pointer items-center gap-3 rounded-sm p-3 transition-colors hover:bg-gray-50 dark:hover:bg-gray-700/50"
    @click="handleClick"
  >
    <Badge variant="secondary" color="pink" icon="fa fa-building" />
    <div class="min-w-0 flex-1">
      <h4 class="truncate text-sm font-medium text-gray-900 dark:text-white">
        {{ name }}
      </h4>
      <p class="text-xs text-gray-500 dark:text-gray-400">{{ folderName }} • {{ timeAgo }}</p>
    </div>
    <Tag v-if="badge" :intent="badge.intent" :label="badge.label" size="xs" />
  </div>
</template>

<script setup lang="ts">
import { Badge, Tag } from '@owlint/feathers-vue'
import { useRouter } from 'vue-router'

interface Props {
  id: number
  name: string
  folderName: string
  folderId?: string | null
  timeAgo: string
  badge?: {
    intent: 'neutral' | 'accent' | 'success' | 'warning' | 'danger' | 'info'
    label: string
  }
}

const props = defineProps<Props>()

const router = useRouter()
function handleClick() {
  if (props.folderId) {
    router.push(`/folders/${props.folderId}/companies/${props.id}`)
  }
}
</script>
