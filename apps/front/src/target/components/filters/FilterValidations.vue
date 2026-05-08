<template>
  <div class="flex flex-col gap-3">
    <div class="flex flex-col items-start gap-1.5 pb-3">
      <Checkbox
        v-for="{ status, count } in statuses"
        :id="status"
        :key="status"
        v-model="selectedStatuses"
        :value="status"
        :disabled="!count"
        name="filter-validations"
      >
        <label :for="status" class="pl-2">
          <span>{{ documentStatusLabelMap[status] ?? status }}</span>
          <span class="text-gray-800">{{ $t('common.countSuffix', { count }) }}</span>
        </label>
      </Checkbox>
    </div>
    <Button
      v-if="selectedStatuses.length"
      class="w-fit"
      variant="tertiary"
      size="sm"
      icon="fa-rotate-left"
      @click="handleReset"
    >
      {{
        t('target.watchFiles.filters.type.reset', {
          name: t('target.watchFiles.filters.type.validations'),
        })
      }}
    </Button>
  </div>
</template>

<script lang="ts" setup>
import { Button, Checkbox } from '@owlint/feathers-vue'
import { computed } from 'vue'
import type { StatusFacet } from '@target/types/facet'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()

const documentStatusLabelMap = computed<Record<string, string>>(() => ({
  ai_rejected: t('target.watchFiles.documents.status.ai_rejected'),
  ai_uncertain: t('target.watchFiles.documents.status.ai_uncertain'),
  ai_validated: t('target.watchFiles.documents.status.ai_validated'),
  manual_accept: t('target.watchFiles.documents.status.manual_accept'),
  manual_empty: t('target.watchFiles.documents.status.manual_empty'),
  manual_refuse: t('target.watchFiles.documents.status.manual_refuse'),
}))

interface Props {
  statuses: StatusFacet[]
}

defineProps<Props>()

const selectedStatuses = defineModel<string[]>({ required: true })

const handleReset = () => {
  selectedStatuses.value = []
}
</script>
