<template>
  <div
    v-for="item in status"
    :key="item"
    class="bg-sage-200 text-sage-800 flex items-center gap-1 rounded-sm p-1 text-sm"
  >
    <Icon icon="fa-file-lines" />
    <span class="shrink">{{ documentStatusLabelMap[item] ?? item }}</span>
    <button class="flex items-center justify-center" @click="emit('openEditFilter', 'validations')">
      <Icon icon="fa-pen" />
    </button>
    <button class="flex items-center justify-center" @click="handleRemove(item)">
      <Icon icon="fa-xmark" />
    </button>
  </div>
</template>

<script lang="ts" setup>
import { Icon } from '@owlint/feathers-vue'
import { computed } from 'vue'
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
  status: string[]
}

defineProps<Props>()

const emit = defineEmits<{
  openEditFilter: [filterType: string]
  remove: [status: string]
}>()

const handleRemove = (status: string) => {
  emit('remove', status)
}
</script>
