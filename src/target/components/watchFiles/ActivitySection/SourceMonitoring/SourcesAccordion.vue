<template>
  <div class="space-y-4">
    <Accordion.Root
      type="multiple"
      :default-value="allSectionValues"
      :collapsible="true"
    >
      <template v-for="section in sections" :key="section.value">
        <Accordion.Item
          v-if="section.count"
          v-slot="{ open }"
          class="mb-6 space-y-3 border shadow-md"
          :class="[
            section.value === CollectorStatus.ERROR
              ? 'rounded border-red-600 bg-red-50 p-3'
              : 'border-sage-100 rounded-2xl p-6',
          ]"
          :value="section.value"
        >
          <Accordion.Header class="flex">
            <Accordion.Trigger
              class="flex items-center justify-between gap-4"
              :class="{ 'flex-1': section.value === CollectorStatus.ERROR }"
            >
              <div
                v-if="section.value === CollectorStatus.ERROR"
                class="min-w-0 flex-1"
              >
                <ErrorMessage
                  :title="t('watch_files.activity.sources.error.title')"
                  :description="
                    t('watch_files.activity.sources.error.subtitle')
                  "
                  width="full"
                  :fill="true"
                  :transparent="true"
                />
              </div>
              <div v-else class="flex items-center gap-2">
                <span class="text-sm font-medium text-gray-900">{{
                  section.title
                }}</span>
                <Badge
                  v-if="section.count"
                  variant="secondary"
                  size="sm"
                  :number="String(section.count)"
                />
              </div>
              <Button
                :icon="open ? 'fa-chevron-up' : 'fa-chevron-down'"
                variant="tertiary"
                aria-label="Expand/Collapse"
              />
            </Accordion.Trigger>
          </Accordion.Header>
          <Accordion.Content
            class="data-[state=open]:animate-slideDown data-[state=closed]:animate-slideUp"
          >
            <div class="grid grid-cols-1 gap-3 md:grid-cols-2 lg:grid-cols-3">
              <SourceCard
                v-for="source in section.sources"
                :key="source.id"
                :source="source"
                variant="detail"
                @monitoring-click="handleMonitoringClick"
              />
            </div>
          </Accordion.Content>
        </Accordion.Item>
      </template>
    </Accordion.Root>
  </div>
</template>

<script setup lang="ts">
import { Badge, Button } from '@owlint/feathers-vue';
import { Accordion } from 'reka-ui/namespaced';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import ErrorMessage from '~/components/global/ErrorMessage.vue';
import type {
  Source,
  SourceGroup,
  SourcesGroupedResponse,
} from '~/types/source';
import { CollectorStatus } from '~/types/source';
import SourceCard from '~/components/sources/SourceCard.vue';

interface Props {
  sourcesData?: SourcesGroupedResponse;
  searchQuery?: string;
}

interface Emits {
  (e: 'monitoring-click', source: Source): void;
}

const { sourcesData = undefined, searchQuery = '' } = defineProps<Props>();
const emit = defineEmits<Emits>();
const { t } = useI18n();

const handleMonitoringClick = (source: Source) => {
  emit('monitoring-click', source);
};

// Process sources data and apply search filter
const sections = computed(() => {
  const data = sourcesData || {
    groups: [],
    summary: { total: 0, error: 0, running: 0, stopped: 0 },
  };
  if (!data.groups || !Array.isArray(data.groups)) return [];

  return data.groups
    .map((group: SourceGroup) => {
      // Filter sources within this group based on search query
      let groupSources = group.sources || [];

      if (searchQuery?.trim()) {
        const query = searchQuery.toLowerCase().trim();
        groupSources = groupSources.filter(
          (source: Source) =>
            source.name.toLowerCase().includes(query) ||
            source.primaryDomain.toLowerCase().includes(query),
        );
      }

      return {
        value: group.type || 'unknown',
        title: t(`source_types.${group.type || 'unknown'}`),
        count: groupSources.length,
        sources: groupSources,
      };
    })
    .filter((section) => section.count > 0); // Only show sections with sources
});

// Get all section values for default expanded state
const allSectionValues = computed(() => {
  return sections.value.map((section) => section.value);
});
</script>
