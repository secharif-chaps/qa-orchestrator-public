<template>
  <div v-if="meta" class="grid grid-cols-3 items-center gap-4">
    <!-- Left: Per page selector with results info -->
    <div class="flex flex-col gap-2">
      <div class="flex items-center gap-2">
        <label class="text-neutral-black-font sr-only text-sm whitespace-nowrap">{{
          $t('common.pagination.show')
        }}</label>
        <select
          :value="meta.per_page"
          @change="$emit('updatePerPage', parseInt(($event.target as HTMLSelectElement).value))"
          class="bg-primary-lightest border-primary-lighter-stroke focus:ring-primary/20 focus:border-primary min-w-16 rounded-sm border px-3 py-1.5 text-sm focus:ring-2 focus:outline-none"
        >
          <option v-for="option in pageSizeOptions" :key="option" :value="option">
            {{ option }}
          </option>
        </select>
      </div>

      <div class="text-neutral-black-font text-sm whitespace-nowrap">
        {{ resultText }}
      </div>
    </div>

    <!-- Center: Page navigation -->
    <div class="flex flex-1 justify-center">
      <Pagination.Root
        v-model:page="currentPage"
        :total="meta.total"
        :items-per-page="meta.per_page"
        :sibling-count="1"
        :show-edges="true"
      >
        <Pagination.List v-slot="{ items }" class="flex items-center gap-2">
          <Pagination.First
            class="bg-primary-lightest hover:bg-primary-lighter ring-primary ring-offset-bg1 text-neutral-black-font hover:text-neutral-black-font flex h-10 w-10 items-center justify-center rounded-sm p-0 ring-offset-2 transition-colors hover:ring-2 disabled:cursor-not-allowed disabled:opacity-50 disabled:hover:ring-0 disabled:hover:ring-offset-0"
          >
            <i class="fas fa-chevron-double-left text-sm"></i>
          </Pagination.First>
          <Pagination.Prev
            class="bg-primary-lightest hover:bg-primary-lighter ring-primary ring-offset-bg1 text-neutral-black-font hover:text-neutral-black-font flex h-10 w-10 items-center justify-center rounded-sm p-0 ring-offset-2 transition-colors hover:ring-2 disabled:cursor-not-allowed disabled:opacity-50 disabled:hover:ring-0 disabled:hover:ring-offset-0"
          >
            <i class="fas fa-chevron-left text-sm"></i>
          </Pagination.Prev>

          <template v-for="(item, index) in items" :key="index">
            <Pagination.Ellipsis
              v-if="item.type === 'ellipsis'"
              class="text-neutral-black-font hover:ring-primary hover:ring-offset-bg1 flex h-10 w-10 items-center justify-center hover:ring-2 hover:ring-offset-2"
            >
              <i class="fas fa-ellipsis-h text-sm"></i>
            </Pagination.Ellipsis>

            <Pagination.ListItem
              v-else
              class="hover:bg-primary-lighter hover:ring-primary hover:ring-offset-bg1 flex h-10 w-10 cursor-pointer items-center justify-center rounded-sm p-0 transition-colors hover:ring-2 hover:ring-offset-2"
              :class="{
                'bg-primary/10 border-primary text-sage-content': item.value === meta.current_page,
                'bg-primary-lightest text-neutral-black-font hover:text-sage-content':
                  item.value !== meta.current_page,
              }"
              :value="item.value"
            >
              {{ item.value }}
            </Pagination.ListItem>
          </template>

          <Pagination.Next
            class="bg-primary-lightest hover:bg-primary-lighter ring-primary ring-offset-bg1 text-neutral-black-font hover:text-neutral-black-font flex h-10 w-10 items-center justify-center rounded-sm p-0 ring-offset-2 transition-colors hover:ring-2 disabled:cursor-not-allowed disabled:opacity-50 disabled:hover:ring-0 disabled:hover:ring-offset-0"
          >
            <i class="fas fa-chevron-right text-sm"></i>
          </Pagination.Next>
          <Pagination.Last
            class="bg-primary-lightest hover:bg-primary-lighter ring-primary ring-offset-bg1 text-neutral-black-font hover:text-neutral-black-font flex h-10 w-10 items-center justify-center rounded-sm p-0 ring-offset-2 transition-colors hover:ring-2 disabled:cursor-not-allowed disabled:opacity-50 disabled:hover:ring-0 disabled:hover:ring-offset-0"
          >
            <i class="fas fa-chevron-double-right text-sm"></i>
          </Pagination.Last>
        </Pagination.List>
      </Pagination.Root>
    </div>

    <!-- Right: Direct page input -->
    <div class="flex items-center justify-end gap-2">
      <label class="text-neutral-black-font text-sm whitespace-nowrap">{{
        $t('common.pagination.page')
      }}</label>
      <input
        v-model.number="pageInput"
        @keyup.enter="goToPage"
        @blur="goToPage"
        type="number"
        :min="1"
        :max="meta.last_page"
        class="bg-primary-lightest border-primary-lighter-stroke focus:ring-primary/20 focus:border-primary w-16 rounded-sm border px-2 py-1.5 text-center text-sm focus:ring-2 focus:outline-none"
        :placeholder="String(meta.current_page)"
      />
      <span class="text-neutral-black-font text-sm whitespace-nowrap"
        >{{ $t('common.pagination.of') }} {{ meta.last_page }}</span
      >
    </div>
  </div>
</template>

<script setup lang="ts">
import type { PaginationMeta } from '@/types/pagination'
import { Pagination } from 'reka-ui/namespaced'
import { computed, ref, watch } from 'vue'
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

  return t('common.pagination.displaying', {
    start,
    end,
    total: props.meta.total,
    itemName: props.itemName,
  })
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
