<template>
  <Pagination
    v-model:current-page="currentPage"
    :items-per-pages="pageSize"
    readonly
    type="simplified"
    :total="totalItems"
  >
    <template #result>
      {{ resultText }}
    </template>
  </Pagination>
</template>

<script setup lang="ts">
import { Pagination } from '@owlint/feathers-vue';

interface Props {
  totalItems: number;
  pageSize: number;
  resultText: string;
}

defineProps<Props>();

const currentPage = defineModel<number>('currentPage', {
  required: true,
});
</script>
