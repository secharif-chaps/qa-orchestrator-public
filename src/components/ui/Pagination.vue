<template>
  <div v-if="meta" class="grid grid-cols-3 gap-4 items-center">
    <!-- Left: Per page selector with results info -->
    <div class="flex flex-col gap-2">
      <div class="flex items-center gap-2">
        <label class="text-sm text-secondary whitespace-nowrap sr-only">Show:</label>
        <select
          :value="meta.per_page"
          @change="$emit('updatePerPage', parseInt(($event.target as HTMLSelectElement).value))"
          class="px-3 py-1.5 bg-base-200 border border-primary-stroke rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary min-w-16"
        >
          <option v-for="option in pageSizeOptions" :key="option" :value="option">
            {{ option }}
          </option>
        </select>
      </div>

      <div class="text-sm text-secondary whitespace-nowrap">
        {{ resultText }}
      </div>
    </div>

    <!-- Center: Page navigation -->
    <div class="flex-1 flex justify-center">
      <Pagination.Root
        v-model:page="currentPage"
        :total="meta.total"
        :items-per-page="meta.per_page"
        :sibling-count="1"
        :show-edges="true"
      >
        <Pagination.List v-slot="{ items }" class="flex items-center gap-2">
          <Pagination.First
            class="h-10 w-10 p-0 bg-base-200 hover:bg-base-300 disabled:opacity-50 hover:ring-2 disabled:hover:ring-0 disabled:hover:ring-offset-0 ring-primary ring-offset-2 ring-offset-bg1 disabled:cursor-not-allowed rounded-md flex items-center justify-center text-secondary hover:text-secondary transition-colors"
          >
            <i class="fas fa-chevron-double-left text-sm"></i>
          </Pagination.First>
          <Pagination.Prev
            class="h-10 w-10 p-0 bg-base-200 hover:bg-base-300 disabled:opacity-50 hover:ring-2 disabled:hover:ring-0 disabled:hover:ring-offset-0 ring-primary ring-offset-2 ring-offset-bg1 disabled:cursor-not-allowed rounded-md flex items-center justify-center text-secondary hover:text-secondary transition-colors"
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
              class="h-10 w-10 p-0 hover:bg-base-300 rounded-md flex items-center justify-center cursor-pointer transition-colors hover:ring-2 hover:ring-primary hover:ring-offset-2 hover:ring-offset-bg1"
              :class="{
                'bg-primary/10 border-primary text-sage-content': item.value === meta.current_page,
                'bg-base-200 text-secondary hover:text-sage-content':
                  item.value !== meta.current_page,
              }"
              :value="item.value"
            >
              {{ item.value }}
            </Pagination.ListItem>
          </template>

          <Pagination.Next
            class="h-10 w-10 p-0 bg-base-200 hover:bg-base-300 disabled:opacity-50 hover:ring-2 disabled:hover:ring-0 disabled:hover:ring-offset-0 ring-primary ring-offset-2 ring-offset-bg1 disabled:cursor-not-allowed rounded-md flex items-center justify-center text-secondary hover:text-secondary transition-colors"
          >
            <i class="fas fa-chevron-right text-sm"></i>
          </Pagination.Next>
          <Pagination.Last
            class="h-10 w-10 p-0 bg-base-200 hover:bg-base-300 disabled:opacity-50 hover:ring-2 disabled:hover:ring-0 disabled:hover:ring-offset-0 ring-primary ring-offset-2 ring-offset-bg1 disabled:cursor-not-allowed rounded-md flex items-center justify-center text-secondary hover:text-secondary transition-colors"
          >
            <i class="fas fa-chevron-double-right text-sm"></i>
          </Pagination.Last>
        </Pagination.List>
      </Pagination.Root>
    </div>

    <!-- Right: Direct page input -->
    <div class="flex items-center gap-2 justify-end">
      <label class="text-sm text-secondary whitespace-nowrap">{{
        $t('pagination.page', 'Page:')
      }}</label>
      <input
        v-model.number="pageInput"
        @keyup.enter="goToPage"
        @blur="goToPage"
        type="number"
        :min="1"
        :max="meta.last_page"
        class="w-16 px-2 py-1.5 bg-base-200 border border-primary-stroke rounded-md text-sm text-center focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary"
        :placeholder="String(meta.current_page)"
      />
      <span class="text-sm text-secondary whitespace-nowrap"
        >{{ $t('pagination.of', 'of') }} {{ meta.last_page }}</span
      >
    </div>
  </div>
</template>

<script setup lang="ts">
import { Pagination } from 'reka-ui/namespaced'
import type { PaginationMeta } from '@/types/pagination'
import { ref, computed, watch } from 'vue'
import { useI18n } from 'vue-i18n'

interface Props {
  meta: PaginationMeta | null
  pageSizeOptions?: number[]
  itemName?: string // For customizing "Displaying X to Y of Z results"
}

const props = withDefaults(defineProps<Props>(), {
  pageSizeOptions: () => [10, 20, 50, 100],
  itemName: 'results',
})

defineEmits<{
  updatePerPage: [value: number]
}>()

const { t } = useI18n()
const currentPage = defineModel<number>('currentPage', { required: true })
const pageInput = ref<number | null>(null)

// Update pageInput when currentPage changes
watch(
  () => currentPage.value,
  (newPage) => {
    pageInput.value = newPage
  },
  { immediate: true },
)

// Computed property for results text
const resultText = computed(() => {
  if (!props.meta) return ''

  const start = (props.meta.current_page - 1) * props.meta.per_page + 1
  const end = Math.min(props.meta.current_page * props.meta.per_page, props.meta.total)

  return t(
    'pagination.displaying',
    `Displaying ${start} to ${end} of ${props.meta.total} ${props.itemName}`,
    {
      start,
      end,
      total: props.meta.total,
      itemName: props.itemName,
    },
  )
})

// Function to handle direct page navigation
const goToPage = () => {
  if (!props.meta || !pageInput.value) {
    pageInput.value = currentPage.value
    return
  }

  const targetPage = Math.max(1, Math.min(pageInput.value, props.meta.last_page))
  pageInput.value = targetPage
  currentPage.value = targetPage
}
</script>
