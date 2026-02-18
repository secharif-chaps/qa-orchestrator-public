<template>
  <div :class="fixedHeight ? 'flex h-full flex-col' : ''">
    <div
      v-if="loading"
      class="grid animate-pulse grid-cols-1 gap-4 sm:grid-cols-2 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4"
      :class="fixedHeight ? 'mb-6 flex-1' : ''"
    >
      <div
        v-for="i in inModal ? 8 : 4"
        :key="i"
        class="flex min-h-[120px] flex-col gap-3 rounded-lg border border-gray-200 bg-white p-4"
      >
        <div class="flex w-full items-center">
          <div class="mr-2 h-6 w-6 rounded-full bg-gray-200"></div>
          <div class="h-4 w-1/3 rounded bg-gray-200"></div>
          <div class="ml-auto h-6 w-10 rounded-full bg-gray-200"></div>
        </div>

        <div class="h-3 w-3/4 rounded bg-gray-100"></div>
        <div class="h-3 w-2/3 rounded bg-gray-100"></div>

        <div class="flex gap-1">
          <div class="h-4 w-10 rounded-full bg-gray-200"></div>
          <div class="h-4 w-12 rounded-full bg-gray-200"></div>
        </div>

        <div class="rounded-lg border border-gray-200 bg-gray-50 p-2">
          <div class="mb-2 h-3 w-2/3 rounded bg-gray-200"></div>
          <div class="h-3 w-1/2 rounded bg-gray-200"></div>
        </div>
      </div>
    </div>

    <div
      v-else-if="error"
      class="flex flex-col items-center gap-4 py-8"
      :class="fixedHeight ? 'mb-6 flex-1' : ''"
    >
      <ErrorMessage :title="errorTitle" :retry-button="true" @retry="$emit('retry')" />
    </div>

    <div v-else-if="allActors.length > 0" :class="fixedHeight ? 'flex-1' : ''">
      <div
        class="grid gap-4"
        :class="{
          'mb-6': !fixedHeight,
          'grid-cols-1 lg:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4': !inModal,
          'grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4': inModal,
        }"
      >
        <ActorCard
          v-for="actor in paginatedActors"
          :key="actor.actor['@id'] || actor.actor.id"
          :actor="actor"
          :watch-file-id="watchFileId"
          :readonly="readonly"
          :override-default-action="overrideDefaultAction"
          :is-selected="isActorSelected ? isActorSelected(actor) : false"
          variant="detail"
          @update:is-selected="$emit('toggle-status', actor)"
          @actor-clicked="$emit('actor-clicked', $event)"
          @actor-updated="$emit('actor-updated', $event)"
        />
      </div>
    </div>

    <div
      v-if="showPagination && allActors.length > 0"
      :class="fixedHeight ? 'mt-auto mb-8 border-t border-gray-100' : 'mt-4'"
    >
      <SectionListPaginator
        v-if="totalItems && totalItems > pageSize"
        v-model:current-page="currentPage"
        :page-size="pageSize"
        :total-items="totalItems"
        :result-text="$t('watch_files.actors.pagination.result')"
      />
    </div>

    <div
      v-else-if="!loading && !error"
      class="flex flex-col items-center py-16"
      :class="fixedHeight ? 'mb-6 flex-1' : ''"
    >
      <Icon icon="fa-user-slash" class="mb-3 text-4xl text-gray-400" />
      <span class="text-lg text-gray-500">
        {{ $t('watch_files.actors.selection_modal.no_actors') }}
      </span>
    </div>
  </div>
</template>

<script setup lang="ts">
import { Icon } from '@owlint/feathers-vue'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import ErrorMessage from '~/components/global/ErrorMessage.vue'
import SectionListPaginator from '~/components/watchFiles/EditSection/SectionListPaginator.vue'
import type { WatchFileActor } from '~/types/watchFile'
import ActorCard from './ActorCard.vue'

interface Props {
  actors: WatchFileActor[]
  loading?: boolean
  error?: string
  watchFileId?: string
  readonly?: boolean
  pageSize?: number
  totalItems?: number
  showPagination?: boolean
  inModal?: boolean
  fixedHeight?: boolean
  isActorSelected?: (actor: WatchFileActor) => boolean
  overrideDefaultAction?: boolean
}

const {
  actors,
  loading = false,
  error = '',
  watchFileId = undefined,
  readonly = false,
  pageSize = 8,
  totalItems = undefined,
  showPagination = true,
  inModal = false,
  fixedHeight = false,
  isActorSelected = undefined,
  overrideDefaultAction = false,
} = defineProps<Props>()

defineEmits<{
  retry: []
  'toggle-status': [actor: WatchFileActor]
  'actor-clicked': [actor: WatchFileActor]
  'actor-updated': [actor: WatchFileActor]
}>()

const currentPage = defineModel<number>('currentPage', {
  required: true,
  default: 1,
})

const allActors = computed(() => actors || [])

const paginatedActors = computed(() => {
  return allActors.value
})

const { t } = useI18n()

const errorTitle = computed(() =>
  error.includes('403') ? t('watch_files.actors.forbidden') : t('common.error.title'),
)
</script>
