<template>
  <div class="flex flex-col gap-4">
    <div v-if="isLoading" class="text-secondary text-sm">
      <Icon icon="fa-spinner" class="fa-spin mr-2" />{{ t('common.loading') }}
    </div>

    <div v-else-if="eventGroups" class="flex flex-col gap-6">
      <div v-for="group in eventGroups" :key="group.source" class="flex flex-col gap-2">
        <!-- Source group header -->
        <div class="flex items-center gap-2">
          <h4 class="text-sm font-semibold">{{ t('stream.sources', { source: group.source }) }}</h4>
          <Tag
            v-if="!group.available"
            intent="accent"
            size="xs"
            :label="t('stream.form.eventsComingSoon')"
          />
        </div>

        <!-- Event checkboxes -->
        <div class="flex flex-col items-start gap-2 pl-1">
          <Checkbox
            v-for="event in group.events"
            :id="`event-${event.type}`"
            :key="event.type"
            v-model="model"
            :value="event.type"
            :label="t('stream.events', { type: toEventCase(event.type) })"
            :disabled="!group.available"
            name="subscribed-events"
            :class="{ 'cursor-not-allowed opacity-50': !group.available }"
          />
        </div>
      </div>
    </div>

    <!-- Error message -->
    <p v-if="error" class="text-error text-xs">{{ error }}</p>
  </div>
</template>

<script setup lang="ts">
import { Checkbox, Icon, Tag } from '@owlint/feathers-vue'
import { useQuery } from '@pinia/colada'
import { eventTypesQuery } from '@/queries/streams'
import { useI18n } from 'vue-i18n'

interface Props {
  error?: string
}

defineProps<Props>()
const model = defineModel<string[]>({ required: true })
const { t } = useI18n()

const { data: eventGroups, isLoading } = useQuery(() => eventTypesQuery())

// Backend event types follow `{source}.{resource}.{action}` (e.g. screen.company.created).
// Flatten to camelCase for ICU `select` case names — dots are not valid identifiers.
const toEventCase = (eventType: string): string =>
  eventType
    .split('.')
    .map((part, i) => (i === 0 ? part : part.charAt(0).toUpperCase() + part.slice(1)))
    .join('')
</script>
