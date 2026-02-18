<template>
  <Button
    :variant
    icon="fa-user"
    :title="title || $t('watch_files.actions.share')"
    :aria-label="ariaLabel || $t('watch_files.actions.share')"
    size="sm"
    v-bind="$attrs"
    :class="{ 'pointer-events-none': isReadOnly }"
    @click.stop="handleClick"
  >
    {{ labelText }}
  </Button>
</template>

<script setup lang="ts">
import { Button } from '@owlint/feathers-vue'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import type { WatchFile } from '~/types/watchFile'

const { t } = useI18n()

interface Props {
  watchFile: WatchFile
  variant?: 'primary' | 'secondary' | 'tertiary'
  showLabel?: boolean
  showCount?: boolean
  title?: string
  ariaLabel?: string
  isReadOnly?: boolean
}

const {
  watchFile,
  variant = 'tertiary',
  title = '',
  ariaLabel = '',
  showLabel = false,
  showCount = false,
  isReadOnly = false,
} = defineProps<Props>()

const emit = defineEmits<{
  (e: 'share-watch-file', watchFile: WatchFile): void
}>()

const labelText = computed<string>(() => {
  if (showCount) {
    const count = watchFile?.watchFileUsersCount || 0
    return t('watch_files.persons', { count })
  }

  if (showLabel) {
    return t('watch_files.actions.share')
  }

  return ''
})

function handleClick() {
  if (watchFile && !isReadOnly) {
    emit('share-watch-file', watchFile)
  }
}
</script>
