<template>
  <div class="p-12 text-center">
    <i class="fa fa-users text-4xl text-secondary/50 mb-4"></i>
    <h3 class="text-lg font-medium text-base mb-2">
      {{ title }}
    </h3>
    <p class="text-secondary mb-6">
      {{ description }}
    </p>
    <Button
      v-if="hasFilters"
      variant="secondary"
      :label="$t('admin.users.clearFilters', 'Clear Filters')"
      @click="$emit('clear-filters')"
    />
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { Button } from '@owlint/feathers-vue'

interface Props {
  hasFilters: boolean
}

const props = defineProps<Props>()

defineEmits<{
  'clear-filters': []
}>()

const title = computed(() => {
  if (props.hasFilters) {
    return 'No users found'
  }
  return 'No users found'
})

const description = computed(() => {
  if (props.hasFilters) {
    return 'Try a different search or filter'
  }
  return 'No users in the system'
})
</script>
