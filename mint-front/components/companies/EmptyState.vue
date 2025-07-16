<template>
  <Card>
    <div class="text-center py-8">
      <i :class="iconClass" class="text-3xl text-secondary mb-3"></i>
      <h3 class="text-lg font-semibold text-secondary mb-2">{{ title }}</h3>
      <p class="text-secondary mb-4">{{ description }}</p>
      <slot name="actions">
        <OButton
          v-if="type === 'no-data'"
          :label="$t('company.list.create.title')"
          icon="fas fa-plus"
          type="primary"
          @click="$emit('createCompany')"
        />
      </slot>
    </div>
  </Card>
</template>

<script setup lang="ts">
import { OButton } from '@owlint/feathers-vue'

interface Props {
  type: 'no-data' | 'no-results'
  searchQuery?: string
}

const props = defineProps<Props>()

defineEmits<{
  createCompany: []
}>()

const iconClass = computed(() => {
  return props.type === 'no-results' ? 'fa fa-search' : 'fa fa-building'
})

const title = computed(() => {
  return props.type === 'no-results' ? 'No companies found' : 'No companies yet'
})

const description = computed(() => {
  if (props.type === 'no-results') {
    return props.searchQuery 
      ? `No companies match "${props.searchQuery}". Try adjusting your search terms.`
      : 'Try adjusting your search terms'
  }
  return 'Get started by creating your first company to track and manage your business relationships.'
})
</script>