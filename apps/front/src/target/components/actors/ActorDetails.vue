<template>
  <div class="flex flex-col gap-6">
    <ActorCard
      :actor="actor"
      :watch-file-id="watchFileId"
      variant="compact"
      override-default-action
      :readonly="false"
      @actor-clicked="handleActorClicked"
    />
    <SourcesListTable
      v-if="watchFileId && actor.actor.id && selectable"
      v-model:selected-sources="detailSelectedSources"
      :watch-file-id="watchFileId"
      :actor-id="String(actor.actor.id)"
      selectable
    />
    <SourcesListTable
      v-else-if="watchFileId && actor.actor.id"
      :watch-file-id="watchFileId"
      :actor-id="String(actor.actor.id)"
    />
  </div>
</template>

<script setup lang="ts">
import SourcesListTable from '@target/components/watchFiles/SourcesListTable.vue'
import { useActorSelection } from '@target/composables/useActorSelection'
import type { WatchFileActor } from '@target/types/watchFile'
import ActorCard from './ActorCard.vue'

interface Props {
  actor: WatchFileActor
  watchFileId?: string
  selectable?: boolean
}

const { actor, watchFileId = undefined, selectable = false } = defineProps<Props>()

const { detailSelectedSources } = useActorSelection()

const emit = defineEmits<{
  'actor-clicked': [actor: WatchFileActor]
}>()

const handleActorClicked = (clickedActor: WatchFileActor) => {
  emit('actor-clicked', clickedActor)
}
</script>
