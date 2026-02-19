<template>
  <span
    class="chip border-sage-200 bg-sage-50 text-sage-700 my-1 mr-1 flex h-8 items-center rounded-sm border px-2 py-0 text-sm"
  >
    {{ user.email }}
    <button
      type="button"
      class="text-sage-400 hover:text-sage-700 ml-1 focus:outline-none"
      :aria-label="$t('watch_files.user.remove')"
      @click="$emit('remove', user.id)"
    >
      &times;
    </button>
  </span>
</template>

<script setup lang="ts">
import type { User } from '@target/types/user';

defineProps<{
  user: User
}>()

defineEmits<{
  (e: 'remove', id: string): void
}>()
</script>

<style scoped>
.chip {
  justify-content: space-between;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
</style>
