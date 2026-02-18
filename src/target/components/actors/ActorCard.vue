<template>
  <ItemCard
    :variant="variant"
    :domain="actor.actor.primaryDomain"
    :name="actor.actor.label"
    :description="actor.explanations?.[shortLocale] || actor.explanations?.en"
    :date="actor.actor.createdAt"
  >
    <template v-if="variant === 'detail'" #status>
      <div v-if="!readonly" class="ml-auto flex items-center">
        <Switch
          :id="`actor-status-${actor.actor.id}`"
          v-model="buttonStatus"
          @click.stop
        />
      </div>
    </template>

    <template v-if="variant !== 'minimal'" #footer>
      <div>
        <UrlDomain
          :show-logo="false"
          size="sm"
          :domain="actor.actor.primaryDomain"
          :alt="actor.actor.label"
          :label="t('watch_files.actors.link_label')"
          class="text-base-alt"
        />
      </div>
      <div class="gap-xs flex">
        <Tag v-if="actor.sourcesCount !== undefined" size="sm">
          {{
            $t('watch_files.actors.sources_count', {
              count: actor.sourcesCount,
            })
          }}
        </Tag>
        <Tag v-if="actor.type" intent="neutral" size="sm">
          {{ actorTypeLabel }}
        </Tag>
      </div>
    </template>

    <template v-if="variant !== 'minimal'" #action>
      <Button
        variant="tertiary"
        size="sm"
        icon="fa-memo"
        @click="handleCardClick"
      >
        {{ $t('watch_files.actors.see_more') }}
      </Button>
      <ActorDetailsModal
        v-if="!overrideDefaultAction"
        v-model:is-open="isActorDetailsModalOpen"
        :actor="actor"
        :watch-file-id="watchFileId"
      />
      <ActorStatusModal
        v-if="!overrideDefaultAction && watchFileId"
        v-model:is-open="isStatusModalOpen"
        :actor="actor"
        :watch-file-id="watchFileId"
        @close="closeStatusModal"
        @actor-updated="handleActorUpdated"
      />
    </template>
  </ItemCard>
</template>

<script setup lang="ts">
import { Button, Switch, Tag } from '@owlint/feathers-vue';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import ActorDetailsModal from '~/components/actors/ActorDetailsModal.vue';
import ItemCard from '~/components/global/ItemCard.vue';
import UrlDomain from '~/components/global/UrlDomain.vue';
import ActorStatusModal from '~/components/watchFiles/ActorStatusModal.vue';
import { ActorStatus } from '~/types/actor';
import type { Localized } from '~/types/localized';
import type { WatchFileActor } from '~/types/watchFile';

const { locale, t } = useI18n();
const shortLocale = computed(() => locale.value.split('-')[0] as keyof Localized);

interface Props {
  actor: WatchFileActor;
  variant?: 'compact' | 'detail' | 'list' | 'minimal';
  watchFileId?: string;
  overrideDefaultAction?: boolean;
  readonly?: boolean;
}

interface Emits {
  'actor-clicked': [actor: WatchFileActor];
  'actor-updated': [actor: WatchFileActor, newStatus: ActorStatus];
}

const {
  actor,
  variant = undefined,
  watchFileId = undefined,
  overrideDefaultAction = false,
  readonly = true,
} = defineProps<Props>();

const isSelected = defineModel<boolean>('isSelected', { default: false });

const emit = defineEmits<Emits>();

const isActorDetailsModalOpen = ref(false);
const isStatusModalOpen = ref(false);

const buttonStatus = computed({
  get: () => {
    if (overrideDefaultAction) {
      return isSelected.value;
    }
    return actor.status === ActorStatus.ACTIVE;
  },
  set: (value: boolean) => {
    if (overrideDefaultAction) {
      isSelected.value = value;
    } else {
      isStatusModalOpen.value = true;
    }
  },
});

const actorTypeLabel = computed(() => {
  return $t('watch_files.actors.type.' + actor.type);
});

const handleCardClick = () => {
  emit('actor-clicked', actor);
  if (!overrideDefaultAction) {
    isActorDetailsModalOpen.value = true;
  }
};

const handleActorUpdated = (newStatus: string) => {
  isStatusModalOpen.value = false;
  emit('actor-updated', actor, newStatus as ActorStatus);
};

const closeStatusModal = () => {
  isStatusModalOpen.value = false;
};
</script>
