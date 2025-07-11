<template>
  <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
    <div>
      <h1 class="text-3xl font-semibold">{{ $t('company.list.title') }}</h1>
      <p class="text-sm text-secondary mt-1">
        {{ $t('company.list.description') }} • {{ companiesCount }} companies
      </p>
    </div>
    
    <div class="flex flex-col sm:flex-row gap-3">
      <!-- Search Input -->
      <OInput
        :model-value="searchQuery"
        placeholder="Search companies..."
        icon="fas fa-search"
        class="w-full sm:w-64"
        @update:model-value="$emit('updateSearch', $event)"
      />
      
      <!-- View Toggle -->
      <OButton 
        :label="viewMode === 'table' ? 'Card View' : 'Table View'"
        :icon="viewMode === 'table' ? 'fas fa-th-large' : 'fas fa-table'"
        type="secondary"
        size="sm"
        @click="$emit('toggleView')"
      />
      
      <!-- Create Button -->
      <OButton 
        :label="$t('company.list.create.title')"
        icon="fas fa-plus"
        type="primary"
        size="sm"
        @click="$emit('createCompany')"
      />
    </div>
  </div>
</template>

<script setup lang="ts">
import { OButton, OInput } from '@owlint/feathers-vue'

interface Props {
  searchQuery: string
  viewMode: 'table' | 'grid'
  companiesCount: number
}

defineProps<Props>()

defineEmits<{
  updateSearch: [query: string]
  toggleView: []
  createCompany: []
}>()
</script>