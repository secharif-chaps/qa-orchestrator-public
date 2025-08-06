<template>
  <div class="bg-bg1 rounded-lg shadow-sm p-12 text-center">
    <i class="fa fa-users text-4xl text-secondary/50 mb-4"></i>
    <h3 class="text-lg font-medium text-base mb-2">
      {{ title }}
    </h3>
    <p class="text-secondary mb-6">
      {{ description }}
    </p>
    <button
      v-if="showCreateButton"
      @click="$emit('create-user')"
      class="bg-primary text-white px-6 py-2 rounded-lg hover:bg-primary/80 transition-colors"
    >
      {{ $t('team.create.button', 'Add User') }}
    </button>
    <button
      v-else-if="showClearButton"
      @click="$emit('clear-search')"
      class="bg-secondary text-white px-6 py-2 rounded-lg hover:bg-secondary/80 transition-colors"
    >
      {{ $t('team.clearSearch', 'Clear Search') }}
    </button>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'

const props = defineProps<{
  type: 'no-users' | 'no-results' | 'loading'
  hasSearch?: boolean
}>()

defineEmits<{
  'create-user': []
  'clear-search': []
}>()

const title = computed(() => {
  switch (props.type) {
    case 'no-users':
      return 'No users in workspace'
    case 'no-results':
      return 'No users found'
    case 'loading':
      return 'Loading users...'
    default:
      return 'No users'
  }
})

const description = computed(() => {
  switch (props.type) {
    case 'no-users':
      return 'Add your first team member to get started with collaboration'
    case 'no-results':
      return props.hasSearch 
        ? 'Try a different search term or filter'
        : 'No users match the current filters'
    case 'loading':
      return 'Please wait while we load your team members'
    default:
      return ''
  }
})

const showCreateButton = computed(() => {
  return props.type === 'no-users'
})

const showClearButton = computed(() => {
  return props.type === 'no-results' && props.hasSearch
})
</script>