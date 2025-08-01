<template>
  <div class="bg-bg1 rounded-lg p-4">
    <div class="flex items-center justify-between mb-6">
      <div class="flex items-center gap-3">
        <div
          class="w-12 h-12 rounded-lg bg-primary/10 dark:bg-primary/20 flex items-center justify-center text-2xl"
        >
          <i class="fa-solid fa-box text-primary"></i>
        </div>
        <div>
          <h2 class="text-xl font-semibold">Product Portfolio</h2>
          <p class="text-sm text-secondary">
            {{ totalProductCount }} products across {{ categoryCount }} categories
          </p>
        </div>
      </div>
      <div class="flex gap-2">
        <OButton
          type="tertiary"
          size="sm"
          :label="viewMode === 'grid' ? 'List View' : 'Grid View'"
          @click="$emit('toggleViewMode')"
        >
          <i class="fa-solid" :class="viewMode === 'grid' ? 'fa-list' : 'fa-th-large'"></i>
        </OButton>
        <OInput
          id="search"
          :model-value="searchQuery"
          placeholder="Search products..."
          class="w-64"
          @update:model-value="$emit('updateSearch', $event as string)"
        >
          <i class="fa-solid fa-search"></i>
        </OInput>
      </div>
    </div>

    <!-- Category Filter Pills -->
    <div class="flex flex-wrap gap-2 mb-4">
      <OButton
        type="tertiary"
        size="sm"
        label="All Categories"
        :class="selectedCategory === null ? 'bg-primary text-white' : ''"
        @click="$emit('selectCategory', null)"
      />
      <OButton
        v-for="category in categories"
        :key="category"
        type="tertiary"
        size="sm"
        :label="formatCategoryName(category)"
        :class="selectedCategory === category ? 'bg-primary text-white' : ''"
        @click="$emit('selectCategory', category)"
      />
    </div>
  </div>
</template>

<script setup lang="ts">
import { OButton, OInput } from '@owlint/feathers-vue'

interface Props {
  totalProductCount: number
  categoryCount: number
  categories: string[]
  viewMode: 'grid' | 'list'
  searchQuery: string
  selectedCategory: string | null
}

defineProps<Props>()

defineEmits<{
  toggleViewMode: []
  updateSearch: [query: string]
  selectCategory: [category: string | null]
}>()

// Methods
const formatCategoryName = (category: string) => {
  return category.replace(/([A-Z])/g, ' $1').replace(/^./, (str) => str.toUpperCase())
}
</script>

<style scoped>
.category-pill {
  transition: all 0.2s ease;
}

.category-pill:hover {
  transform: translateY(-1px);
}
</style>
