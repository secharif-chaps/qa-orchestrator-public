<template>
  <div
    id="watchFileStatus"
    class="shrink-0 text-sm font-medium"
    :aria-label="t('watch_files.header_section.status.label')"
  >
    <Select
      v-if="!isReadOnly"
      v-model="statusValue"
      :options="statusOptions"
      :display-value="statusLabel"
      :disabled="isLoading"
      :icon="statusIcon(statusValue)"
      to="#watchFileStatus"
    >
      <template #items="{ options }">
        <SelectItem
          v-for="option in options"
          :key="option"
          :value="option"
          :disabled="isDisabledStatus(option)"
          @select="onStatusChange(option)"
        >
          <template #icon>
            <Icon :icon="statusIcon(option)" />
          </template>
          <OPopper v-if="isDisabledStatus(option)" placement="top">
            <template #tooltip>
              <div v-sanitize-html="disabledStatusTooltip" class="tooltip-content" />
            </template>
            <span class="cursor-not-allowed">
              {{ statusLabel(option) }}
            </span>
          </OPopper>
          <span v-else>
            {{ statusLabel(option) }}
          </span>
        </SelectItem>
      </template>
    </Select>
    <Tag v-else size="md" :icon="statusIcon(statusValue)">
      {{ statusLabel(statusValue) }}
    </Tag>
  </div>
</template>

<script lang="ts" setup>
import { Icon, OPopper, Select, SelectItem, Tag } from '@owlint/feathers-vue'
import { useQuery } from '@pinia/colada'
import { getCollectionSourceQuery } from '@target/api/queries/sources'
import { useWatchFileStatusModal } from '@target/composables/useWatchFileStatusModal'
import { WATCH_FILE_STATUS, type WatchFile, type WatchFileStatus } from '@target/types/watchFile'
import { computed, watch } from 'vue'
import { useI18n } from 'vue-i18n'

interface Props {
  watchFile: WatchFile
  isReadOnly?: boolean
}

const { watchFile, isReadOnly = false } = defineProps<Props>()

const { t } = useI18n()

const statusOptions = Object.values(WATCH_FILE_STATUS) as readonly WatchFileStatus[]

const { showStatusModal, statusValue, isLoading } = useWatchFileStatusModal(
  {
    onSuccess: (newStatus: WatchFileStatus) => {
      statusValue.value = newStatus
    },
    onRevert: () => {
      statusValue.value = watchFile.status
    },
  },
  watchFile,
)

const { data: sourcesData } = useQuery(getCollectionSourceQuery, () => ({
  watchFileId: watchFile.id,
  active: true,
  itemsPerPage: 1,
  sortBy: 'name',
  sortOrder: 'ASC' as const,
}))

const hasActiveSources = computed(() => !!sourcesData.value?.totalItems)

const isDisabledStatus = (status: WatchFileStatus) => {
  if (status === WATCH_FILE_STATUS.ENABLED) {
    return !hasActiveSources.value || !watchFile.referenceSubject
  }
  return false
}

const disabledStatusTooltip = computed(() => {
  if (!hasActiveSources.value) {
    return t('watch_files.status_change.enabled.disabled_tooltip.sources')
  }
  if (!watchFile.referenceSubject) {
    return t('watch_files.status_change.enabled.disabled_tooltip.reference_subject')
  }
  return ''
})

watch(
  () => watchFile.status,
  (newStatus) => {
    statusValue.value = newStatus
  },
)

function statusLabel(status: WatchFileStatus) {
  switch (status) {
    case WATCH_FILE_STATUS.DRAFT:
      return t('watch_files.header_section.status.draft')
    case WATCH_FILE_STATUS.ENABLED:
      return t('watch_files.header_section.status.enabled')
    case WATCH_FILE_STATUS.ARCHIVED:
      return t('watch_files.header_section.status.archived')
  }
}

function statusIcon(status: WatchFileStatus) {
  switch (status) {
    case WATCH_FILE_STATUS.DRAFT:
      return 'fa-file-lines'
    case WATCH_FILE_STATUS.ENABLED:
      return 'fa-play'
    case WATCH_FILE_STATUS.ARCHIVED:
      return 'fa-box-archive'
  }
}

async function onStatusChange(newStatus: WatchFileStatus) {
  if (newStatus === statusValue.value || isDisabledStatus(newStatus)) {
    return
  }

  showStatusModal(watchFile, newStatus)
}
</script>

<style scoped>
.tooltip-content :deep(ul) {
  list-style-type: disc;
  padding-left: 1.25rem;
  margin-top: 0.25rem;
}

.tooltip-content :deep(li) {
  margin-bottom: 0.25rem;
}
</style>
