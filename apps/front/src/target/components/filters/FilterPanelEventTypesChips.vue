<template>
  <div
    v-for="eventType in eventTypes"
    :key="eventType"
    class="bg-sage-200 text-sage-600 flex items-center gap-1 rounded-sm p-1 text-sm"
  >
    <Icon icon="fa-bullhorn" />
    <span class="shrink">{{ eventTypeLabelMap[eventType] ?? eventType }}</span>
    <button class="flex items-center justify-center" @click="emit('openEditFilter', 'sources')">
      <Icon icon="fa-pen" />
    </button>
    <button class="flex items-center justify-center" @click="handleRemove(eventType)">
      <Icon icon="fa-xmark" />
    </button>
  </div>
</template>

<script lang="ts" setup>
import { Icon } from '@owlint/feathers-vue'
import type { WatchFileEventType } from '@target/types/watchFile'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()

const eventTypeLabelMap = computed<Record<string, string>>(() => ({
  commercial_business: t('target.watchFiles.analysis.event.type.commercial_business'),
  financial: t('target.watchFiles.analysis.event.type.financial'),
  market_competitors: t('target.watchFiles.analysis.event.type.market_competitors'),
  organizational_hr: t('target.watchFiles.analysis.event.type.organizational_hr'),
  regulatory_political: t('target.watchFiles.analysis.event.type.regulatory_political'),
  societal_environmental: t('target.watchFiles.analysis.event.type.societal_environmental'),
  technological_rd: t('target.watchFiles.analysis.event.type.technological_rd'),
}))

interface Props {
  eventTypes: WatchFileEventType[]
}

defineProps<Props>()

const emit = defineEmits<{
  openEditFilter: [filterType: string]
  remove: [id: WatchFileEventType]
}>()

const handleRemove = (id: WatchFileEventType) => {
  emit('remove', id)
}
</script>
