<template>
  <PageHeader
    :title="watchFile?.name ?? ''"
    :back-to="{ name: RouteNames.HOME }"
    :back-label="t('target.watchFiles.actions.back')"
    :editable="canEdit"
    @title-update="handleTitleUpdate"
  >
    <template v-if="watchFile?.status === WATCH_FILE_STATUS.ENABLED" #info>
      <WatchFileEditAlert />
    </template>

    <template #actions>
      <WatchFileNavigationTabs :watch-file-id="effectiveWatchFileId" />
    </template>
  </PageHeader>
</template>

<script setup lang="ts">
import PageHeader from '@/components/ui/PageHeader.vue'
import { useQuery } from '@pinia/colada'
import { useUpdateWatchFile } from '@target/api/mutations/watchFile'
import { getItemWatchFileQuery } from '@target/api/queries/watchFile'
import WatchFileNavigationTabs from '@target/components/watchFiles/WatchFileNavigationTabs.vue'
import { useToast } from '@/composables/useToast'
import { useWatchFileStore } from '@target/stores/watchFile'
import { RouteNames } from '@target/types/route-names'
import { WATCH_FILE_STATUS } from '@target/types/watchFile'
import { computed, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import WatchFileEditAlert from './EditSection/WatchFileEditAlert.vue'

const props = defineProps<{
  watchFileId?: string
}>()

const toast = useToast()
const { t } = useI18n()

const watchFileStore = useWatchFileStore()
const { updateTask } = useUpdateWatchFile()

// Effective watchFileId: uses store value after silent navigation, otherwise falls back to prop
const effectiveWatchFileId = computed(() => watchFileStore.currentWatchFileId || props.watchFileId)

const { data, error } = useQuery(() =>
  getItemWatchFileQuery({
    id: effectiveWatchFileId.value!,
  }),
)
const watchFile = computed(() => data.value ?? null)

watch(error, (newError) => {
  if (newError) {
    toast.error(t('target.watchFiles.toast.error.load'))
  }
})

const canEdit = computed(() => !!watchFile.value && watchFile.value.userEditable)

const handleTitleUpdate = (newTitle: string) => {
  if (!watchFile.value) return
  updateTask({ id: watchFile.value.id, data: { name: newTitle } })
}
</script>
