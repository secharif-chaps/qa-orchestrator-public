<template>
  <Card>
    <div class="text-center py-8">
      <i :class="iconClass" class="text-3xl text-slate-300 dark:text-slate-600 mb-3"></i>
      <h3 class="text-lg font-semibold text-slate-900 dark:text-slate-100 mb-2">{{ title }}</h3>
      <p class="text-slate-500 dark:text-slate-400">{{ description }}</p>
      <slot name="actions"></slot>
    </div>
  </Card>
</template>

<script setup lang="ts">
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
