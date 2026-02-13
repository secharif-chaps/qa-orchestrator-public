<template>
  <Filters
    v-model="displayDrawer"
    filter-type="documents"
    :facets="facets"
    :is-loading="isLoading"
    :error="error"
    css-variable-name="--panel-document-filters-width"
    :available-filters="[
      FILTER_CATEGORY_VALIDATIONS,
      FILTER_CATEGORY_DATES,
      FILTER_CATEGORY_ACTORS,
      FILTER_CATEGORY_SOURCES,
    ]"
  />
</template>

<script lang="ts" setup>
import Filters from '~/components/filters/Filters.vue';
import type { DocumentFacets } from '~/types/document';
import {
  FILTER_CATEGORY_VALIDATIONS,
  FILTER_CATEGORY_DATES,
  FILTER_CATEGORY_ACTORS,
  FILTER_CATEGORY_SOURCES,
} from '~/types/facet';

const displayDrawer = defineModel<boolean>();

interface Props {
  facets?: DocumentFacets;
  isLoading?: boolean;
  error?: Error | null;
}

defineProps<Props>();
</script>
