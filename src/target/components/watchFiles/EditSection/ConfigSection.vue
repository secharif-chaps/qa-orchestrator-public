<template>
  <div class="flex h-full w-full flex-col border-l border-gray-200">
    <div class="flex flex-1 flex-col items-stretch gap-4 overflow-y-auto px-8 py-4">
      <template v-if="watchFile || !loading">
        <ReferenceSubjectSection :watch-file="watchFile" />
        <ActorsSection :watch-file="watchFile" :readonly="isReadOnly || !isUserEditable" />
        <SourcesSection :watch-file="watchFile" :readonly="isReadOnly || !isUserEditable" />
      </template>
    </div>
    <footer v-if="watchFile" class="sticky bottom-0 z-10 bg-white p-4 shadow-2xl">
      <div class="flex flex-wrap items-center justify-between gap-14">
        <div class="flex flex-wrap items-center gap-6">
          <WatchFileShareButton
            :watch-file="watchFile"
            :disabled="!watchFile"
            show-count
            :is-read-only="!isUserEditable"
            @share-watch-file="shareWatchFile"
          />
          <WatchFileDate
            :date-prefix="t('watch_files.prefix.createdAt')"
            :date="watchFile.createdAt"
          />
          <WatchFileDate
            :date-prefix="t('watch_files.prefix.updatedAt')"
            :date="watchFile.updatedAt"
          />
        </div>
        <WatchFileSelectStatus :watch-file="watchFile" :is-read-only="!isUserEditable" />
      </div>
    </footer>

    <WatchFileShareDialog
      v-model:is-open="shareDialogIsOpen"
      :selected-watch-file="selectedWatchFileForDialog"
      @close="shareDialogIsOpen = false"
    />
  </div>
</template>

<script setup lang="ts">
import ActorsSection from '@target/components/watchFiles/EditSection/ActorsSection.vue'
import ReferenceSubjectSection from '@target/components/watchFiles/EditSection/ReferenceSubjectSection.vue'
import SourcesSection from '@target/components/watchFiles/EditSection/SourcesSection.vue'
import WatchFileShareButton from '@target/components/watchFiles/WatchFileShareButton.vue'
import WatchFileShareDialog from '@target/components/watchFiles/WatchFileShareDialog.vue'
import { useWatchFileStore } from '@target/stores/watchFile'
import type { WatchFile } from '@target/types/watchFile'
import { storeToRefs } from 'pinia'
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import WatchFileDate from './WatchFileDate.vue'
import WatchFileSelectStatus from './WatchFileSelectStatus.vue'

interface Props {
  watchFile?: WatchFile
  isReadOnly?: boolean
}
defineProps<Props>()

const { t } = useI18n()

const watchFileStore = useWatchFileStore()
const { isUserEditable } = storeToRefs(watchFileStore)

const loading = computed(() => watchFileStore.isLoading)

const shareDialogIsOpen = ref(false)
const selectedWatchFileForDialog = ref<WatchFile | null>(null)

const shareWatchFile = (watchFile: WatchFile) => {
  selectedWatchFileForDialog.value = watchFile
  shareDialogIsOpen.value = true
}
</script>
