<template>
  <Modal v-model:display-modal="isOpen" :title="modalTitle" size="lg" @close="handleClose">
    <template #description>
      <p class="mb-4">
        {{ modalDescription }}
      </p>

      <!-- Sources Table -->
      <div>
        <div v-if="actorSources.length > 0 || isTableLoading">
          <Table
            :key="`sources-table-${actor?.actor.id || 'no-actor'}`"
            :items="tableData"
            :fields="tableColumns"
            class="max-h-60"
            layout="fixed"
          >
            <template #head(selectAll)>
              <th class="px-4 py-2">
                <Checkbox
                  id="select-all-checkbox"
                  v-model="selectAll"
                  :value="true"
                  :disabled="isTableLoading || areAllSourcesReadOnly"
                />
              </th>
            </template>
            <template
              v-for="column in tableColumns.filter((col) => col.key !== 'selectAll')"
              :key="column.key"
              #[`head(${column.key})`]="{ field }"
            >
              <th v-if="column.sortable" class="px-4">
                <HeaderCell
                  :field="field"
                  :sort-order="sortOrderTable"
                  :sort-by="sortByTable"
                  @click="changeSort(field.key)"
                />
              </th>
            </template>
            <template #cell(selectAll)="{ item }">
              <td class="px-4 py-3">
                <div v-if="item.isSkeleton" class="h-4 w-4 animate-pulse rounded bg-gray-200"></div>
                <template v-else>
                  <OPopper v-if="isSourceAlreadyInTargetState(item)">
                    <template #tooltip>
                      {{ disabledSourceTooltipText }}
                    </template>
                    <span class="cursor-not-allowed">
                      <Checkbox
                        :id="`source-checkbox-${item.id}`"
                        :model-value="true"
                        :name="`source-checkbox-${item.id}`"
                        disabled
                      />
                    </span>
                  </OPopper>
                  <Checkbox
                    v-else
                    :id="`source-checkbox-${item.id}`"
                    v-model="selectedSourceIds"
                    :value="item.id"
                    :name="`source-checkbox-${item.id}`"
                  />
                </template>
              </td>
            </template>
            <template #cell(name)="{ item }">
              <td class="px-4 py-3">
                <div v-if="item.isSkeleton" class="flex items-center gap-2">
                  <div class="h-4 w-4 animate-pulse rounded-full bg-gray-200"></div>
                  <div class="h-4 w-[120px] animate-pulse rounded bg-gray-200"></div>
                </div>
                <SourceCard v-else :source="item" variant="minimal" />
              </td>
            </template>
            <template #cell(type)="{ item }">
              <td class="min-w-32 px-4 py-3">
                <div
                  v-if="item.isSkeleton"
                  class="h-6 w-20 animate-pulse rounded-full bg-gray-200"
                ></div>
                <div v-else class="whitespace-nowrap">
                  <Tag variant="secondary" size="sm">
                    {{ getSourceTypeLabel(item.type) }}
                  </Tag>
                </div>
              </td>
            </template>
          </Table>
        </div>
        <div v-else class="py-8 text-center text-gray-500">
          {{ $t('target.watchFiles.actors.no_sources_found') }}
        </div>
      </div>
    </template>

    <template #footer>
      <div class="flex justify-end gap-2">
        <Button variant="secondary" @click="handleClose">
          {{ $t('target.watchFiles.actors.deactivation_modal.cancel') }}
        </Button>
        <Button :loading="isLoading" @click="handleConfirm">
          {{ confirmButtonLabel }}
        </Button>
      </div>
    </template>
  </Modal>
</template>

<script setup lang="ts">
import {
  Button,
  Checkbox,
  HeaderCell,
  Modal,
  OPopper,
  Table,
  Tag,
  useSort,
  type SortOrder,
} from '@owlint/feathers-vue'
import { useChangeActorStatus } from '@target/api/mutations/actor'
import { useSourceTypeLabel } from '@target/composables/useSourceTypeLabel'
import { useToast } from '@target/composables/useToast'
import { ActorStatus } from '@target/types/actor'
import type { Source } from '@target/types/source'
import { SourceStatus } from '@target/types/source'
import type { WatchFileActor } from '@target/types/watchFile'
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import SourceCard from '../sources/SourceCard.vue'

interface Props {
  actor?: WatchFileActor | null
  watchFileId?: string
}

const { actor = null, watchFileId = '' } = defineProps<Props>()

const isOpen = defineModel<boolean>('isOpen', {
  required: false,
  default: false,
})

