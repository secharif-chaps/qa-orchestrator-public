<template>
  <section>
    <SourcesList :watch-file-id="watchFile?.id || ''" :readonly="readonly" />
  </section>
</template>

<script setup lang="ts">
import SourcesList from '~/components/watchFiles/SourcesList.vue'
import type { WatchFile } from '~/types/watchFile'

defineProps<{
  watchFile?: WatchFile | null
  readonly?: boolean
}>()
</script>
