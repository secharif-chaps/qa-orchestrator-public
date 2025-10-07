<template>
  <div class="bg-base-100 rounded-lg shadow-sm p-12 text-center">
    <i class="fa fa-users text-4xl text-primary-light-content/50 mb-4"></i>
    <h3 class="text-lg font-medium text-base mb-2">
      {{ title }}
    </h3>
    <p class="text-primary-light-content mb-6">
      {{ description }}
    </p>
    <button
      v-if="showCreateButton && canManageUsers"
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
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '@/stores/auth'

const { t } = useI18n()

const props = defineProps<{
  type: 'no-users' | 'no-results' | 'loading'
  hasSearch?: boolean
}>()

defineEmits<{
  'create-user': []
  'clear-search': []
}>()

const authStore = useAuthStore()

// Only users with workspace.write can manage users (add, edit, disable)
const canManageUsers = computed(() => authStore.hasPermission('workspace.write'))

const title = computed(() => {
  switch (props.type) {
    case 'no-users':
      return t('team.empty.noUsers.title', 'No users in workspace')
    case 'no-results':
      return t('team.empty.noResults.title', 'No users found')
    case 'loading':
      return t('team.empty.loading.title', 'Loading users...')
    default:
      return t('team.empty.default', 'No users')
  }
})

const description = computed(() => {
  switch (props.type) {
    case 'no-users':
      return t(
        'team.empty.noUsers.description',
        'Add your first team member to get started with collaboration',
      )
    case 'no-results':
      return props.hasSearch
        ? t('team.empty.noResults.description', 'Try a different search term or filter')
        : t('team.empty.noResults.descriptionNoFilter', 'No users match the current filters')
    case 'loading':
      return t('team.empty.loading.description', 'Please wait while we load your team members')
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
