<template>
  <header class="flex items-center justify-between leading-9">
    <div class="flex items-center gap-2">
      <Button variant="tertiary" icon="fa-arrow-left" @click="goToHome()" />
      <Icon v-if="watchFile" icon="fa-folder-open" class="text-gray-600" />
      <WatchFileEditTitle :watch-file="watchFile" :can-edit="canEdit" />
    </div>

    <WatchFileEditAlert v-if="watchFile && watchFile.status === WATCH_FILE_STATUS.ENABLED" />

    <WatchFileNavigationTabs :watch-file-id="effectiveWatchFileId" />
  </header>
</template>

<script setup lang="ts">
import { Button, Icon } from '@owlint/feathers-vue'
import { useQuery } from '@pinia/colada'
import { getItemWatchFileQuery } from '@target/api/queries/watchFile'
import WatchFileNavigationTabs from '@target/components/watchFiles/WatchFileNavigationTabs.vue'
import { useToast } from '@target/composables/useToast'
import { useWatchFileStore } from '@target/stores/watchFile'
import { RouteNames } from '@target/types/route-names'
import { WATCH_FILE_STATUS } from '@target/types/watchFile'
import { computed, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import WatchFileEditAlert from './EditSection/WatchFileEditAlert.vue'
import WatchFileEditTitle from './EditSection/WatchFileEditTitle.vue'

const props = defineProps<{
  watchFileId?: string
}>()

const toast = useToast()
const { t } = useI18n()
const router = useRouter()

const watchFileStore = useWatchFileStore()

// Effective watchFileId: uses store value after silent navigation, otherwise falls back to prop
const effectiveWatchFileId = computed(() => watchFileStore.currentWatchFileId || props.watchFileId)

const { data, error } = useQuery(getItemWatchFileQuery, () => ({
  id: effectiveWatchFileId.value!,
}))
const watchFile = computed(() => data.value ?? null)

const goToHome = async () => {
  await router.push({ name: RouteNames.HOME })
}

watch(error, (newError) => {
  if (newError) {
    toast.error(t('watch_files.toast.error.load'))
  }
})

const canEdit = computed(() => !!watchFile.value && watchFile.value.userEditable)
</script>
