<template>
  <div :key="event.id" class="space-y-4">
    <Tag size="sm">
      {{ eventDateLabel }}
    </Tag>
    <div class="space-y-3 text-sm">
      <div>
        <p class="text-gray-700">
          {{ eventDescription }}
        </p>
      </div>
      <div>
        <p class="font-medium text-gray-900">
          {{ $t('target.watchFiles.analysis.event.actors') }}
        </p>
        <ul v-if="event.actors.length > 0" class="mt-1 list-outside list-disc space-y-1 pl-6">
          <li v-for="actor in event.actors" :key="actor.id" class="text-gray-700">
            {{ actor.name }}
          </li>
        </ul>
      </div>
    </div>
  </div>
</template>

<script lang="ts" setup>
import { Tag } from '@owlint/feathers-vue'
import { useLocalized } from '@target/composables/useLocalized'
import type { WatchFileEvent } from '@target/types/watchFileEvent'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

interface Props {
  event: WatchFileEvent
}

const { event } = defineProps<Props>()

const { d, t } = useI18n()
const { getLocalizedString } = useLocalized()

const eventDescription = getLocalizedString(event.description)

const eventDateLabel = computed<string | null>(() => {
  try {
    const endDate = new Date(event.endDate)
    const startDate = new Date(event.startDate)

    if (startDate.getTime() !== endDate.getTime()) {
      return t('target.watchFiles.analysis.event.date', {
        startDate: d(startDate, 'eventDateTime'),
        endDate: d(endDate, 'eventDateTime'),
      })
    }
    return d(startDate, 'eventDateTime')
  } catch {
    return t('target.watchFiles.analysis.event.unknown_date')
  }
})
</script>
