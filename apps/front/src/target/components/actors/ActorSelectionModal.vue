<template>
  <Modal v-model:display-modal="isOpen" size="7xl" @close="closeModal">
    <template #title>
      <div class="flex w-full items-center justify-between">
        <span class="text-lg text-gray-900">
          {{ $t('watch_files.actors.selection_modal.title') }}
        </span>
        <button
          class="flex cursor-pointer items-center p-1 text-gray-500 transition-colors hover:text-gray-700"
          @click="closeModal"
        >
          <Icon icon="fa-xmark" class="h-5 w-5" />
        </button>
      </div>
    </template>

    <template #description>
      <div class="relative -mt-2 h-[620px] overflow-hidden">
        <Transition
          enter-active-class="transition-all duration-300 ease-in-out"
          leave-active-class="transition-all duration-300 ease-in-out"
          enter-from-class="translate-x-full opacity-0"
          enter-to-class="translate-x-0 opacity-100"
          leave-from-class="translate-x-0 opacity-100"
          leave-to-class="-translate-x-full opacity-0"
          mode="out-in"
        >
          <ActorSelectionModalList
            v-if="currentView === 'list'"
            key="list"
            v-model:type-filter="typeFilter"
            :actors="allActors"
            :loading="loading"
            :error="error?.message || ''"
            :watch-file-id="watchFileId"
            :page-size="actorStore.itemsPerPage"
            :total-items="totalItems"
            :search="search"
            :is-actor-selected="isActorSelected"
            @retry="handleRetry"
            @toggle-status="toggleActorSelection"
            @actor-clicked="handleActorClicked"
          />

          <div
            v-else-if="currentView === 'detail' && detailActor"
            key="detail"
            class="flex h-full flex-col"
          >
            <div class="mb-4 flex shrink-0 justify-start px-2">
              <Button
                variant="secondary"
                icon="fa-chevron-left"
                size="lg"
                @click="handleBackToList"
              >
                {{ $t('common.action.back') }}
              </Button>
            </div>
            <div class="min-h-0 flex-1 overflow-hidden overflow-y-auto">
              <ActorDetails
                selectable
                :actor="detailActor"
                :watch-file-id="watchFileId"
                @actor-clicked="handleActorClicked"
              />
            </div>
          </div>
        </Transition>
      </div>
    </template>

    <template #footer>
      <div class="flex flex-row-reverse gap-3">
        <template v-if="currentView === 'list'">
          <Button
            v-if="hasSelections"
            :loading="isConfirming"
            :disabled="isConfirming"
            @click="confirmSelection"
          >
            {{ confirmButtonLabel }}
          </Button>
          <Button variant="tertiary" @click="closeModal">
            {{ $t('common.button.cancel') }}
          </Button>
        </template>

        <template v-else-if="currentView === 'detail'">
          <Button @click="handleSelectActorFromDetail">
            {{ getSelectActorButtonLabel() }}
          </Button>
        </template>
      </div>
    </template>
  </Modal>
</template>

<script setup lang="ts">
import { Button, Icon, Modal } from '@owlint/feathers-vue'
import { useQuery } from '@pinia/colada'
import { useBatchChangeActorStatus } from '@target/api/mutations/actor'
import { getCollectionActorQuery } from '@target/api/queries/actor'
import { useActorSelection } from '@target/composables/useActorSelection'
import { useActorStore } from '@target/stores/actor'
import { ActorStatus } from '@target/types/actor'
import type { WatchFileActor } from '@target/types/watchFile'
import { storeToRefs } from 'pinia'
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import ActorDetails from './ActorDetails.vue'
import ActorSelectionModalList from './ActorSelectionModalList.vue'

const props = defineProps<{
  watchFileId?: string
}>()

const isOpen = defineModel<boolean>('isOpen', {
  required: true,
})

const emit = defineEmits<{
  'actor-updated': []
  'actors-selected': [actors: WatchFileActor[]]
}>()

