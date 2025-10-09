<template>
  <div
    class="flex items-center gap-3 p-3 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors cursor-pointer"
    @click="handleClick"
  >
    <Badge variant="secondary" color="accent" icon="fa fa-building" size="md" />
    <div class="flex-1 min-w-0">
      <h4 class="text-sm font-medium text-gray-900 dark:text-white truncate">
        {{ name }}
      </h4>
      <p class="text-xs text-gray-500 dark:text-gray-400">
        {{ folderName }} • {{ timeAgo }}
      </p>
    </div>
    <Tag v-if="badge" :variant="badge.variant" :label="badge.label" size="xs" />
  </div>
</template>

<script setup lang="ts">
import Badge from '@/components/ui/Badge.vue'
import Tag from '@/components/ui/Tag.vue'

interface Props {
  id: number
  name: string
  folderName: string
  folderId?: string | null
  timeAgo: string
  badge?: {
    variant: 'info' | 'success' | 'warning' | 'error' | 'primary' | 'slate'
    label: string
  }
}

const props = defineProps<Props>()

const emit = defineEmits<{
  click: [{ id: number; folderId: string | null | undefined }]
}>()

function handleClick() {
  emit('click', { id: props.id, folderId: props.folderId })
}
</script>
