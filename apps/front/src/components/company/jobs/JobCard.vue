<template>
  <div
    class="bg-primary-lightest gap-md p-xl flex flex-col rounded-3xl transition-all duration-300"
  >
    <div class="flex flex-col gap-2">
      <div class="text-neutral-black-font text-base font-bold">
        {{ jobTitle }}
      </div>
      <div class="flex flex-wrap gap-1">
        <JobTag :type="JOB_TAG_TYPES.DEPARTMENT" :label="jobDepartment" />
        <JobTag :type="JOB_TAG_TYPES.LOCATION" :label="jobLocation" />
      </div>
    </div>

    <div v-if="jobDescription" class="flex flex-col gap-1">
      <div class="text-base font-semibold">{{ t('screen.jobs.card.description') }}</div>
      <p class="text-neutral-black-font text-justify text-sm">
        {{ jobDescription }}
      </p>
    </div>

    <div v-if="jobRequirements" class="flex flex-col gap-1">
      <div class="text-base font-semibold">{{ t('screen.jobs.card.requirements') }}</div>
      <p class="text-neutral-black-font text-justify text-sm">
        {{ jobRequirements }}
      </p>
    </div>

    <div v-if="job.source" class="flex justify-end">
      <Source :source="job.source" />
    </div>
  </div>
</template>

<script lang="ts" setup>
import type { SourcedValue } from '@/types/company'
import { JOB_TAG_TYPES } from '@/types/company'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import JobTag from './JobTag.vue'
import Source from '../Source.vue'

const { t } = useI18n()

const props = defineProps<{
  job: {
    title?: string | SourcedValue<string>
    location?: string | SourcedValue<string>
    department?: string | SourcedValue<string>
    posted_date?: string | SourcedValue<string>
    description?: string | SourcedValue<string>
    requirements?: string | SourcedValue<string>
    source?: string
  }
}>()

// Helper to extract value from SourcedValue or return plain string
const extractValue = (field: string | SourcedValue<string> | undefined): string | undefined => {
  if (!field) return undefined
  if (typeof field === 'string') return field
  return field.value
}

// Computed properties to extract values
const jobTitle = computed(() => extractValue(props.job.title) || '')
const jobLocation = computed(() => extractValue(props.job.location))
const jobDepartment = computed(() => extractValue(props.job.department))
const jobDescription = computed(() => extractValue(props.job.description))
const jobRequirements = computed(() => extractValue(props.job.requirements))
</script>
