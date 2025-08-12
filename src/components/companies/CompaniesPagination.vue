<template>
  <div v-if="meta && meta.last_page > 1" class="flex justify-center">
    <Pagination.Root
      v-model:page="currentPage"
      :total="meta.total"
      :items-per-page="meta.per_page"
      :sibling-count="1"
      :show-edges="true"
      class="mx-auto"
    >
      <Pagination.List v-slot="{ items }" class="flex items-center gap-2">
        <Pagination.First
          class="h-10 w-10 p-0 bg-bg1 hover:bg-bg2 disabled:opacity-50 hover:ring-2 disabled:hover:ring-0 disabled:hover:ring-offset-0 ring-primary ring-offset-2 ring-offset-bg1 disabled:cursor-not-allowed rounded-md flex items-center justify-center text-secondary hover:text-primary transition-colors"
        >
          <i class="fas fa-chevron-double-left text-sm"></i>
        </Pagination.First>
        <Pagination.Prev
          class="h-10 w-10 p-0 bg-bg1 hover:bg-bg2 disabled:opacity-50 hover:ring-2 disabled:hover:ring-0 disabled:hover:ring-offset-0 ring-primary ring-offset-2 ring-offset-bg1 disabled:cursor-not-allowed rounded-md flex items-center justify-center text-secondary hover:text-primary transition-colors"
        >
          <i class="fas fa-chevron-left text-sm"></i>
        </Pagination.Prev>

        <template v-for="(item, index) in items" :key="index">
          <Pagination.Ellipsis
            v-if="item.type === 'ellipsis'"
            class="flex h-10 w-10 items-center justify-center text-secondary hover:ring-2 hover:ring-primary hover:ring-offset-2 hover:ring-offset-bg1"
          >
            <i class="fas fa-ellipsis-h text-sm"></i>
          </Pagination.Ellipsis>

          <Pagination.ListItem
            v-else
            class="h-10 w-10 p-0 hover:bg-bg2 rounded-md flex items-center justify-center cursor-pointer transition-colors hover:ring-2 hover:ring-primary hover:ring-offset-2 hover:ring-offset-bg1"
            :class="{
              'bg-primary/10 border-primary text-primary': item.value === meta.current_page,
              'bg-bg1 text-secondary hover:text-primary': item.value !== meta.current_page,
            }"
            :value="item.value"
          >
            {{ item.value }}
          </Pagination.ListItem>
        </template>

        <Pagination.Next
          class="h-10 w-10 p-0 bg-bg1 hover:bg-bg2 disabled:opacity-50 hover:ring-2 disabled:hover:ring-0 disabled:hover:ring-offset-0 ring-primary ring-offset-2 ring-offset-bg1 disabled:cursor-not-allowed rounded-md flex items-center justify-center text-secondary hover:text-primary transition-colors"
        >
          <i class="fas fa-chevron-right text-sm"></i>
        </Pagination.Next>
        <Pagination.Last
          class="h-10 w-10 p-0 bg-bg1 hover:bg-bg2 disabled:opacity-50 hover:ring-2 disabled:hover:ring-0 disabled:hover:ring-offset-0 ring-primary ring-offset-2 ring-offset-bg1 disabled:cursor-not-allowed rounded-md flex items-center justify-center text-secondary hover:text-primary transition-colors"
        >
          <i class="fas fa-chevron-double-right text-sm"></i>
        </Pagination.Last>
      </Pagination.List>
    </Pagination.Root>
  </div>
</template>

<script setup lang="ts">
import { Pagination } from 'reka-ui/namespaced'
import type { PaginationMeta } from '@/types/pagination'

defineProps<{
  meta: PaginationMeta | null
}>()

const currentPage = defineModel<number>('currentPage', { required: true })
</script>
