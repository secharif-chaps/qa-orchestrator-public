<template>
  <div class="flex flex-col gap-4">
    <!-- Header -->
    <div>
      <div class="flex items-center justify-between mb-6">
        <div>
          <h1 class="text-3xl font-bold">
            {{ $t('company.management.title', 'Company Management') }}
          </h1>
          <p class="text-secondary mt-2">
            {{
              $t(
                'company.management.description',
                'View and manage all companies in your organization',
              )
            }}
          </p>
        </div>

        <Button
          variant="tertiary"
          icon="fa fa-plus"
          :label="$t('company.create.button', 'New search')"
          @click="$router.push('/search')"
        />
      </div>

      <!-- Search and Filters -->
      <div class="flex items-center justify-between gap-4 rounded-lg">
        <!-- Search Input -->
        <div class="flex-1 max-w-md">
          <div class="relative">
            <i
              class="fa fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-secondary"
            ></i>
            <input
              v-model="companiesStore.filterName"
              type="text"
              :placeholder="$t('company.search.placeholder', 'Search companies...')"
              class="w-full pl-10 pr-4 py-2 border border-primary-stroke rounded-lg focus:ring-2 focus:ring-primary/20 focus:border-primary bg-base-100"
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
import Button from '@/components/ui/Button.vue'
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
    title: t('company.view.table', 'Table View'),
  },
  {
    value: 'grid',
    label: 'Grid',
    icon: 'fa fa-th-large',
    title: t('company.view.grid', 'Grid View'),
  },
])
</script>
