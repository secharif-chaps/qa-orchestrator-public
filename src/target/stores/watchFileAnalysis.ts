import { computed, ref, type Ref } from 'vue';
import { defineStore, storeToRefs } from 'pinia';
import { RouteNames } from '~/types/route-names';
import { useWatchFileFiltersStore } from './watchFileFilters';

export const useWatchFileAnalysisStore = defineStore(
  'watchFileAnalysis',
  () => {
    const filtersStore = useWatchFileFiltersStore();
    const type = 'analysis' as const;

    const selectedView = ref<
      RouteNames.WATCH_FILES_RADAR_GRAPH | RouteNames.WATCH_FILES_RADAR_TIMELINE
    >(RouteNames.WATCH_FILES_RADAR_TIMELINE);

    const currentPage = ref(1);
    const itemsPerPage = ref(25);

    const { states } = storeToRefs(filtersStore);

    const formFilters = computed({
      get: () => states.value.analysis.formFilters,
      set: (value) => {
        states.value.analysis.formFilters = value;
      },
    });

    const actors = computed({
      get: () => states.value.analysis.actors,
      set: (value) => {
        states.value.analysis.actors = value;
      },
    });

    const sources = computed({
      get: () => states.value.analysis.sources,
      set: (value) => {
        states.value.analysis.sources = value;
      },
    });

    const datesPicker = computed({
      get: () => states.value.analysis.datesPicker,
      set: (value) => {
        states.value.analysis.datesPicker = value;
      },
    });

    const selectedPeriod = computed({
      get: () => states.value.analysis.selectedPeriod,
      set: (value) => {
        states.value.analysis.selectedPeriod = value;
      },
    });

    const displayFiltersPanel = computed({
      get: () => states.value.analysis.displayFiltersPanel,
      set: (value) => {
        states.value.analysis.displayFiltersPanel = value;
      },
    });

    const isUrlSync = computed({
      get: () => states.value.analysis.isUrlSync,
      set: (value) => {
        states.value.analysis.isUrlSync = value;
      },
    });

    const filterQuery = computed(() => filtersStore.filterQuery(type));
    const filtersCounts = computed(() => filtersStore.filtersCounts(type));
    const datesFilterCount = computed(() =>
      filtersStore.datesFilterCount(type),
    );

    const resetPagination = () => {
      currentPage.value = 1;
    };

    const syncFormFilter = () => {
      filtersStore.syncFormFilter(type);
      resetPagination();
    };

    const removeFilterItem = <T extends { id: string }>(
      id: string,
      filterArray: T[],
      targetRef: Ref<T[]>,
    ) => {
      // Determine if it's actors or sources based on the targetRef
      if (targetRef === actors) {
        filtersStore.removeActor(type, id);
      } else if (targetRef === sources) {
        filtersStore.removeSource(type, id);
      }
      resetPagination();
    };

    const resetFormDatesFilter = () => {
      filtersStore.resetFormDatesFilter(type);
    };

    const resetDatesFilter = () => {
      filtersStore.resetDatesFilter(type);
    };

    const resetFilters = () => {
      filtersStore.resetFilters(type);
    };

    const $reset = () => {
      resetFilters();
      currentPage.value = 1;
      itemsPerPage.value = 25;
      displayFiltersPanel.value = false;
    };

    return {
      currentPage,
      itemsPerPage,

      selectedView,

      formFilters,
      filtersCounts,
      datesFilterCount,

      displayFiltersPanel,
      actors,
      sources,

      datesPicker,
      selectedPeriod,

      filterQuery,

      syncFormFilter,
      isUrlSync,

      removeFilterItem,

      resetFormDatesFilter,
      resetDatesFilter,
      resetFilters,
      resetPagination,
      $reset,
    };
  },
);
