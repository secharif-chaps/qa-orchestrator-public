<template>
  <div class="flex flex-col gap-4">
    <!-- Header -->
    <div>
      <div class="mb-6 flex items-center justify-between">
        <div>
          <h1 class="text-3xl font-bold">
            {{ $t('screen.company.management.title') }}
          </h1>
          <p class="text-neutral-black-font mt-2">
            {{ $t('screen.company.management.description') }}
          </p>
        </div>

        <Button
          variant="tertiary"
          icon="fa-plus"
          :label="$t('screen.company.create.button')"
          @click="$router.push('/search')"
        />
      </div>

      <!-- Search and Filters -->
      <div class="flex items-center justify-between gap-4 rounded-sm">
        <!-- Search Input -->
        <div class="max-w-112 flex-1">
          <Searchbar
            id="companies-search"
            v-model="companiesStore.filterName"
            :placeholder="$t('screen.company.search.placeholder')"
          />
        </div>

        <!-- View Mode Toggle -->
        <ButtonGroup v-model="viewMode" :options="viewModeOptions" />
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import ButtonGroup from '@/components/ui/ButtonGroup.vue'
import { useCompaniesStore } from '@/stores/companies'
import { Button, Searchbar } from '@owlint/feathers-vue'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

const companiesStore = useCompaniesStore()
const { t } = useI18n()

// v-model for viewMode
const viewMode = defineModel<'table' | 'grid'>('viewMode', { required: true })

// View mode options for ButtonGroup
const viewModeOptions = computed(() => [
  {
    value: 'table',
    label: 'Table',
    icon: 'fa fa-list',
    title: t('screen.company.view.table'),
  },
  {
    value: 'grid',
    label: 'Grid',
    icon: 'fa fa-th-large',
    title: t('screen.company.view.grid'),
  },
])
</script>
