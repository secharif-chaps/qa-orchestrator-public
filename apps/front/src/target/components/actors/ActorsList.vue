<template>
  <div class="border-sage-100 space-y-4 rounded border p-6 shadow">
    <SectionListHeader
      :title="$t('watch_files.actors.title')"
      :add-button-text="$t('watch_files.actors.select_actor')"
      :sub-title="subTitle"
      :readonly="readonly"
      :loading="loading"
      :error="error"
      :is-new
      @refresh="$emit('retry')"
      @add="openSelectionModal"
    />

    <ActorsGrid
      v-model:current-page="currentPage"
      :actors="actorsList"
      :loading="loading"
      :error="error"
      :watch-file-id="watchFileId"
      :readonly="readonly"
      :page-size="pageSize"
      :total-items="totalItems"
      :show-pagination="true"
      @retry="$emit('retry')"
      @actor-updated="handleActorUpdated"
    />

    <ActorSelectionModal
      v-model:is-open="isSelectionModalOpen"
      :watch-file-id="watchFileId"
      @actor-updated="handleModalActorUpdated"
    />
  </div>
</template>

<script setup lang="ts">
import SectionListHeader from '@target/components/watchFiles/EditSection/SectionListHeader.vue'
import { ActorStatus } from '@target/types/actor'
import type { WatchFileActor } from '@target/types/watchFile'
import { RouteNames } from '@target/types/route-names'
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'
import ActorSelectionModal from './ActorSelectionModal.vue'
import ActorsGrid from './ActorsGrid.vue'

interface Props {
  actors: WatchFileActor[]
  loading?: boolean
  error?: string
  pageSize?: number
  totalItems?: number
  watchFileId?: string
  readonly?: boolean
}

const { t } = useI18n()

const {
  actors,
  loading = false,
  error = '',
  pageSize = 4,
  totalItems = undefined,
  watchFileId = undefined,
  readonly = false,
} = defineProps<Props>()

const route = useRoute(RouteNames.WATCH_FILES)
const emit = defineEmits(['retry', 'add', 'actor-updated'])
const currentPage = defineModel<number>('currentPage', { required: true })
const actorsList = ref<WatchFileActor[]>([...actors])
const isSelectionModalOpen = ref(false)

const isNew = computed(() => !route.params.id)

const actorsStats = computed(() => {
  const activeCount = actorsList.value.filter((a) => a.status === ActorStatus.ACTIVE).length
  const total = totalItems ?? actorsList.value.length
  return { count: activeCount, total }
})

const subTitle = computed(() => {
  if (!actorsList.value.length) {
    return ''
  }
  return t('watch_files.actors.sub_title', actorsStats.value)
})

watch(
  () => actors,
  (newActors) => {
    actorsList.value = [...newActors]
  },
)

function handleActorUpdated(actor: WatchFileActor) {
  const idx = actorsList.value.findIndex(
    (a: WatchFileActor) =>
      (a.actor['@id'] ?? a.actor.id) === (actor.actor['@id'] ?? actor.actor.id),
  )

  if (idx !== -1) {
    const current = actorsList.value[idx]
    if (current) {
      // Toggle the status based on current state
      const newStatus =
        current.status === ActorStatus.ACTIVE ? ActorStatus.INACTIVE : ActorStatus.ACTIVE
      actorsList.value[idx] = {
        ...current,
        status: newStatus,
      }
    }
  }

  emit('actor-updated')
}

function openSelectionModal() {
  isSelectionModalOpen.value = true
}

function handleModalActorUpdated() {
  emit('actor-updated')
}
</script>
