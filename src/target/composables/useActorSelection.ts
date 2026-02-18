import type { ActorSelection } from '@target/types/actor'
import type { WatchFileActor } from '@target/types/watchFile'
import { computed, readonly, ref } from 'vue'

const selectedActorIds = ref<Set<string>>(new Set())
const actorSelections = ref<ActorSelection[]>([])
const detailActor = ref<WatchFileActor | null>(null)
const detailSelectedSources = ref<string[]>([])

/**
 * Sources are no longer loaded with actors to optimize API performance.
 * Source IDs should be fetched separately when needed (e.g., via getActorSources API).
 * This function returns an empty array as initial selection.
 */
const extractSourceIds = (_actor: WatchFileActor): string[] => {
  return []
}

export const useActorSelection = () => {
  const selectedActorsCount = computed(() => {
    return actorSelections.value.length
  })

  const selectedSourcesCount = computed(() => {
    let totalSources = 0
    for (const selection of actorSelections.value) {
      totalSources += selection.sourceIds.length
    }
    return totalSources
  })

  const isActorSelected = (actor: WatchFileActor): boolean => {
    const actorId = String(actor.actor.id || actor.actor['@id'])
    return selectedActorIds.value.has(actorId)
  }

  const addActorSelection = (actorId: string, sourceIds: string[]) => {
    actorSelections.value = actorSelections.value.filter(
      (selection) => selection.actorId !== actorId,
    )

    actorSelections.value.push({
      actorId,
      sourceIds,
    })

    selectedActorIds.value.add(actorId)
  }

  const removeActorSelection = (actorId: string) => {
    selectedActorIds.value.delete(actorId)
    actorSelections.value = actorSelections.value.filter(
      (selection) => selection.actorId !== actorId,
    )
  }

  const toggleActorSelection = (actor: WatchFileActor) => {
    const actorId = String(actor.actor.id || actor.actor['@id'])

    if (selectedActorIds.value.has(actorId)) {
      removeActorSelection(actorId)
    } else {
      const sourceIds = extractSourceIds(actor)
      addActorSelection(actorId, sourceIds)
    }

    selectedActorIds.value = new Set(selectedActorIds.value)
  }

  const updateActorSelection = (selection: { actorId: string; sourceIds: string[] }) => {
    addActorSelection(selection.actorId, selection.sourceIds)
  }

  const getApiFormatSelections = () => {
    return actorSelections.value.map((selection) => ({
      id: selection.actorId,
      sourceIds: selection.sourceIds,
    }))
  }

  const clearSelections = () => {
    selectedActorIds.value.clear()
    actorSelections.value = []
  }

  const getSelectedActors = (allActors: WatchFileActor[]): WatchFileActor[] => {
    return allActors.filter((actor) => {
      const actorId = String(actor.actor.id || actor.actor['@id'])
      return actorSelections.value.some((selection) => selection.actorId === actorId)
    })
  }

  const hasSelections = computed(() => {
    return actorSelections.value.length > 0
  })

  const detailSelectedSourcesCount = computed(() => {
    return detailSelectedSources.value.length
  })

  const setDetailActor = (actor: WatchFileActor | null) => {
    detailActor.value = actor
    if (actor) {
      // Initialize with all actor sources by default
      detailSelectedSources.value = extractSourceIds(actor)
    } else {
      detailSelectedSources.value = []
    }
  }

  const clearDetailActor = () => {
    detailActor.value = null
    detailSelectedSources.value = []
  }

  const selectActorFromDetail = () => {
    if (detailActor.value) {
      const actorId = String(detailActor.value.actor.id || detailActor.value.actor['@id'])
      addActorSelection(actorId, detailSelectedSources.value)
      selectedActorIds.value = new Set(selectedActorIds.value)
    }
  }

  return {
    selectedActorIds: readonly(selectedActorIds),
    actorSelections: readonly(actorSelections),
    detailActor,
    detailSelectedSources,
    detailSelectedSourcesCount,
    selectedActorsCount,
    selectedSourcesCount,
    hasSelections,
    isActorSelected,
    addActorSelection,
    removeActorSelection,
    toggleActorSelection,
    updateActorSelection,
    getApiFormatSelections,
    clearSelections,
    getSelectedActors,
    setDetailActor,
    clearDetailActor,
    selectActorFromDetail,
  }
}
