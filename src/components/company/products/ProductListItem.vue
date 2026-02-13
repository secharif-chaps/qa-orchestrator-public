<template>
  <div class="border-base-200 border-b pb-6 last:border-b-0 last:pb-0">
    <div class="mb-4 flex items-center gap-3">
      <Badge :icon="categoryIcon" variant="secondary" size="lg" rounded class="shrink-0"> </Badge>
      <div class="flex-1">
        <h3 class="text-lg font-semibold capitalize">{{ formattedCategoryName }}</h3>
        <p class="text-secondary text-sm">
          {{ t('products.countInCategory', { count: productList.length }) }}
        </p>
      </div>
      <Tag :label="productList.length.toString()" variant="slate" size="sm" />
    </div>

    <div class="grid grid-cols-1 gap-2 md:grid-cols-2 lg:grid-cols-3">
      <div
        v-for="(product, index) in productList"
        :key="index"
        class="bg-base-300 flex items-center gap-2 rounded p-2 text-sm"
      >
        <span class="text-secondary flex-1 capitalize">{{ product }}</span>
        <Tag v-if="isNewProduct(product)" variant="success" size="xs">
          <i class="fa-solid fa-star"></i>
          {{ t('products.badges.new') }}
        </Tag>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import Tag from '@/components/ui/Tag.vue'
import { Badge } from '@owlint/feathers-vue'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()

interface Props {
  category: string
  productList: string[]
}

const props = defineProps<Props>()

// Computed properties
const formattedCategoryName = computed(() => {
  return props.category.replace(/([A-Z])/g, ' $1').replace(/^./, (str) => str.toUpperCase())
})

const categoryIcon = computed(() => {
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

  const normalizedCategory = props.category.toLowerCase().replace(/\s+/g, '')
  return iconMap[normalizedCategory] || 'fa fa-box'
})

// Methods
const isNewProduct = (product: string) => {
  const newKeywords = ['2024', '2025', 'new', 'latest', 'beta', 'preview', 'next-gen']
  return newKeywords.some((keyword) => product.toLowerCase().includes(keyword))
}
</script>

<style scoped>
.product-item:hover {
  transform: translateX(2px);
  transition: transform 0.2s ease;
}
</style>
