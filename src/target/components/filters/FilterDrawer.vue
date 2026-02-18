<template>
  <Drawer
    v-model="displayDrawer"
    :title="title"
    position="left"
    icon="fa-filter"
    to="#watchfile-layout"
  >
    <div class="absolute inset-0 flex flex-col overflow-hidden">
      <div class="scrollable min-h-0 flex-1 p-6 pr-3">
        <FiltersPanelSkeleton v-if="isLoading" />
        <FiltersAccordion
          v-else-if="facets"
          :items="accordionFilters"
          :default-value="openEdit ? [openEdit] : defaultValueOpen"
        >
          <template v-for="(_, name) in $slots" #[name]="slotData">
            <slot :name="name" v-bind="slotData" />
          </template>
        </FiltersAccordion>
        <ErrorMessage
          v-else-if="error"
          :title="$t('common.error.title.filter')"
          vertical-align="center"
        />
      </div>
      <div class="flex w-full shrink-0 items-center gap-1.5 bg-white p-6">
        <Button @click="confirmFilters">
          {{ confirmButtonLabel }}
        </Button>
        <Button v-if="filtersCounts" variant="tertiary" icon="fa-rotate-left" @click="resetFilters">
          {{ resetButtonLabel }}
        </Button>
      </div>
    </div>
  </Drawer>
</template>

<script lang="ts" setup>
import { Button } from '@owlint/feathers-vue'
import ErrorMessage from '~/components/global/ErrorMessage.vue'
import FiltersAccordion from '~/components/filterPanel/FiltersAccordion.vue'
import FiltersPanelSkeleton from '~/components/filterPanel/FiltersPanelSkeleton.vue'
import Drawer from '~/components/global/Drawer.vue'
import type { DocumentFacets } from '~/types/document'
import type { AnalysisFacets } from '~/types/facet'
import type { DocumentFilter } from '~/types/filter'

interface Props {
  facets?: DocumentFacets | AnalysisFacets
  isLoading?: boolean
  error?: Error | null
  accordionFilters: DocumentFilter[]
  openEdit?: string
  defaultValueOpen: string[]
  filtersCounts?: number
  title: string
  confirmButtonLabel: string
  resetButtonLabel: string
}

const {
  facets = undefined,
  isLoading = false,
  error = null,
  openEdit = '',
  filtersCounts = 0,
} = defineProps<Props>()

const displayDrawer = defineModel<boolean>({ default: false })

const emit = defineEmits<{
  confirm: []
  reset: []
}>()

const confirmFilters = () => {
  emit('confirm')
  displayDrawer.value = false
}

const resetFilters = () => {
  emit('reset')
  displayDrawer.value = false
}
</script>
