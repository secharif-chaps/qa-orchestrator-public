<template>
  <Drawer
    v-model="isOpen"
    :title="t('watch_files.activity.sources.history.drawer_title')"
    position="right"
    to="#watchfile-layout"
  >
    <template #subheader>
      <div class="pt-2">
        <SourceCard :source="source" variant="compact" />
      </div>
    </template>
    <div class="flex h-full flex-col px-2">
      <SourceMonitoring :source-id="source.id" />
    </div>
  </Drawer>
</template>

<script setup lang="ts">
import type { Source } from '~/types/source'
import { useI18n } from 'vue-i18n'
import Drawer from '~/components/global/Drawer.vue'
import SourceMonitoring from '~/components/watchFiles/ActivitySection/SourceMonitoring/SourceMonitoring.vue'
import SourceCard from '~/components/sources/SourceCard.vue'

const { t } = useI18n()

interface Props {
  source: Source
}

const { source } = defineProps<Props>()

const isOpen = defineModel<boolean>()
</script>
