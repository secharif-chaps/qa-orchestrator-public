<template>
  <div
    class="flex items-center gap-3 p-3 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors cursor-pointer"
    @click="handleClick"
  >
    <Badge variant="secondary" color="pink" icon="fa fa-building" />
    <div class="flex-1 min-w-0">
      <h4 class="text-sm font-medium text-gray-900 dark:text-white truncate">
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
  console.log(props.folderId)
  if (props.folderId) {
    router.push(`/folders/${props.folderId}/companies/${props.id}`)
  }
}
</script>