const emit = defineEmits<{
  (e: 'close'): void
  (e: 'actor-updated', newStatus: string): void
}>()

const { t } = useI18n()
const toast = useToast()
const { getSourceTypeLabel } = useSourceTypeLabel()

const { changeStatus } = useChangeActorStatus({
  onSuccess: () => {
    emit(
      'actor-updated',
      actor?.status === ActorStatus.ACTIVE ? ActorStatus.INACTIVE : ActorStatus.ACTIVE,
    )
    isOpen.value = false
  },
})

const isLoading = ref(false)
const fetchedSources = ref<Source[]>([])
const isTableLoading = ref(false)
const selectedSourceIds = ref<Array<string>>([])

const sortOrderTable = ref<SortOrder>('')
const sortByTable = ref('')
const { changeSort } = useSort(sortOrderTable, sortByTable)

// Get sources associated with the actor (fetched via API)
const actorSources = computed(() => {
  if (!actor) return []
  return fetchedSources.value
})

// Sorted sources for the table
const sortedActorSources = computed(() => {
  const sources = [...actorSources.value]

  if (sortByTable.value === 'name') {
    sources.sort((a, b) => {
      const aName = a.name?.toLowerCase() || ''
      const bName = b.name?.toLowerCase() || ''
      return sortOrderTable.value === 'ASC'
        ? aName.localeCompare(bName)
        : bName.localeCompare(aName)
    })
  } else if (sortByTable.value === 'type') {
    sources.sort((a, b) => {
      const aType = getSourceTypeLabel(a.type)?.toLowerCase() || ''
      const bType = getSourceTypeLabel(b.type)?.toLowerCase() || ''
      return sortOrderTable.value === 'ASC'
        ? aType.localeCompare(bType)
        : bType.localeCompare(aType)
    })
  }

  return sources
})

const areAllSourcesReadOnly = computed(() => {
  if (sortedActorSources.value.length === 0) {
    return true
  }
  return sortedActorSources.value.every((source) => isSourceAlreadyInTargetState(source))
})

const isSourceAlreadyInTargetState = (source: Source): boolean => {
  if (!actor || isTableLoading.value) return false

  const isActivating = actor.status !== ActorStatus.ACTIVE

  if (isActivating) {
    return source.status === SourceStatus.ACTIVE
  } else {
    return source.status === SourceStatus.INACTIVE || source.status === SourceStatus.AUTO_DISABLED
  }
}

const disabledSourceTooltipText = computed(() => {
  if (!actor) return ''

  return actor.status !== ActorStatus.ACTIVE
    ? t('target.watchFiles.actors.deactivation_modal.source_already_active')
    : t('target.watchFiles.actors.deactivation_modal.source_already_inactive')
})

const selectAll = ref<boolean | 'indeterminate'>(false)

// // Handle select all checkbox change
const toggleSelectAll = (value: boolean | 'indeterminate') => {
  if (isTableLoading.value) return
  // Update selection for all changeable sources
  const filterActorSources = sortedActorSources.value.filter(
    (source) => !isSourceAlreadyInTargetState(source),
  )
  if (value === true) {
    selectedSourceIds.value = sortedActorSources.value.map((source) => source.id)
  } else if (value === false) {
    for (const source of filterActorSources) {
      const sourceIndex = selectedSourceIds.value.findIndex((id) => source.id === id)
      if (sourceIndex !== -1) {
        selectedSourceIds.value.splice(sourceIndex, 1)
      }
    }
  }
}

watch(selectAll, (newValue) => {
  toggleSelectAll(newValue)
})

// Watch for changes in sources to update selectAll state
watch(
  [sortedActorSources, selectedSourceIds],
  () => {
    if (isTableLoading.value) return

    if (areAllSourcesReadOnly.value) {
      selectAll.value = true
      return
    }

    const changeableSources = sortedActorSources.value.filter(
      (source) => !isSourceAlreadyInTargetState(source),
    )
    if (changeableSources.length) {
      const isAllSourceSelected = changeableSources.every((source) =>
        selectedSourceIds.value.includes(source.id),
      )
      if (isAllSourceSelected) {
        selectAll.value = true
      } else if (!isAllSourceSelected && selectedSourceIds.value.length) {
        selectAll.value = 'indeterminate'
      } else {
        selectAll.value = false
      }
    }
  },
  { deep: true },
)