const { t } = useI18n()
const actorStore = useActorStore()
const { search, page: currentPage } = storeToRefs(actorStore)
const currentView = ref<'list' | 'detail'>('list')
const isConfirming = ref(false)
const typeFilter = ref<string[]>([])

const {
  selectedActorsCount,
  selectedSourcesCount: visibleSelectedSourcesCount,
  hasSelections,
  isActorSelected,
  toggleActorSelection,
  getApiFormatSelections,
  clearSelections,
  getSelectedActors,
  detailActor,
  detailSelectedSourcesCount,
  setDetailActor,
  clearDetailActor,
  selectActorFromDetail,
} = useActorSelection()

const {
  data: actorData,
  error,
  isLoading: loading,
  refetch: refetchActors,
} = useQuery(() =>
  getCollectionActorQuery({
    watchFileId: isOpen.value && props.watchFileId ? props.watchFileId : '',
    status: actorStore.status,
    search: actorStore.search,
    type: typeFilter.value.length > 0 ? typeFilter.value : undefined,
    sortBy: actorStore.sortBy,
    sortOrder: actorStore.sortOrder,
    page: currentPage.value,
    itemsPerPage: actorStore.itemsPerPage,
  }),
)

const allActors = computed(() => {
  if (!actorData.value?.items) {
    return []
  }
  const transformed = actorData.value.items as WatchFileActor[]
  return transformed
})

const totalItems = computed(() => {
  return actorData.value?.totalItems || 0
})

const { batchChangeStatus } = useBatchChangeActorStatus({
  onSuccess: () => {
    emit('actor-updated')
  },
})

const confirmButtonLabel = computed(() => {
  if (isConfirming.value) {
    return t('common.action.loading')
  }

  if (visibleSelectedSourcesCount.value > 0) {
    return t(
      'watch_files.actors.selection_modal.add_actors',
      {
        count: selectedActorsCount.value,
        sources: visibleSelectedSourcesCount.value,
      },
      selectedActorsCount.value,
    )
  }

  return t(
    'watch_files.actors.selection_modal.add_actors_no_sources',
    {
      count: selectedActorsCount.value,
    },
    selectedActorsCount.value,
  )
})

const closeModal = () => {
  isOpen.value = false
  actorStore.resetFilters()
  typeFilter.value = []
  currentView.value = 'list'
  clearDetailActor()
}

const handleActorClicked = (actor: WatchFileActor) => {
  setDetailActor(actor)
  currentView.value = 'detail'
}

const handleBackToList = () => {
  currentView.value = 'list'
  clearDetailActor()
}

const handleSelectActorFromDetail = () => {
  if (detailActor.value) {
    selectActorFromDetail()
    handleBackToList()
  }
}

const handleRetry = () => {
  refetchActors()
}

const getSelectActorButtonLabel = () => {
  return t(
    'watch_files.actors.selection_modal.select_actor',
    {
      count: detailSelectedSourcesCount.value,
    },
    detailSelectedSourcesCount.value,
  )
}

const confirmSelection = async () => {
  if (!props.watchFileId) {
    console.error('No watchFileId provided')
    return
  }

  if (!hasSelections.value) {
    closeModal()
    return
  }

  isConfirming.value = true

  try {
    await batchChangeStatus({
      watchFileId: props.watchFileId!,
      actors: getApiFormatSelections(),
    })

    const selectedActors = getSelectedActors(allActors.value)
    emit('actors-selected', selectedActors)
    emit('actor-updated')
    closeModal()
  } catch (error) {
    console.error('Error batch changing actor status:', error)
    closeModal()
  } finally {
    isConfirming.value = false
  }
}

watch(isOpen, (isOpenValue) => {
  if (isOpenValue && props.watchFileId) {
    actorStore.status = ActorStatus.INACTIVE
  } else {
    clearSelections()
  }
})
</script>
