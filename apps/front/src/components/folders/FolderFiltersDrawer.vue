<template>
  <FilterDrawer
    v-model="open"
    :title="t('common.folder.filters.title')"
    :confirm-label="t('common.filters.confirm')"
    :reset-label="t('common.filters.reset')"
    :filters-count="store.activeFiltersCount"
    @confirm="handleConfirm"
    @reset="handleReset"
    to="#main-content"
  >
    <div class="flex flex-col gap-6">
      <section class="border-primary-light-stroke rounded-sm border">
        <div
          class="gap-2xs p-xs bg-primary-lightest border-primary-light-stroke flex items-center rounded-t-sm border-b text-lg"
        >
          <Icon icon="fa-folder" aria-hidden="true" />

          <h4 class="text-primary-font font-semibold">
            {{ t('common.folder.filters.status.title') }}
          </h4>
        </div>

        <div class="gap-3xs px-xs py-md flex flex-col items-start">
          <Radio
            id="folder-filter-status-all"
            v-model="formFavoritesValue"
            value="all"
            name="folder-filter-status"
            :label="t('common.folder.filters.status.all', { count: totalCount })"
          />
          <Radio
            id="folder-filter-status-favorites"
            v-model="formFavoritesValue"
            value="favorites"
            name="folder-filter-status"
            :label="t('common.folder.filters.status.favorites')"
          />
        </div>
      </section>
    </div>
  </FilterDrawer>
</template>

<script setup lang="ts">
import FilterDrawer from '@/components/ui/filters/FilterDrawer.vue'
import { useFoldersStore } from '@/stores/folders'
import { Icon, Radio } from '@owlint/feathers-vue'
import { ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'

interface Props {
  totalCount?: number
}

defineProps<Props>()

const open = defineModel<boolean>({ default: false })

const { t } = useI18n()
const store = useFoldersStore()

// Local form state — committed to store on confirm.
const formFavoritesValue = ref<'all' | 'favorites'>(store.favoritesOnly ? 'favorites' : 'all')

const syncFromStore = () => {
  formFavoritesValue.value = store.favoritesOnly ? 'favorites' : 'all'
}

watch(open, (value) => {
  if (value) syncFromStore()
})

const handleConfirm = () => {
  store.favoritesOnly = formFavoritesValue.value === 'favorites'
  store.page = 1 // reset pagination when filters change
}

const handleReset = () => {
  store.resetFilters()
  store.page = 1
  syncFromStore()
}
</script>
