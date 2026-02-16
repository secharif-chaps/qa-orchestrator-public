<template>
  <div class="p-xs border-sage-200 space-y-md rounded-lg border">
    <div class="gap-2xs flex items-center">
      <div class="gap-3xs flex flex-col">
        <h4 class="text-base font-bold text-black">{{ eventTitle }}</h4>
        <span class="text-sm text-gray-800">{{ eventDateLabel }}</span>
      </div>
    </div>
    <p>
      {{ eventDescription }}
    </p>
    <div class="gap-xs flex items-center">
      <Tag intent="accent" size="sm" icon="fa-bullhorn">
        {{ eventTypeLabel }}
      </Tag>
    </div>
    <Table :items="watchFileEvent.actors" :fields="columns">
      <template #cell(name)="{ item }">
        <td class="px-4 py-3">
          <ActorCard
            :actor="convertEventActorToWatchFileActor(item)"
            variant="minimal"
          />
        </td>
      </template>
      <template #cell(action)="{ item }">
        <td class="border-sage-200 border-l px-4 py-3">
          <Button
            variant="tertiary"
            size="sm"
            icon="fa-file"
            @click="handleViewDocuments(item)"
          >
            {{ t('watch_files.analysis.timeline.event.actor.action') }}
          </Button>
        </td>
      </template>
    </Table>
  </div>
</template>

<script lang="ts" setup>
import { Button, Table, Tag } from '@owlint/feathers-vue';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRoute, useRouter } from 'vue-router';
import ActorCard from '~/components/actors/ActorCard.vue';
import { useLocalized } from '~/composables/useLocalized';
import { useWatchFileDocumentsStore } from '~/stores/watchFileDocuments';
import { useWatchFileFiltersStore } from '~/stores/watchFileFilters';
import { ActorStatus, type Actor } from '~/types/actor';
import { RouteNames } from '~/types/route-names';
import type { WatchFileActor } from '~/types/watchFile';
import type { EventActor, WatchFileEvent } from '~/types/watchFileEvent';

const { d, t } = useI18n();
const route = useRoute();
const router = useRouter();

interface Props {
  watchFileEvent: WatchFileEvent;
}

const { getLocalizedString } = useLocalized();
const { watchFileEvent } = defineProps<Props>();

const watchFileDocumentsStore = useWatchFileDocumentsStore();
const watchFileFiltersStore = useWatchFileFiltersStore();
const watchFileId = computed(() => route.params.id as string);

const eventTitle = computed(() => getLocalizedString(watchFileEvent.title));

/**
 * Converts an EventActor to a WatchFileActor
 * EventActor only has id, name, and role, so we create a minimal WatchFileActor
 * with default values for missing fields
 */
const convertEventActorToWatchFileActor = (
  eventActor: EventActor,
): WatchFileActor => {
  const now = new Date().toISOString();

  // Create a minimal Actor object
  const actor: Actor = {
    id: eventActor.id,
    label: eventActor.name,
    primaryDomain: null,
    createdAt: now,
    updatedAt: now,
  };

  // Create WatchFileActor with default values
  const watchFileActor: WatchFileActor = {
    '@id': eventActor['@id'] || '',
    '@type': 'WatchFileActor',
    id: eventActor.id,
    actor,
    type: eventActor.role || '',
    status: ActorStatus.ACTIVE,
    createdAt: now,
    // no sourcesCount for eventActors
  };

  return watchFileActor;
};

const eventDescription = computed(() => {
  return getLocalizedString(watchFileEvent.description);
});

const eventTypeLabel = computed(() => {
  return t(`watch_files.analysis.event.type.${watchFileEvent.eventType}`);
});

const columns = computed(() => [
  {
    key: 'name',
    label: t('watch_files.analysis.timeline.event.actor.name'),
    class: 'w-2/3',
  },
  {
    key: 'action',
    label: '',
    class: 'w-1/3',
  },
]);

const eventDateLabel = computed<string | null>(() => {
  try {
    const endDate = new Date(watchFileEvent.endDate);
    const startDate = new Date(watchFileEvent.startDate);

    if (startDate.getTime() !== endDate.getTime()) {
      return t('watch_files.analysis.event.date', {
        startDate: d(startDate, 'eventDateTime'),
        endDate: d(endDate, 'eventDateTime'),
      });
    }
    return d(startDate, 'eventDateTime');
  } catch {
    return t('watch_files.analysis.event.unknown_date');
  }
});

const convertEventActorToActor = (eventActor: EventActor) => {
  return {
    id: eventActor.id,
    label: eventActor.name,
    primaryDomain: '',
  };
};

const handleViewDocuments = async (actor: EventActor) => {
  const actorFilter = convertEventActorToActor(actor);

  watchFileDocumentsStore.resetFilters();

  watchFileDocumentsStore.formFilters.actors = [actorFilter];
  watchFileDocumentsStore.actors = [actorFilter];

  watchFileDocumentsStore.syncFormFilter();

  const filterQuery = watchFileFiltersStore.filterQuery('documents');
  await router.push({
    name: RouteNames.WATCH_FILES_DOCUMENTS,
    params: { id: watchFileId.value },
    query: filterQuery,
  });
};
</script>