const fetchActorSources = async () => {
  if (!actor) return

  isTableLoading.value = true

  try {
    const { getActorSources } = await import('@target/api/actor')
    const response = await getActorSources(
      watchFileId,
      String(actor.actor.id),
      1,
      100,
      'name',
      'ASC',
    )
    if (response && response.items && Array.isArray(response.items)) {
      fetchedSources.value = response.items
    }
  } catch (error) {
    console.error('Could not fetch sources for actor:', error)
  } finally {
    isTableLoading.value = false
  }
}

// Skeleton data for loading state
const skeletonData = computed(() => {
  return Array.from({ length: 5 }, (_, index) => ({
    id: `skeleton-${index}`,
    name: '',
    type: '',
    primaryDomain: '',
    isSkeleton: true,
  }))
})

const tableData = computed(() => {
  if (isTableLoading.value) {
    return skeletonData.value
  }
  return sortedActorSources.value
})

const modalTitle = computed(() => {
  if (!actor) return ''

  const isActivating = actor.status !== ActorStatus.ACTIVE
  const actorName = actor.actor.label

  return isActivating
    ? t('target.watchFiles.actors.deactivation_modal.activation_modal.title', {
        actor: actorName,
      })
    : t('target.watchFiles.actors.deactivation_modal.title', { actor: actorName })
})

const modalDescription = computed(() => {
  if (!actor) return ''

  const isActivating = actor.status !== ActorStatus.ACTIVE

  return isActivating
    ? t('target.watchFiles.actors.deactivation_modal.activation_modal.description')
    : t('target.watchFiles.actors.deactivation_modal.description')
})

const confirmButtonLabel = computed(() => {
  if (!actor) return ''

  const isActivating = actor.status !== ActorStatus.ACTIVE
  const changeableSources = sortedActorSources.value.filter(
    (source) => !isSourceAlreadyInTargetState(source),
  )
  const selectedCount = changeableSources.filter((source) =>
    selectedSourceIds.value.includes(source.id),
  ).length

  const translationKey = isActivating
    ? 'target.watchFiles.actors.deactivation_modal.activation_modal.confirm'
    : 'target.watchFiles.actors.deactivation_modal.confirm'

  return t(translationKey, { count: selectedCount })
})

const tableColumns = computed(() => {
  const columns = [
    {
      key: 'selectAll',
      label: '',
      sortable: false,
      class: 'w-12',
    },
    {
      key: 'name',
      label: t('target.watchFiles.actors.deactivation_modal.source_name'),
      sortable: true,
      class: 'w-4/6',
    },
    {
      key: 'type',
      label: t('target.watchFiles.actors.deactivation_modal.type'),
      sortable: true,
      class: 'w-1/4',
    },
  ]

  return columns
})

const currentActorId = ref<string | null>(null)

watch(isOpen, async (isOpen) => {
  if (isOpen && actor) {
    const newActorId = actor.actor.id
    if (currentActorId.value !== newActorId) {
      fetchedSources.value = []
      selectedSourceIds.value = []
      currentActorId.value = newActorId
      // Only reset sort when opening for a different actor
      sortByTable.value = 'name'
      sortOrderTable.value = 'ASC'
    }

    await fetchActorSources()

    const filterActorSources = sortedActorSources.value.filter((source) => {
      if (!isSourceAlreadyInTargetState(source)) {
        const isActivating = actor?.status !== ActorStatus.ACTIVE
        if (!(isActivating && source.status === SourceStatus.INACTIVE)) {
          return true
        }
      }
      return false
    })

    selectedSourceIds.value = filterActorSources.map(({ id }) => id)
  }
})

const handleClose = () => {
  emit('close')
}

const handleConfirm = async () => {
  if (!actor) {
    return
  }

  isLoading.value = true

  try {
    const isActivating = actor.status !== ActorStatus.ACTIVE
    const status = isActivating ? ActorStatus.ACTIVE : ActorStatus.INACTIVE

    const selectedSources = sortedActorSources.value.filter(
      (source) =>
        !isSourceAlreadyInTargetState(source) && selectedSourceIds.value.includes(source.id),
    )
    const sourceIds = selectedSources.map((source) => source.id)

    await changeStatus({
      watchFileId: watchFileId,
      actorId: String(actor.actor.id),
      status: status as ActorStatus,
      sourceIds,
    })
  } catch (error) {
    console.error('Error updating actor status:', error)
    toast.error(t('target.watchFiles.actors.deactivation_modal.error'))
  } finally {
    isLoading.value = false
  }
}
</script>
