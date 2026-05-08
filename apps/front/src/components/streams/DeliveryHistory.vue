<template>
  <div class="flex flex-col gap-4">
    <h3 class="text-lg font-semibold">{{ t('stream.delivery.title') }}</h3>

    <!-- Table -->
    <Table :loading="isLoading" :items="deliveries" :fields="columns">
      <template #cell(status)="{ item }">
        <td class="px-4 py-3">
          <Tag
            :intent="deliveryStatusIntent(item.status)"
            :label="deliveryStatusLabel[item.status]"
            size="sm"
          />
        </td>
      </template>

      <template #cell(event_id)="{ value }">
        <td class="px-4 py-3">
          <span class="text-sm">{{ value }}</span>
        </td>
      </template>

      <template #cell(delivered_at)="{ value }">
        <td class="px-4 py-3">
          <span class="text-secondary text-sm">
            {{ value ? formatDate(value) : EMPTY_DASH }}
          </span>
        </td>
      </template>

      <template #cell(attempt_count)="{ value }">
        <td class="px-4 py-3">
          <span class="text-secondary text-sm">{{ value }}</span>
        </td>
      </template>

      <template #cell(error_message)="{ value }">
        <td class="px-4 py-3">
          <span v-if="value" class="text-error truncate text-sm" :title="value">
            {{ value }}
          </span>
          <span v-else class="text-secondary text-sm">{{ EMPTY_DASH }}</span>
        </td>
      </template>
    </Table>

    <!-- Empty state -->
    <p v-if="!isLoading && deliveries.length === 0" class="text-secondary text-sm">
      {{ t('stream.delivery.empty') }}
    </p>
  </div>
</template>

<script setup lang="ts">
import { useDateTime } from '@/composables/useDateTime'
import { deliveriesQuery } from '@/queries/streams'
import type { DeliveryStatus } from '@/types/stream'
import { Table, Tag } from '@owlint/feathers-vue'
import { useQuery } from '@pinia/colada'
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'

interface Props {
  streamId: string
}

const { streamId } = defineProps<Props>()
const { t } = useI18n()
const { formatDate } = useDateTime()

const EMPTY_DASH = '-'

const deliveryStatusLabel = computed<Record<string, string>>(() => ({
  delivered: t('stream.delivery.status.delivered'),
  failed: t('stream.delivery.status.failed'),
  pending: t('stream.delivery.status.pending'),
  skipped: t('stream.delivery.status.skipped'),
}))

const page = ref(1)
const perPage = ref(20)

const { data: deliveriesData, isLoading } = useQuery(() =>
  deliveriesQuery({
    streamId,
    page: page.value,
    perPage: perPage.value,
  }),
)

const deliveries = computed(() => deliveriesData.value?.data ?? [])

const columns = computed(() => [
  { key: 'status', label: t('stream.delivery.columns.status') },
  { key: 'event_id', label: t('stream.delivery.columns.eventType') },
  { key: 'delivered_at', label: t('stream.delivery.columns.deliveredAt') },
  { key: 'attempt_count', label: t('stream.delivery.columns.attempts') },
  { key: 'error_message', label: t('stream.delivery.columns.error') },
])

const deliveryStatusIntent = (status: DeliveryStatus) => {
  const map: Record<DeliveryStatus, 'warning' | 'success' | 'danger' | 'accent'> = {
    pending: 'warning',
    delivered: 'success',
    failed: 'danger',
    skipped: 'accent',
  }
  return map[status]
}
</script>
