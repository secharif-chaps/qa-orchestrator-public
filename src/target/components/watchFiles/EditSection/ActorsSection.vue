<template>
  <section>
    <ActorsList
      v-model:current-page="currentPage"
      :actors
      :loading="loading"
      :error="error?.message || ''"
      :watch-file-id="watchFile?.id"
      :readonly="readonly"
      :total-items="totalItems"
      @retry="refetch"
      @actor-updated="handleActorUpdated"
    />
  </section>
</template>

<script setup lang="ts">
import type { SortOrder } from '@owlint/feathers-vue'
import { useQuery } from '@pinia/colada'
import { computed, ref } from 'vue'
import { getCollectionActorQuery } from '~/api/queries/actor'
import ActorsList from '~/components/actors/ActorsList.vue'
import { ActorStatus } from '~/types/actor'
import type { WatchFile } from '~/types/watchFile'

const props = defineProps<{
  watchFile?: WatchFile | null
  readonly?: boolean
}>()

const currentPage = ref(1)
const itemsPerPage = 4

const {
  data: actorData,
  isLoading: loading,
  error,
  refetch,
} = useQuery(getCollectionActorQuery, () => ({
  watchFileId: props.watchFile?.id || '',
  status: ActorStatus.ACTIVE,
  sortBy: 'actor.label',
  sortOrder: 'ASC' as SortOrder,
  page: currentPage.value,
  itemsPerPage: itemsPerPage,
}))

const actors = computed(() => {
  return actorData.value?.items || []
})

const totalItems = computed(() => {
  return actorData.value?.totalItems || 0
})

const handleActorUpdated = () => {
  currentPage.value = 1
  refetch()
}
</script>
