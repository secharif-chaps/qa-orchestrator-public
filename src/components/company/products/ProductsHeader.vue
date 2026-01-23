<template>
  <div class="bg-base-100 rounded-lg p-4">
    <div class="flex items-center justify-between mb-6">
      <div class="flex items-center gap-3">
        <div>
          <h2 class="text-xl font-semibold">{{ t('products.header.title') }}</h2>
          <p class="text-sm text-secondary">
            {{ t('products.header.summary', { total: totalProductCount, categories: categoryCount }) }}
          </p>
        </div>
      </div>
      <div class="flex gap-2">
        <Button
          variant="tertiary"
          size="sm"
          :icon="viewMode === 'grid' ? 'fa fa-list' : 'fa fa-th-large'"
          :label="viewMode === 'grid' ? t('products.viewMode.list') : t('products.viewMode.grid')"
          @click="$emit('toggleViewMode')"
        />

        <div class="w-64 relative">
          <i class="fas fa-search absolute left-2 top-1/2 -translate-y-1/2 text-secondary"></i>
          <Searchbar
            id="product-search"
            :value="searchQuery"
            :placeholder="t('products.search.placeholder')"
            @input="$emit('updateSearch', ($event.target as HTMLInputElement).value)"
          />
        </div>
      </div>
    </div>

    <!-- Category Filter Pills -->
    <div class="flex flex-wrap gap-2 mb-4">
      <Tag
        :label="t('products.categories.all')"
        :variant="selectedCategory === null ? 'sage' : 'slate'"
        size="md"
        :icon="selectedCategory === null ? 'fa fa-check' : 'fa fa-layer-group'"
        rounded
        class="cursor-pointer hover:opacity-80 transition-opacity"
        @click="$emit('selectCategory', null)"
      />
      <Tag
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
import { Button, Searchbar } from '@owlint/feathers-vue'
import Tag from '@/components/ui/Tag.vue'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()

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
