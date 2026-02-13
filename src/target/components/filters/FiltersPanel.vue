<template>
  <div ref="filterPanelRef" class="flex h-full flex-col">
    <div
      class="flex h-min justify-between gap-2"
      :class="displayFiltersPanelValue ? 'px-4 py-3' : 'p-2'"
    >
      <Button
        icon="fa-filter"
        variant="secondary"
        size="sm"
        class="shrink-0"
        @click="displayDrawer = true"
      >
        <template v-if="displayFiltersPanelValue || filtersCounts">
          {{ filterTitle }}
        </template>
      </Button>
      <Button
        v-if="filtersCounts"
        :icon="
          displayFiltersPanelValue ? 'fa-chevron-left' : 'fa-chevron-right'
        "
        variant="tertiary"
        size="sm"
        class="shrink-0"
        @click="toggleDisplayFiltersPanel"
      />
    </div>
    <div class="flex flex-1 flex-col gap-3 p-3">
      <Button
        v-if="displayFiltersPanelValue && filtersCounts"
        class="w-fit"
        variant="tertiary"
        size="sm"
        icon="fa-rotate-left"
        @click="handleResetFilters"
      >
        {{ t('watch_files.filters.button.reset') }}
      </Button>
      <div
        v-if="displayFiltersPanelValue"
        class="flex flex-wrap items-start gap-1"
      >
        <slot name="chips" :open-edit-filter="openEditFilter" />
      </div>
    </div>
  </div>
  <FilterDrawer
    v-model="displayDrawer"
    :facets="facets"
    :is-loading="isLoading"
    :error="error"
    :accordion-filters="accordionFilters"
    :open-edit="openEdit"
    :default-value-open="defaultValueOpen"
    :filters-counts="filtersCounts"
    :title="$t('watch_files.filters.title')"
    :confirm-button-label="t('watch_files.filters.button.confirm')"
    :reset-button-label="t('watch_files.filters.button.reset')"
    @confirm="handleConfirmFilters"
    @reset="handleResetFilters"
  >
    <template v-for="(_, name) in $slots" #[name]="slotData">
      <slot :name="name" v-bind="slotData" />
    </template>
  </FilterDrawer>
</template>

<script lang="ts" setup>
import { Button } from '@owlint/feathers-vue';
import { computed, nextTick, ref, unref, useTemplateRef, watch, type Ref } from 'vue';
import { useI18n } from 'vue-i18n';
import FilterDrawer from '~/components/filters/FilterDrawer.vue';
import { useFilterPanel } from '~/composables/useFilterPanel';
import type { DocumentFacets } from '~/types/document';
import type { AnalysisFacets } from '~/types/facet';
import type { DocumentFilter } from '~/types/filter';

const { t } = useI18n();

interface Props {
  facets?: DocumentFacets | AnalysisFacets;
  isLoading?: boolean;
  error?: Error | null;
  accordionFilters: DocumentFilter[];
  filtersCounts: number;
  displayFiltersPanel: Ref<boolean> | boolean;
  cssVariableName: string;
  onConfirmFilters: () => void | Promise<void>;
  onResetFilters: () => void | Promise<void>;
  onSyncFormFilters?: () => void;
  onUpdateDisplayFiltersPanel?: (value: boolean) => void;
}

const {
  facets = undefined,
  isLoading = false,
  error = null,
  accordionFilters,
  filtersCounts,
  displayFiltersPanel,
  cssVariableName,
  onConfirmFilters,
  onResetFilters,
  onSyncFormFilters = undefined,
  onUpdateDisplayFiltersPanel = undefined,
} = defineProps<Props>();
const displayDrawer = defineModel<boolean>();

const filterPanelRef = useTemplateRef('filterPanelRef');
useFilterPanel(filterPanelRef, cssVariableName);

const openEdit = ref('');

// displayFiltersPanel can be a Ref or a boolean
// Vue unwraps refs in templates, so we need to create a local ref that syncs
const displayFiltersPanelRef = ref(unref(displayFiltersPanel));

// Flag to prevent watch loops
let isUpdatingFromProp = false;

// Sync prop -> local ref
watch(
  () => unref(displayFiltersPanel),
  (newValue) => {
    if (!isUpdatingFromProp) {
      displayFiltersPanelRef.value = newValue;
    }
  },
  { immediate: true },
);

// Sync local ref -> prop via callback
watch(displayFiltersPanelRef, (newValue) => {
  isUpdatingFromProp = true;
  // Use callback if provided
  if (onUpdateDisplayFiltersPanel) {
    onUpdateDisplayFiltersPanel(newValue);
  }
  nextTick(() => {
    isUpdatingFromProp = false;
  });
});

// Use unref to get the value and track changes
const displayFiltersPanelValue = computed(() => {
  return displayFiltersPanelRef.value;
});

const filterTitle = computed(() => {
  if (displayFiltersPanelValue.value) {
    return `${t('watch_files.filters.title_count', { count: filtersCounts })}`;
  }
  return `(${filtersCounts})`;
});

const defaultValueOpen = computed(() => {
  const activeFilters = accordionFilters.filter(
    (filter) => filter.count || (filter.count && filter.empty),
  );
  if (activeFilters.length) {
    return activeFilters.map((filter) => filter.value);
  }
  return accordionFilters.map((filter) => filter.value);
});

const openEditFilter = (filterType: string) => {
  openEdit.value = filterType;
  displayDrawer.value = true;
};

const toggleDisplayFiltersPanel = () => {
  displayFiltersPanelRef.value = !displayFiltersPanelRef.value;
};

const handleConfirmFilters = async () => {
  await onConfirmFilters();
};

const handleResetFilters = async () => {
  await onResetFilters();
};

// Reset openEdit on close and sync formFilters when opening
watch(displayDrawer, (newValue) => {
  if (!newValue) {
    openEdit.value = '';
  } else if (onSyncFormFilters) {
    // When opening the drawer, sync formFilters with current store values
    onSyncFormFilters();
  }
});
</script>
