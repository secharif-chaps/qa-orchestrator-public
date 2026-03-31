<template>
  <DocumentDetailSkeleton v-if="isLoading" />

  <div v-else-if="document" class="scrollable flex-1 space-y-4 p-6 pb-26">
    <Tag v-if="document?.cfcRestricted" size="sm" icon="fa-lock">
      {{ $t('target.documents.detail.cfc') }}
    </Tag>

    <div class="">
      <div class="space-y-2">
        <template v-for="item in metadataItems" :key="item.key">
          <div v-if="item.value !== undefined" class="flex items-center gap-2">
            <span class="text-sm text-gray-600">{{ item.label }}</span>
            <UrlDomain
              v-if="item.key === 'source' && item.domain"
              :domain="item.domain"
              :url="item.url"
              :alt="item.label"
              class="text-sm font-medium text-gray-900"
            />
            <UrlDomain v-else-if="item.key === 'url'" :url="item.value" />
            <template v-else>
              <Icon v-if="item.leftIcon" :icon="item.leftIcon" class="h-4 w-4 text-gray-400" />
              <span class="text-sm font-medium text-gray-900">
                {{ item.value }}
              </span>
            </template>
          </div>
        </template>
      </div>
    </div>

    <DocumentAccordion
      v-if="document.summary"
      :title="t('target.documents.detail.summary.title')"
      :content="summaryText"
      :status="document.summaryStatus"
      pending-title="target.watchFiles.documents.summary.pending"
      error-title="target.watchFiles.documents.summary.error"
    >
      <template #subtitle>
        <Tag v-if="document.summaryGeneratedAt" size="sm" icon="fa-clock" variant="secondary">
          {{ d(document.summaryGeneratedAt, 'long') }}
        </Tag>
      </template>
    </DocumentAccordion>

    <DocumentAccordion
      v-if="document.aiValidation"
      :title="validationTitle"
      :content="validationReasonText"
      :status="document.aiValidation.status"
      pending-title="target.watchFiles.documents.validation.pending"
      error-title="target.watchFiles.documents.validation.error"
    >
      <template #subtitle>
        <Tag v-if="document.aiValidation.processedAt" size="sm" icon="fa-clock" variant="secondary">
          {{ d(document.aiValidation.processedAt, 'long') }}
        </Tag>
      </template>
    </DocumentAccordion>

    <InformationMessage
      v-if="document?.manualStatus === DocumentValidationAction.ACCEPT"
      :title="acceptedTitle"
      color="success"
      icon="fa-thumbs-up"
      width="full"
      :fill="true"
    />

    <InformationMessage
      v-else-if="document?.manualStatus === DocumentValidationAction.REFUSE"
      :title="refusedTitle"
      color="error"
      icon="fa-thumbs-down"
      width="full"
      :fill="true"
    />
  </div>
</template>

<script setup lang="ts">
import { Icon, Tag } from '@owlint/feathers-vue'
import InformationMessage from '@target/components/global/InformationMessage.vue'
import UrlDomain from '@target/components/global/UrlDomain.vue'
import DocumentDetailSkeleton from '@target/components/skeletons/DocumentDetailSkeleton.vue'
import { useDocumentIcon } from '@target/composables/useDocumentIcon'
import { useLocalized } from '@target/composables/useLocalized'
import type { Document } from '@target/types/document'
import { DocumentValidationAction } from '@target/types/document'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import DocumentAccordion from './DocumentAccordion.vue'

const { d, t } = useI18n()
const { getDocumentIcon } = useDocumentIcon()
const { getLocalizedString } = useLocalized()

interface Props {
  document?: Document
  isLoading?: boolean
}

const { document = undefined, isLoading = false } = defineProps<Props>()

const formatDate = (date: string, format: string = 'long') => {
  return d(date, format)
}

const summaryText = getLocalizedString(computed(() => document?.summary))
const validationReasonText = getLocalizedString(
  computed(() => document?.aiValidation?.validationReason),
)

const validationTitle = computed(() => {
  if (!document?.aiValidation) return ''

  const status = document.aiValidation.status
  if (status === 'pending' || status === 'failed') return ''

  return t(`target.documents.detail.validation.title.${status}`)
})

const acceptedTitle = computed(() => {
  if (!document) return ''

  if (document.validatedBy && document.validatedAt) {
    return t('target.documents.detail.validatedBy', {
      name: document.validatedBy.displayName,
      date: formatDate(document.validatedAt, 'eventDateTime'),
    })
  }

  return t('target.documents.detail.accepted')
})

const refusedTitle = computed(() => {
  if (!document) return ''

  if (document.validatedBy && document.validatedAt) {
    return t('target.documents.detail.rejectedBy', {
      name: document.validatedBy.displayName,
      date: formatDate(document.validatedAt, 'eventDateTime'),
    })
  }

  return t('target.documents.detail.rejected')
})

const metadataItems = computed(() => {
  if (!document) return []

  return [
    {
      key: 'source',
      label: t('target.documents.detail.source'),
      value: document?.source?.name,
      url: document?.source?.url,
      domain: document?.source?.primaryDomain,
    },
    {
      key: 'url',
      label: t('target.documents.detail.url'),
      value: document?.url,
    },
    {
      key: 'publishDate',
      label: t('target.documents.detail.publishDate'),
      value: formatDate(document?.datePublish),
    },
    {
      key: 'creationDate',
      label: t('target.documents.detail.creationDate'),
      value: formatDate(document?.dateCollect),
    },
    {
      key: 'type',
      label: t('target.documents.detail.type'),
      value: t('common.documentType_' + document?.type.toLowerCase()),
      leftIcon: getDocumentIcon(document?.type),
    },
    {
      key: 'language',
      label: t('target.documents.detail.language'),
      value: t('common.language_' + document?.language.toLowerCase()),
    },
  ]
})
</script>
