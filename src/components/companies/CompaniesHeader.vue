<template>
  <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
    <div>
      <h1 class="text-3xl font-semibold">{{ $t('company.list.title') }}</h1>
      <p class="text-sm text-secondary mt-1">
        {{ $t('company.list.description') }}
      </p>
    </div>

    <div class="flex flex-col sm:flex-row gap-3">
      <Button
        variant="tertiary"
        :icon="viewMode !== 'table' ? 'fa fa-list' : 'fa fa-table'"
        :title="viewMode === 'table' ? 'Card View' : 'Table View'"
        icon-only
        @click="$emit('toggleView')"
      />

      <!-- Search Input -->

      <div class="relative">
        <i class="fas fa-search absolute left-2 top-1/2 -translate-y-1/2 text-secondary"></i>
        <input
          v-model="companiesStore.filterName"
          placeholder="Search companies..."
          class="w-full sm:w-64 bg-bg1 border border-border-2 rounded-md p-2 pl-8 focus:outline-none focus-within:ring-2 focus-within:ring-primary focus-within:ring-offset-2 ring-primary ring-offset-bg3"
        />

        <div
          v-if="companiesStore.debouncedName !== companiesStore.filterName"
          class="absolute right-2 top-1/2 -translate-y-1/2 text-secondary"
        >
          <i class="fas fa-spinner-third fa-spin"></i>
        </div>
      </div>

      <!-- Create Button -->
      <Button 
        v-if="canCreateCompany" 
        variant="primary"
        icon="fa fa-plus"
        :label="$t('company.list.create.title')"
        @click="$router.push('/search')"
      />
    </div>
  </div>
</template>

<script setup lang="ts">
import { useCompaniesStore } from '@/stores/companies'
import { useCompanyPermissions } from '@/composables/useCompanyPermissions'
import Button from '@/components/ui/Button.vue'

interface Props {
  viewMode: 'table' | 'grid'
}

const companiesStore = useCompaniesStore()
const { canCreateCompany } = useCompanyPermissions()

defineProps<Props>()

defineEmits<{
  toggleView: []
  createCompany: []
}>()
</script>
