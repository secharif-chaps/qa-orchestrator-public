<template>
  <div class="dark:bg-primary-lightest rounded-sm bg-white p-4">
    <div class="mb-4 flex items-center gap-3">
      <Badge :icon="categoryIcon" variant="secondary" size="lg" rounded class="shrink-0"> </Badge>
      <div>
        <h3 class="text-lg font-semibold capitalize">{{ formattedCategoryName }}</h3>
        <p class="text-neutral-black-font text-sm">
          {{ t('screen.products.count', { count: productList.length }) }}
        </p>
      </div>
    </div>

    <div class="space-y-2">
      <div
        v-for="(product, index) in displayedProducts"
        :key="index"
        class="bg-primary-lighter flex items-center gap-3 rounded-sm p-3 transition-colors"
      >
        <span class="flex-1 text-sm capitalize">{{ product }}</span>
        <Tag v-if="isNewProduct(product)" variant="success" size="xs">
          <i class="fa-solid fa-star"></i>
          {{ t('screen.products.badges.new') }}
        </Tag>
      </div>
    </div>

    <div v-if="productList.length > maxDisplayItems" class="mt-4 text-center">
      <Button
        variant="tertiary"
        size="sm"
        :icon="showAll ? 'fa fa-chevron-up' : 'fa fa-chevron-down'"
        :label="
          showAll
            ? t('screen.products.showLess')
            : t('screen.products.showMore', { count: productList.length - maxDisplayItems })
        "
        @click="toggleShowAll"
      />
    </div>
  </div>
</template>

<script setup lang="ts">
import Tag from '@/components/ui/Tag.vue'
import { Badge, Button } from '@owlint/feathers-vue'
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()

interface Props {
  category: string
  productList: string[]
  maxDisplayItems?: number
}

const props = withDefaults(defineProps<Props>(), {
  maxDisplayItems: 5,
})

const emit = defineEmits<{
  toggleShowAll: [category: string]
}>()

// Internal state
const showAll = ref(false)

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

const displayedProducts = computed(() => {
  return showAll.value ? props.productList : props.productList.slice(0, props.maxDisplayItems)
})

// Methods
const isNewProduct = (product: string) => {
  const newKeywords = ['2024', '2025', 'new', 'latest', 'beta', 'preview', 'next-gen']
  return newKeywords.some((keyword) => product.toLowerCase().includes(keyword))
}

const toggleShowAll = () => {
  showAll.value = !showAll.value
  emit('toggleShowAll', props.category)
}
</script>

<style scoped>
.product-card {
  transition: all 0.3s ease;
}

.product-card:hover {
  transform: translateY(-2px);
}
</style>
