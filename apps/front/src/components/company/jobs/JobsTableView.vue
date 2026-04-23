<template>
  <Table
    :fields="fields"
    :items="jobs"
    :row-key="(item: JobOffer) => extractValue(item.title) || ''"
  >
    <template #cell(title)="{ item }">
      <td class="px-4 py-3 align-text-top text-sm font-medium">
        {{ extractValue(item.title) }}
      </td>
    </template>

    <template #cell(department)="{ item }">
      <td class="px-4 py-3 align-text-top">
        <Tooltip v-if="extractValue(item.department)" arrow class="max-w-25">
          <JobTag
            :type="JOB_TAG_TYPES.DEPARTMENT"
            :label="extractValue(item.department)"
            truncate
          />
          <template #tooltip>{{ extractValue(item.department) }}</template>
        </Tooltip>
        <JobTag v-else :type="JOB_TAG_TYPES.DEPARTMENT" :label="extractValue(item.department)" />
      </td>
    </template>

    <template #cell(location)="{ item }">
      <td class="px-4 py-3 align-text-top">
        <Tooltip v-if="extractValue(item.location)" arrow class="max-w-25">
          <JobTag :type="JOB_TAG_TYPES.LOCATION" :label="extractValue(item.location)" truncate />
          <template #tooltip>{{ extractValue(item.location) }}</template>
        </Tooltip>
        <JobTag v-else :type="JOB_TAG_TYPES.LOCATION" :label="extractValue(item.location)" />
      </td>
    </template>

    <template #cell(description)="{ item }">
      <td class="px-4 py-3 text-justify align-text-top text-sm">
        {{ extractValue(item.description) || '-' }}
      </td>
    </template>

    <template #cell(requirements)="{ item }">
      <td class="px-4 py-3 text-justify align-text-top text-sm">
        {{ extractValue(item.requirements) || '-' }}
      </td>
    </template>

    <template #empty>
      <div class="p-8 text-center">
        <p class="text-neutral-black-font text-sm">
          {{ t('screen.jobs.listings.noResults', { query: '' }) }}
        </p>
      </div>
    </template>
  </Table>
</template>

<script setup lang="ts">
import type { SourcedValue } from '@/types/company'
import { JOB_TAG_TYPES } from '@/types/company'
import { Table, Tooltip } from '@owlint/feathers-vue'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import JobTag from './JobTag.vue'

const { t } = useI18n()

interface JobOffer {
  title?: string | SourcedValue<string>
  location?: string | SourcedValue<string>
  department?: string | SourcedValue<string>
  posted_date?: string | SourcedValue<string>
  description?: string | SourcedValue<string>
  requirements?: string | SourcedValue<string>
  source?: string
}

defineProps<{
  jobs: JobOffer[]
}>()

const fields = computed(() => [
  { key: 'title', label: t('screen.jobs.listings.columns.offer'), class: 'min-w-[120px] w-[15%]' },
  {
    key: 'department',
    label: t('screen.jobs.listings.columns.type'),
    class: 'min-w-[100px] w-[10%]',
  },
  {
    key: 'location',
    label: t('screen.jobs.listings.columns.location'),
    class: 'min-w-[100px] w-[10%]',
  },
  { key: 'description', label: t('screen.jobs.listings.columns.description') },
  { key: 'requirements', label: t('screen.jobs.listings.columns.requirements') },
])

const extractValue = (field: string | SourcedValue<string> | undefined): string | undefined => {
  if (!field) return undefined
  if (typeof field === 'string') return field
  return field.value
}
</script>
