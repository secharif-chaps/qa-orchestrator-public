<template>
  <Tag
    v-if="label"
    :variant="variant"
    :intent="intent"
    size="sm"
    :icon="icon"
    :label="label"
    :title="truncate ? label : undefined"
    :class="truncate ? 'job-tag-truncate' : ''"
  />
</template>

<script setup lang="ts">
import { Tag } from '@owlint/feathers-vue'
import { JOB_TAG_TYPES, type JobTagType } from '@/types/company'
import { computed } from 'vue'

interface Props {
  label?: string
  type: JobTagType
  truncate?: boolean
}

const { type } = defineProps<Props>()

const icon = computed(() =>
  type === JOB_TAG_TYPES.LOCATION ? 'fa fa-map-marker' : 'fa fa-building',
)
const variant = computed(() => 'primary' as const)
const intent = computed(() => (type === JOB_TAG_TYPES.LOCATION ? 'neutral' : undefined))
</script>

<style scoped>
.job-tag-truncate {
  max-width: 100%;
  overflow: hidden;
}

.job-tag-truncate :deep(span) {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
</style>
