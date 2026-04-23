<template>
  <Tag
    v-if="label"
    :intent="intent"
    size="sm"
    :icon="icon"
    :title="truncate ? label : undefined"
    :class="truncate ? 'job-tag-truncate' : ''"
  >
    {{ label }}</Tag
  >
</template>

<script setup lang="ts">
import { JOB_TAG_TYPES, type JobTagType } from '@/types/company'
import { Tag } from '@owlint/feathers-vue'
import { computed } from 'vue'

interface Props {
  label?: string
  type: JobTagType
  truncate?: boolean
}

const { type } = defineProps<Props>()

const icon = computed(() => (type === JOB_TAG_TYPES.LOCATION ? 'fa-location-dot' : 'fa-building'))
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
