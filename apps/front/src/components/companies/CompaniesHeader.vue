<template>
  <div class="flex flex-col gap-4">
    <!-- Header -->
    <div>
      <div class="mb-6 flex items-center justify-between">
        <div>
          <h1 class="text-3xl font-bold">
            {{ $t('screen.company.management.title') }}
          </h1>
          <p class="text-secondary mt-2">
            {{ $t('screen.company.management.description') }}
          </p>
        </div>

        <Button
          variant="tertiary"
          icon="fa fa-plus"
          :label="$t('screen.company.create.button')"
          @click="$router.push('/search')"
        />
      </div>

      <!-- Search and Filters -->
      <div class="flex items-center justify-between gap-4 rounded-lg">
        <!-- Search Input -->
        <div class="max-w-md flex-1">
          <div class="relative">
            <i
              class="fa fa-search text-secondary absolute top-1/2 left-3 -translate-y-1/2 transform"
            ></i>
            <input
              v-model="companiesStore.filterName"
              type="text"
              :placeholder="$t('screen.company.search.placeholder')"
              class="border-primary-stroke focus:ring-primary/20 focus:border-primary bg-base-100 w-full rounded-lg border py-2 pr-4 pl-10 focus:ring-2"
            />
          </div>
        </div>

        <!-- View Mode Toggle -->
        <ButtonGroup v-model="viewMode" :options="viewModeOptions" />
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useCompaniesStore } from '@/stores/companies'
import { Button } from '@owlint/feathers-vue'
import ButtonGroup from '@/components/ui/ButtonGroup.vue'
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
