<template>
  <Card>
    <div class="flex items-center justify-between mb-6">
      <div class="flex items-center gap-3">
        <OIcon icon="fa-box" type="secondary" />
        <div>
          <h2 class="text-xl font-semibold">Product Portfolio</h2>
          <p class="text-sm text-secondary">{{ totalProductCount }} products across {{ categoryCount }} categories</p>
        </div>
      </div>
      <div class="flex gap-2">
        <OButton
          type="secondary"
          size="sm"
          :label="viewMode === 'grid' ? 'List View' : 'Grid View'"
          :icon="viewMode === 'grid' ? 'fa-list' : 'fa-th-large'"
          @click="$emit('toggleViewMode')"
        />
        <OInput
          :model-value="searchQuery"
          placeholder="Search products..."
          icon="fa-search"
          size="sm"
          class="w-64"
          @update:model-value="$emit('updateSearch', $event)"
        />
      </div>
    </div>

    <!-- Category Filter Pills -->
    <div class="flex flex-wrap gap-2 mb-4">
      <OButton
        type="secondary"
        size="sm"
        label="All Categories"
        :class="selectedCategory === null ? 'bg-primary text-white' : ''"
        @click="$emit('selectCategory', null)"
      />
      <OButton
        v-for="category in categories"
        :key="category"
        type="secondary"
        size="sm"
        :label="formatCategoryName(category)"
        :class="selectedCategory === category ? 'bg-primary text-white' : ''"
        @click="$emit('selectCategory', category)"
      />
    </div>
  </Card>
</template>

<script setup lang="ts">
import { OButton, OIcon, OInput } from '@owlint/feathers-vue'

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
  return category.replace(/([A-Z])/g, ' $1').replace(/^./, str => str.toUpperCase())
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