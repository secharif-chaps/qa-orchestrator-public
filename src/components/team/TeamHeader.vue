<template>
  <div>
    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-3xl font-bold">
          {{ $t('team.title', 'Team Management') }}
        </h1>
        <p class="text-secondary mt-2">
          {{ $t('team.description', 'Manage users in your workspace') }}
        </p>
      </div>

      <Button
        v-if="canManageUsers"
        variant="tertiary"
        icon="fa fa-plus"
        :label="$t('team.create.button', 'Add User')"
        @click="emit('create-user')"
      />
    </div>

    <div class="flex items-center justify-between gap-4">
      <div class="flex-1 max-w-md">
        <div class="relative">
          <i
            class="fa fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-secondary"
          ></i>
          <Input
            :model-value="props.search"
            @input="emit('update:search', $event.target.value)"
            type="text"
            :placeholder="$t('team.search.placeholder', 'Search users...')"
          />
        </div>
      </div>

      <div class="relative">
        <button
          @click.stop="showSortDropdown = !showSortDropdown"
          class="flex items-center gap-2 px-3 py-2 border border-border-2 rounded-lg hover:bg-bg2 transition-colors text-sm font-medium bg-bg1"
        >
          <span class="text-secondary">{{ getSortDisplayText() }}</span>
          <i
            class="fa fa-chevron-down text-xs transition-transform"
            :class="{ 'rotate-180': showSortDropdown }"
          ></i>
        </button>

        <div
          v-if="showSortDropdown"
          class="absolute right-0 mt-2 w-56 bg-bg1 border border-border-2 rounded-lg shadow-lg z-50"
          @click.stop
        >
          <div class="p-4 border-b border-border-2">
            <h3 class="text-sm font-medium text-primary mb-3">
              {{ $t('team.sort.label', 'Sort by:') }}
            </h3>
            <div class="space-y-2">
              <label class="flex items-center gap-3 cursor-pointer">
                <input
                  type="radio"
                  :checked="props.sort === 'created_at'"
                  @change="updateSort('created_at')"
                  class="w-4 h-4 text-primary border-border-2 focus:ring-primary/20"
                />
                <span class="text-sm">{{ $t('team.sort.created', 'Created Date') }}</span>
              </label>
              <label class="flex items-center gap-3 cursor-pointer">
                <input
                  type="radio"
                  :checked="props.sort === 'name'"
                  @change="updateSort('name')"
                  class="w-4 h-4 text-primary border-border-2 focus:ring-primary/20"
                />
                <span class="text-sm">{{ $t('team.sort.name', 'Name') }}</span>
              </label>
              <label class="flex items-center gap-3 cursor-pointer">
                <input
                  type="radio"
                  :checked="props.sort === 'email'"
                  @change="updateSort('email')"
                  class="w-4 h-4 text-primary border-border-2 focus:ring-primary/20"
                />
                <span class="text-sm">{{ $t('team.sort.email', 'Email') }}</span>
              </label>
              <label class="flex items-center gap-3 cursor-pointer">
                <input
                  type="radio"
                  :checked="props.sort === 'username'"
                  @change="updateSort('username')"
                  class="w-4 h-4 text-primary border-border-2 focus:ring-primary/20"
                />
                <span class="text-sm">{{ $t('team.sort.username', 'Username') }}</span>
              </label>
            </div>
          </div>

          <div class="p-4">
            <h3 class="text-sm font-medium text-primary mb-3">
              {{ $t('team.sort.order', 'Sort Order:') }}
            </h3>
            <div class="space-y-2">
              <label class="flex items-center gap-3 cursor-pointer">
                <input
                  type="radio"
                  :checked="props.order === 'asc'"
                  @change="updateOrder('asc')"
                  class="w-4 h-4 text-primary border-border-2 focus:ring-primary/20"
                />
                <span class="text-sm flex items-center gap-2">
                  <i class="fa fa-sort-amount-up"></i>
                  {{ $t('team.sort.ascending', 'Ascending (A-Z)') }}
                </span>
              </label>
              <label class="flex items-center gap-3 cursor-pointer">
                <input
                  type="radio"
                  :checked="props.order === 'desc'"
                  @change="updateOrder('desc')"
                  class="w-4 h-4 text-primary border-border-2 focus:ring-primary/20"
                />
                <span class="text-sm flex items-center gap-2">
                  <i class="fa fa-sort-amount-down"></i>
                  {{ $t('team.sort.descending', 'Descending (Z-A)') }}
                </span>
              </label>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, ref, onMounted, onUnmounted } from 'vue'
import { useAuthStore } from '@/stores/auth'
import type { WorkspaceUserQueryParams } from '@/types/team'
import Button from '@/components/ui/Button.vue'
import Input from '../ui/Input.vue'

const props = defineProps<{
  search: string
  sort: WorkspaceUserQueryParams['sort']
  order: WorkspaceUserQueryParams['order']
  status: WorkspaceUserQueryParams['status']
  pageSize: number
}>()

const emit = defineEmits<{
  'create-user': []
  'update:search': [value: string]
  'update:sort': [value: WorkspaceUserQueryParams['sort']]
  'toggle-order': []
  'update:status': [value: WorkspaceUserQueryParams['status']]
  'update:page-size': [value: number]
}>()

const authStore = useAuthStore()

// Only users with workspace.write can manage users (add, edit, disable)
const canManageUsers = computed(() => authStore.hasPermission('workspace.write'))

// Dropdown state
const showSortDropdown = ref(false)

// Helper methods
const getSortDisplayText = () => {
  const sortLabels = {
    created_at: 'Created Date',
    name: 'Name',
    email: 'Email',
    username: 'Username',
  }
  const orderText = props.order === 'asc' ? 'A-Z' : 'Z-A'
  return `${sortLabels[props.sort]} (${orderText})`
}

const updateSort = (newSort: WorkspaceUserQueryParams['sort']) => {
  emit('update:sort', newSort)
  showSortDropdown.value = false
}

const updateOrder = (newOrder: WorkspaceUserQueryParams['order']) => {
  if (newOrder !== props.order) {
    emit('toggle-order')
  }
  showSortDropdown.value = false
}

// Close dropdown when clicking outside
const handleClickOutside = (event: MouseEvent) => {
  if (showSortDropdown.value) {
    showSortDropdown.value = false
  }
}

onMounted(() => {
  document.addEventListener('click', handleClickOutside)
})

onUnmounted(() => {
  document.removeEventListener('click', handleClickOutside)
})
</script>
