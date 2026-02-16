import { defineStore } from 'pinia';
import { ref, computed } from 'vue';

import type { SortOrder } from '@owlint/feathers-vue';
import { ActorStatus } from '~/types/actor';

export const useActorStore = defineStore('actor', () => {
  const status = ref<ActorStatus>(ActorStatus.INACTIVE); // Default to inactive
  const search = ref<string>('');
  const sortBy = ref<string>('actor.label');
  const sortOrder = ref<SortOrder>('ASC');
  const page = ref<number>(1);
  const itemsPerPage = ref<number>(8);

  const isLoading = ref<boolean>(false);
  const errors = ref<Record<string, string[]>>({});

  const filters = computed(() => ({
    status: status.value,
    search: search.value || undefined,
    sortBy: sortBy.value,
    sortOrder: sortOrder.value,
    page: page.value,
    itemsPerPage: itemsPerPage.value,
  }));

  const getError = (field: string) => errors.value[field]?.join(', ');

  const setLoading = (state: boolean) => {
    isLoading.value = state;
  };

  const resetFilters = () => {
    status.value = ActorStatus.INACTIVE;
    search.value = '';
    sortBy.value = 'actor.label';
    sortOrder.value = 'ASC';
    page.value = 1;
    itemsPerPage.value = 8;
  };

  const $reset = () => {
    resetFilters();
    isLoading.value = false;
    errors.value = {};
  };

  return {
    status,
    search,
    sortBy,
    sortOrder,
    page,
    itemsPerPage,
    isLoading,
    errors,
    filters,
    getError,
    setLoading,
    resetFilters,
    $reset,
  };
});
