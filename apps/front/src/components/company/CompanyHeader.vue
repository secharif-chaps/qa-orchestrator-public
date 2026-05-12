<template>
  <PageHeader
    :title="company?.name ?? ''"
    :back-to="{ name: '/folders/[folderId]/(folderId)', params: { folderId } }"
    :back-label="t('screen.company.header.backToFolder')"
    :logo-name="company?.name"
    :logo-website="company?.website"
    :progress-segments="progressSegments"
    :show-progress="showProgressRing"
    :privacy="privacyState"
  >
    <template #buttons>
      <CompanyTranslation v-model="selectedLanguage" :company-id="companyId" />
      <Export />
      <span v-if="isOwner" :title="refreshButtonTooltip">
        <Button
          variant="tertiary"
          icon="fa-refresh"
          size="sm"
          :label="t('screen.company.refresh.button')"
          :disabled="hasRunningTasks || !allTasksSucceeded || !hasEnoughTokens"
          :loading="isRefreshing"
          @click="emit('openRefreshModal')"
        />
      </span>
      <CompanyDeleteButton :company="company" />
      <Button
        v-if="isDebugUser"
        variant="tertiary"
        size="sm"
        icon="fa-bug"
        icon-only
        :title="t('screen.company.debug.workflowTitle')"
        @click="emit('openTasksModal')"
      />
    </template>

    <template #info>
      <span
        v-if="currentMessage"
        :key="currentMessage"
        class="text-sage-600 shrink-0 text-sm whitespace-nowrap"
      >
        {{ currentMessage }}
      </span>
    </template>

    <template #actions>
      <CompanyHeaderTabs
        :folder-id="folderId"
        :company-id="companyId"
        :job-offers-count="company?.jobs?.offers?.length"
      />
    </template>
  </PageHeader>
</template>

<script lang="ts" setup>
import CompanyDeleteButton from '@/components/company/CompanyDeleteButton.vue'
import CompanyHeaderTabs from '@/components/company/CompanyHeaderTabs.vue'
import CompanyTranslation from '@/components/company/CompanyTranslation.vue'
import Export from '@/components/company/Export.vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import type { ProgressSegment } from '@/composables/useTaskProgress'
import type { Company } from '@/types/company'
import type { Folder } from '@/types/folder'
import { Button } from '@owlint/feathers-vue'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

interface Props {
  company: Company | undefined
  folder: Folder | undefined
  folderId: string
  companyId: string
  progressSegments: ProgressSegment[]
  showProgressRing: boolean
  currentMessage: string
  isOwner: boolean
  refreshButtonTooltip: string
  hasRunningTasks: boolean
  allTasksSucceeded: boolean
  hasEnoughTokens: boolean
  isRefreshing: boolean
  isDebugUser: boolean
}

const { folder } = defineProps<Props>()

interface Emits {
  openRefreshModal: []
  openTasksModal: []
}

const emit = defineEmits<Emits>()

const selectedLanguage = defineModel<string | undefined>('selectedLanguage')

const { t } = useI18n()

const privacyState = computed<'private' | 'shared' | null>(() => {
  if (!folder) return null
  if (folder.is_owner) return 'private'
  if (folder.share_role != null) return 'shared'
  return null
})
</script>
