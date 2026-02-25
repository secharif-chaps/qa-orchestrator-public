<template>
  <Card>
    <div class="py-8 text-center">
      <i :class="iconClass" class="text-secondary mb-3 text-3xl"></i>
      <h3 class="mb-2 text-lg font-semibold">{{ title }}</h3>
      <p class="text-secondary">{{ description }}</p>
      <slot name="actions"></slot>
    </div>
  </Card>
</template>

<script setup lang="ts">
import { computed } from 'vue'

interface Props {
  type: 'no-data' | 'no-results'
  searchQuery?: string
}

const props = defineProps<Props>()

const iconClass = computed(() => {
  return props.type === 'no-results' ? 'fa fa-search' : 'fa fa-box'
})

const title = computed(() => {
  return props.type === 'no-results' ? 'No products found' : 'No products available'
})

const description = computed(() => {
  if (props.type === 'no-results') {
    return props.searchQuery
      ? `No products match "${props.searchQuery}". Try adjusting your search terms or filters.`
      : 'Try adjusting your search terms or filters'
  }
  return 'Product information will be displayed here once available.'
})
</script>
