<template>
  <WatchFileSkeleton v-if="isLoading" />
</template>

<script setup lang="ts">
import { useQuery } from '@pinia/colada';
import { watchEffect } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { getItemWatchFileQuery } from '~/api/queries/watchFile';
import WatchFileSkeleton from '~/components/skeletons/WatchFileSkeleton.vue';
import { RouteNames } from '~/types/route-names';
import { WATCH_FILE_STATUS } from '~/types/watchFile';

const route = useRoute();
const router = useRouter();

definePage({
  meta: {
    layout: 'watch-file',
  },
});

// Fetch watch file data
const { data: watchFile, isLoading } = useQuery(getItemWatchFileQuery, () => ({
  id: route.params.id as string,
}));

// Redirect only when data is properly fetched
watchEffect(async () => {
  if (watchFile.value && !isLoading.value) {
    const watchFileId = route.params.id as string;
    const redirectTo =
      watchFile.value?.status === WATCH_FILE_STATUS.ENABLED
        ? RouteNames.WATCH_FILES_RADAR
        : RouteNames.WATCH_FILES_SCOPE;
    await router.push({
      name: redirectTo,
      params: { id: watchFileId },
      query: {
        ...route.query,
      },
    });
  }
});
</script>
