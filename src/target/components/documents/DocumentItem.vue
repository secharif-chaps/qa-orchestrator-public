<template>
  <div
    class="border-sage-100 cursor-pointer border-b px-3 py-2 last:border-b-0"
    :class="[
      {
        'hover:bg-sage-100': !isDocumentSelected && !isClickedDocument,
      },
      isDocumentSelected || isClickedDocument ? 'bg-sage-200' : 'even:bg-sage-50 odd:bg-white',
    ]"
    @mouseenter="isHovered = true"
    @mouseleave="isHovered = false"
  >
    <div class="flex items-center gap-4">
      <div class="flex items-center gap-3">
        <Checkbox
          :id="`document-${document.id}`"
          v-model="selectedDocuments"
          :value="document.id"
          :name="`documents-selection-${document.id}`"
          @click.stop
        />
        <Logo
          :domain="document.source?.primaryDomain ?? ''"
          :alt="document.source?.name"
          class="h-6 w-6 rounded-full"
        />
      </div>

      <div class="min-w-0 flex-1 space-y-1">
        <div class="flex items-center gap-1">
          <Bullet v-if="!document.isSeen" color="pink" />
          <h3
            v-sanitize-html="document.title"
            class="text-sm leading-tight font-medium text-gray-900"
            :class="{
              'text-almond-900': isActive,
            }"
          />
        </div>
        <div class="flex items-center gap-2 text-xs">
          <Tag size="sm">
            {{ document.source?.name }}
          </Tag>
          <span class="text-gray-600">•</span>
          <span
            class="text-gray-600"
            :class="{
              'text-almond-900': isActive,
            }"
          >
            {{ dateLabel }}
          </span>
          <Tag v-if="isExcerpt" intent="accent" size="sm">
            {{
              t('watch_files.documents.search.matching', {
                search: searchQuery,
              })
            }}
          </Tag>
        </div>
      </div>
      <Tag
        v-if="isAiValidatedOrRejected"
        size="sm"
        :intent="document.aiValidation?.status === 'validated' ? 'success' : 'danger'"
      >
        {{ t(`watch_files.documents.aiValidationStatus.${document.aiValidation?.status}`) }}
      </Tag>
      <DocumentValidationButtons
        v-if="isUserEditable"
        :document="document"
        :is-batch-processing="isBatchProcessing"
        icon-only
      />
      <Tag v-else-if="document.manualStatus" size="sm">
        {{ t(`watch_files.documents.manualStatus.${document.manualStatus}`) }}
      </Tag>
    </div>
  </div>
</template>

<script lang="ts" setup>
import { Bullet, Checkbox, Tag } from '@owlint/feathers-vue'
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { storeToRefs } from 'pinia'
import type { Document } from '~/types/document'
import { useWatchFileStore } from '~/stores/watchFile'
import { useWatchFileDocumentsStore } from '~/stores/watchFileDocuments'
import Logo from '../global/Logo.vue'
import DocumentValidationButtons from './DocumentValidationButtons.vue'

const { t, d } = useI18n()

interface Props {
  document: Document
  selectedDocumentId?: string
  isBatchProcessing?: boolean
}

const watchFileStore = useWatchFileStore()
const { isUserEditable } = storeToRefs(watchFileStore)
const { selectedDocumentId = undefined, document, isBatchProcessing = false } = defineProps<Props>()

const isHovered = ref(false)

const selectedDocuments = defineModel<string[]>('selectedDocuments', {
  required: true,
})

const watchFileDocumentsStore = useWatchFileDocumentsStore()
const { searchQuery, sortBy } = storeToRefs(watchFileDocumentsStore)

const formatDate = (date: string, format: string = 'short') => {
  return d(date, format)
}

const isClickedDocument = computed(() => selectedDocumentId === document.id)
const isDocumentSelected = computed(() => selectedDocuments.value.includes(document.id))
const isActive = computed(
  () => isDocumentSelected.value || isClickedDocument.value || isHovered.value,
)

const isExcerpt = computed(() => {
  if (!searchQuery.value) return false

  return document.excerptHighlighted || document.contentHighlighted
})

const isAiValidatedOrRejected = computed(() => {
  return ['validated', 'rejected'].includes(document.aiValidation?.status ?? '')
})

const dateLabel = computed(() => {
  if (sortBy.value === 'datePublish') {
    return t('common.publishedOn', { date: formatDate(document.datePublish) })
  } else {
    return t('common.collectedOn', { date: formatDate(document.dateCollect) })
  }
})
</script>
