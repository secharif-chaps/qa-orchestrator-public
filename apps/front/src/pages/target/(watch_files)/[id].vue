<template>
  <div id="watchfile-layout" class="relative flex h-full flex-col gap-4 overflow-hidden">
    <!-- Watchfile Header - fixed at top, never scrolls -->
    <div class="shrink-0 px-6 pt-4">
      <WatchFileHeader :watch-file-id="watchFileId" />
    </div>
    <!-- Page-specific content - takes remaining space below header -->
    <div class="min-h-0 flex-1">
      <RouterView />
    </div>
  </div>
</template>

<script lang="ts" setup>
import { useQuery } from '@pinia/colada'
import { getItemWatchFileQuery } from '@target/api/queries/watchFile'
import { WATCHFILES_SUBSCRIBE_KEYS } from '@target/api/watchFile'
import WatchFileHeader from '@target/components/watchFiles/WatchFileHeader.vue'
import { useMercure } from '@target/composables/useMercure'
import { useWatchFileTitle } from '@target/composables/useWatchFileTitle'
import { useWatchFileStore } from '@target/stores/watchFile'
import { useHead } from '@unhead/vue'
import { storeToRefs } from 'pinia'
import { computed, onBeforeUnmount, watch, watchEffect } from 'vue'
import { RouterView, useRoute } from 'vue-router'

const route = useRoute('/target/(watch_files)/[id]')
const { activeSubscriptions, unsubscribe } = useMercure()

const watchFileStore = useWatchFileStore()

const { isUserEditable } = storeToRefs(watchFileStore)

// Get ID from route params or from store (after silent navigation)
const routeWatchFileId = computed<string | undefined>(() => {
  const id = route.params.id
  return id
})

// CurrentWatchFileId is only set after silent navigation from /new. Not needed otherwise.
watch(
  routeWatchFileId,
  () => {
    watchFileStore.currentWatchFileId = null
  },
  { immediate: true },
)

const watchFileId = computed<string | undefined>(
  () => watchFileStore.currentWatchFileId || routeWatchFileId.value,
)

// Automatic title management for all watchfile pages
const { data } = useQuery(() =>
  getItemWatchFileQuery({
    id: watchFileId.value!,
  }),
)
const watchFile = computed(() => data.value ?? null)

const titleManager = useWatchFileTitle({ watchFile })

watchEffect(() => {
  isUserEditable.value = watchFile.value?.userEditable ?? false
})

useHead(() => ({
  title: titleManager.currentTitleTruncated.value,
}))

onBeforeUnmount(() => {
  for (const subscription of activeSubscriptions.value) {
    if (subscription.subscribeKey.includes(WATCHFILES_SUBSCRIBE_KEYS.root)) {
      unsubscribe(subscription.subscribeKey)
    }
  }
})
</script>
