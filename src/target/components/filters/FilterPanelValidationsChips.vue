<template>
  <div
    v-for="item in status"
    :key="item"
    class="bg-sage-200 text-sage-800 flex items-center gap-1 rounded-sm p-1 text-sm"
  >
    <Icon icon="fa-file-lines" />
    <span class="shrink">{{ $t(`watch_files.documents.status.${item}`) }}</span>
    <button class="flex items-center justify-center" @click="emit('openEditFilter', 'validations')">
      <Icon icon="fa-pen" />
    </button>
    <button class="flex items-center justify-center" @click="handleRemove(item)">
      <Icon icon="fa-xmark" />
    </button>
  </div>
</template>

<script lang="ts" setup>
import { Icon } from '@owlint/feathers-vue'

interface Props {
  status: string[]
}

defineProps<Props>()

const emit = defineEmits<{
  openEditFilter: [filterType: string]
  remove: [status: string]
}>()

const handleRemove = (status: string) => {
  emit('remove', status)
}
</script>
