<template>
  <div class="flex h-full flex-col">
    <div class="flex min-h-0 flex-1">
      <aside
        v-if="isUserEditable || !watchFile"
        class="hidden w-1/3 overflow-y-auto bg-slate-50 md:block"
        style="min-width: 24rem; max-width: 36rem"
      >
        <WatchFileAssistant
          :watch-file-id="effectiveWatchFileId"
          :watch-file-status="watchFile?.status"
          :is-read-only="isReadOnly"
          @watch-file-created="handleWatchFileCreated"
        />
      </aside>

      <main class="flex-1 overflow-y-auto">
        <ConfigSection :watch-file="watchFile" :is-read-only="isReadOnly" />
      </main>
    </div>
  </div>
</template>

<script setup lang="ts">
import { useQuery } from '@pinia/colada';
import { computed } from 'vue';
import { useRouter } from 'vue-router';
import { getItemWatchFileQuery } from '~/api/queries/watchFile';
import ConfigSection from '~/components/watchFiles/EditSection/ConfigSection.vue';
import WatchFileAssistant from '~/components/watchFiles/EditSection/WatchFileAssistant.vue';
import { useWatchFileStore } from '~/stores/watchFile';
import { RouteNames } from '~/types/route-names';
import { WATCH_FILE_STATUS } from '~/types/watchFile';

const props = defineProps<{
  watchFileId?: string;
}>();

const router = useRouter();
const watchFileStore = useWatchFileStore();

// We used to navigate from new/ to edit/ route after watchfile creation.
// Navigation is now silent, i.e. the URL is updated without actually navigating.
// Hence the use of effectiveWatchFileId: uses store value after watchfile creation, otherwise falls back to prop
const effectiveWatchFileId = computed(
  () => watchFileStore.currentWatchFileId || props.watchFileId,
);

const handleWatchFileCreated = (newId: string) => {
  // Update store state (shared with layout and header)
  watchFileStore.currentWatchFileId = newId;

  // Silently update URL without triggering navigation/refresh
  const resolved = router.resolve({
    name: RouteNames.WATCH_FILES_SCOPE,
    params: { id: newId },
  });
  window.history.replaceState(window.history.state, '', resolved.href);
};

const { data } = useQuery(getItemWatchFileQuery, () => ({
  id: effectiveWatchFileId.value!,
}));
const watchFile = computed(() => data.value ?? undefined);

const isReadOnly = computed(() => {
  return (
    watchFile.value?.status === WATCH_FILE_STATUS.ENABLED ||
    watchFile.value?.status === WATCH_FILE_STATUS.ARCHIVED
  );
});

const isUserEditable = computed(() => {
  return !!watchFile.value?.userEditable;
});
</script>
