<template>
  <td class="px-4 py-3">
    <div class="gap-xs flex items-start">
      <div>
        <span v-if="cell">{{ cell.value }}</span>
        <span v-else class="text-neutral-black-font">{{ t('common.na') }}</span>
        <p v-if="cell?.context" class="text-neutral-black-font text-xs italic">
          {{ cell.context }}
        </p>
      </div>
      <Source v-if="cell?.source" :source="cell.source" />
    </div>
  </td>
</template>

<script lang="ts" setup>
import { useI18n } from 'vue-i18n'
import Source from '@/components/company/Source.vue'

const { t } = useI18n()

interface MetricCell {
  value: string
  source: string | null
  context?: string | null
}

interface Props {
  cell: MetricCell | null
}

defineProps<Props>()
</script>
