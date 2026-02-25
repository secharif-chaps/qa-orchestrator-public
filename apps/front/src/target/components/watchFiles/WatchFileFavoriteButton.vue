<template>
  <Button
    variant="tertiary"
    icon="fa-star"
    size="sm"
    :lib="iconFilled"
    :title="
      watchFile.isFavorite
        ? $t('watch_files.actions.remove_favorite')
        : $t('watch_files.actions.add_favorite')
    "
    :aria-label="
      watchFile.isFavorite
        ? $t('watch_files.actions.remove_favorite')
        : $t('watch_files.actions.add_favorite')
    "
    :loading="isLoading"
    @click="toggleFavorite(watchFile)"
  />
</template>

<script setup lang="ts">
import { Button } from '@owlint/feathers-vue'
import { useToggleWatchFileFavorite } from '@target/api/mutations/watchFile'
import type { WatchFile } from '@target/types/watchFile'
import { computed } from 'vue'

const { watchFile } = defineProps<{
  watchFile: WatchFile
}>()

const { toggleFavorite, isLoading } = useToggleWatchFileFavorite()

const iconFilled = computed(() => (watchFile.isFavorite ? 'fa-solid' : 'fa-regular'))
</script>
