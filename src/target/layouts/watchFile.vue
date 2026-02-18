<template>
  <div id="watchfile-layout" class="relative flex h-full flex-col gap-4 overflow-hidden">
    <!-- Watch File Header - fixed at top, never scrolls -->
    <div class="shrink-0 px-6 pt-4">
      <WatchFileHeader :watch-file-id="watchFileId" />
    </div>
    <!-- Page-specific content - takes remaining space below header -->
    <div class="min-h-0 flex-1">
      <slot />
    </div>
  </div>
</template>

<script lang="ts" setup>
import { useQuery } from '@pinia/colada'
import { useHead } from '@unhead/vue'
import { computed, watch, watchEffect, onBeforeUnmount } from 'vue'
import { useRoute } from 'vue-router'
import { storeToRefs } from 'pinia'
import { getItemWatchFileQuery } from '~/api/queries/watchFile'
import { WATCHFILES_SUBSCRIBE_KEYS } from '~/api/watchFile'
import WatchFileHeader from '~/components/watchFiles/WatchFileHeader.vue'
import { useMercure } from '~/composables/useMercure'
import { useWatchFileTitle } from '~/composables/useWatchFileTitle'
import { useWatchFileStore } from '~/stores/watchFile'

const route = useRoute()
const { activeSubscriptions, unsubscribe } = useMercure()

const watchFileStore = useWatchFileStore()

const { isUserEditable } = storeToRefs(watchFileStore)

// Get ID from route params or from store (after silent navigation)
const routeWatchFileId = computed<string | undefined>(() => {
  const id = route.params.id
  return Array.isArray(id) ? id[0] : id
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
const { data } = useQuery(getItemWatchFileQuery, () => ({
  id: watchFileId.value!,
}))
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
