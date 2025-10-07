<template>
  <div class="bg-base-100 rounded-lg p-4">
    <div class="flex items-center justify-between mb-6">
      <div class="flex items-center gap-3">
        <div
          class="w-12 h-12 rounded-lg bg-primary/10 dark:bg-primary/20 flex items-center justify-center text-2xl"
        >
          <i class="fa-solid fa-box text-primary"></i>
        </div>
        <div>
          <h2 class="text-xl font-semibold">Product Portfolio</h2>
          <p class="text-sm text-primary-light-content">
            {{ totalProductCount }} products across {{ categoryCount }} categories
          </p>
        </div>
      </div>
      <div class="flex gap-2">
        <Button
          variant="ghost-primary"
          size="sm"
          :icon="viewMode === 'grid' ? 'fa fa-list' : 'fa fa-th-large'"
          :label="viewMode === 'grid' ? 'List View' : 'Grid View'"
          @click="$emit('toggleViewMode')"
        />

        <div class="w-64 relative">
          <i class="fas fa-search absolute left-2 top-1/2 -translate-y-1/2 text-primary-light-content"></i>
          <input
            :value="searchQuery"
            placeholder="Search products..."
            class="w-full sm:w-64 bg-base-300 border border-primary-stroke rounded-md p-2 pl-8 focus:outline-none focus-within:ring-2 focus-within:ring-primary focus-within:ring-offset-2 ring-primary ring-offset-bg3"
            @input="$emit('updateSearch', ($event.target as HTMLInputElement).value)"
          />
        </div>
      </div>
    </div>

    <!-- Category Filter Pills -->
    <div class="flex flex-wrap gap-2 mb-4">
      <Badge
        label="All Categories"
        :variant="selectedCategory === null ? 'primary' : 'slate'"
        size="md"
        :icon="selectedCategory === null ? 'fa fa-check' : 'fa fa-layer-group'"
        rounded
        class="cursor-pointer hover:opacity-80 transition-opacity"
        @click="$emit('selectCategory', null)"
      />
      <Badge
        v-for="category in categories"
        :key="category"
        :label="formatCategoryName(category)"
        :variant="selectedCategory === category ? 'success' : 'slate'"
        size="md"
        :icon="selectedCategory === category ? 'fa fa-check' : getCategoryIcon(category)"
        rounded
        class="cursor-pointer hover:opacity-80 transition-opacity"
        @click="$emit('selectCategory', category)"
      />
    </div>
  </div>
</template>

<script setup lang="ts">
import Button from '@/components/ui/Button.vue'
import Badge from '@/components/ui/Badge.vue'

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

const getCategoryIcon = (category: string) => {
  const iconMap: Record<string, string> = {
    software: 'fa fa-laptop-code',
    hardware: 'fa fa-microchip',
    services: 'fa fa-handshake',
    consulting: 'fa fa-lightbulb',
    saas: 'fa fa-cloud',
    mobile: 'fa fa-mobile-alt',
    web: 'fa fa-globe',
    enterprise: 'fa fa-building',
    consumer: 'fa fa-users',
    healthcare: 'fa fa-heartbeat',
    finance: 'fa fa-chart-line',
    education: 'fa fa-graduation-cap',
    retail: 'fa fa-shopping-cart',
    gaming: 'fa fa-gamepad',
    analytics: 'fa fa-chart-bar',
    security: 'fa fa-shield-alt',
    ai: 'fa fa-robot',
    blockchain: 'fa fa-link',
    iot: 'fa fa-wifi',
  }

  const normalizedCategory = category.toLowerCase().replace(/\s+/g, '')
  return iconMap[normalizedCategory] || 'fa fa-box'
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
