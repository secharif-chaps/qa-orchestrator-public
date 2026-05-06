<template>
  <WatchFileSkeleton v-if="isLoading" />
</template>

<script setup lang="ts">
import { useQuery } from '@pinia/colada'
import { getItemWatchFileQuery } from '@target/api/queries/watchFile'
import WatchFileSkeleton from '@target/components/skeletons/WatchFileSkeleton.vue'
import { RouteNames } from '@target/types/route-names'
import { WATCH_FILE_STATUS } from '@target/types/watchFile'
import { watchEffect } from 'vue'
import { useRoute, useRouter } from 'vue-router'

const route = useRoute('/target/(watch_files)/[id]/')
const router = useRouter()

// Fetch watchfile data
const { data: watchFile, isLoading } = useQuery(() =>
  getItemWatchFileQuery({
    id: route.params.id,
  }),
)

// Redirect only when data is properly fetched
watchEffect(async () => {
  if (watchFile.value && !isLoading.value) {
    const watchFileId = route.params.id
    const redirectTo =
      watchFile.value?.status === WATCH_FILE_STATUS.ENABLED
        ? RouteNames.WATCH_FILES_RADAR
        : RouteNames.WATCH_FILES_SCOPE
    await router.push({
      name: redirectTo,
      params: { id: watchFileId },
      query: {
        ...route.query,
      },
    })
  }
})
</script>
