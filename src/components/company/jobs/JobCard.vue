<template>
  <div
    class="p-4 bg-sage-light border border-primary-stroke rounded-lg transition-all duration-300"
  >
    <div class="flex justify-between items-start">
      <div>
        <h3 class="text-lg font-semibold text-secondary">
          {{ jobTitle }}
        </h3>
        <div class="mt-2 space-y-2">
          <Tag v-if="jobLocation" variant="primary" size="sm" :icon="'fa fa-map-marker'" >
            {{ jobLocation }}
          </Tag>
          <Tag v-if="jobDepartment" variant="primary" size="sm" :icon="'fa fa-building'" class="block">
            {{ jobDepartment }}
          </Tag>
          <div v-if="jobPostedDate" class="flex items-center text-sm text-secondary">
            <i class="fa fa-calendar w-4"></i>
            {{ t('jobs.card.postedDate') }} {{ jobPostedDate }}
          </div>
        </div>
      </div>
    </div>

    <div v-if="jobDescription" class="mt-4">
      <h4 class="font-medium mb-2">{{ t('jobs.card.description') }}</h4>
      <p class="text-sm text-secondary">
        {{ jobDescription }}
      </p>
    </div>

    <div v-if="jobRequirements" class="mt-4">
      <h4 class="font-medium mb-2">{{ t('jobs.card.requirements') }}</h4>
      <p class="text-sm text-secondary">
        {{ jobRequirements }}
      </p>
    </div>

    <div v-if="job.source" class="mt-4 flex justify-end">
      <Source :source="job.source" />
    </div>
  </div>
</template>

<script lang="ts" setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import Source from '../Source.vue'
import type { SourcedValue } from '@/types/company'
import { Tag } from '@owlint/feathers-vue'

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
const jobPostedDate = computed(() => extractValue(props.job.posted_date))
const jobDescription = computed(() => extractValue(props.job.description))
const jobRequirements = computed(() => extractValue(props.job.requirements))
</script>
