import type { SortOrder } from '@owlint/feathers-vue';
import { defineStore } from 'pinia';
import { computed, ref } from 'vue';
import type { Source } from '~/types/source';

export const useSourcesStore = defineStore('sources', () => {
  const sources = ref<Source[]>([]);

  const page = ref(1);
  const itemsPerPage = ref(10);

  const sortBy = ref<string>('');
  const sortOrder = ref<SortOrder>('ASC');

  const filters = computed(() => ({
    page: page.value,
    itemsPerPage: itemsPerPage.value,
    sortBy: sortBy.value,
    sortOrder: sortOrder.value,
  }));

  function reset() {
    sources.value = [];
    page.value = 1;
    itemsPerPage.value = 10;
    sortBy.value = 'name';
    sortOrder.value = 'ASC';
  }

  function resetPagination() {
    page.value = 1;
  }

  return {
    // state
    sources,
    page,
    itemsPerPage,
    sortBy,
    sortOrder,

    filters,

    // methods
    reset,
    resetPagination,
  };
});
