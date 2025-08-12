<template>
  <div class="mb-8">
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
        variant="primary"
        icon="fa fa-plus"
        :label="$t('team.create.button', 'Add User')"
        @click="$emit('create-user')"
      />
    </div>

    <div class="flex items-center gap-4 bg-bg1 p-4 rounded-lg shadow-sm border border-border-2">
      <div class="flex-1 max-w-md">
        <div class="relative">
          <i
            class="fa fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-secondary"
          ></i>
          <input
            :model-value="search"
            @input="$emit('update:search', $event.target.value)"
            type="text"
            :placeholder="$t('team.search.placeholder', 'Search users...')"
            class="w-full pl-10 pr-4 py-2 border border-border-2 rounded-lg focus:ring-2 focus:ring-primary/20 focus:border-primary"
          />
        </div>
      </div>

      <div class="flex items-center gap-2">
        <label class="text-sm text-secondary">{{ $t('team.sort.label', 'Sort by:') }}</label>
        <select
          :model-value="sort"
          @change="$emit('update:sort', $event.target.value)"
          class="px-3 py-2 border border-border-2 rounded-lg text-sm focus:ring-2 focus:ring-primary/20 focus:border-primary"
        >
          <option value="created_at">{{ $t('team.sort.created', 'Created Date') }}</option>
          <option value="name">{{ $t('team.sort.name', 'Name') }}</option>
          <option value="email">{{ $t('team.sort.email', 'Email') }}</option>
          <option value="username">{{ $t('team.sort.username', 'Username') }}</option>
        </select>

        <button
          @click="$emit('toggle-order')"
          class="flex items-center gap-2 px-3 py-2 border border-border-2 rounded-lg hover:bg-bg2 transition-colors text-sm font-medium"
          :title="
            order === 'asc'
              ? $t('team.sort.desc', 'Sort Descending')
              : $t('team.sort.asc', 'Sort Ascending')
          "
        >
          <span class="text-secondary">{{ order === 'asc' ? 'A-Z' : 'Z-A' }}</span>
          <div class="flex flex-col items-center gap-0.5">
            <i
              class="fa fa-chevron-up text-xs transition-colors"
              :class="order === 'asc' ? 'text-primary' : 'text-gray-300'"
            ></i>
            <i
              class="fa fa-chevron-down text-xs transition-colors"
              :class="order === 'desc' ? 'text-primary' : 'text-gray-300'"
            ></i>
          </div>
        </button>
      </div>

      <div class="flex items-center gap-2">
        <label class="text-sm text-secondary">{{ $t('team.status.label', 'Status:') }}</label>
        <select
          :model-value="status"
          @change="$emit('update:status', $event.target.value)"
          class="px-3 py-2 border border-border-2 rounded-lg text-sm focus:ring-2 focus:ring-primary/20 focus:border-primary"
        >
          <option value="all">{{ $t('team.status.all', 'All') }}</option>
          <option value="active">{{ $t('team.status.active', 'Active') }}</option>
          <option value="disabled">{{ $t('team.status.disabled', 'Disabled') }}</option>
        </select>
      </div>

      <div class="flex items-center gap-2">
        <label class="text-sm text-secondary">{{ $t('team.pageSize.label', 'Show:') }}</label>
        <select
          :model-value="pageSize"
          @change="$emit('update:page-size', parseInt($event.target.value))"
          class="px-3 py-2 border border-border-2 rounded-lg text-sm focus:ring-2 focus:ring-primary/20 focus:border-primary"
        >
          <option value="10">10</option>
          <option value="20">20</option>
          <option value="50">50</option>
          <option value="100">100</option>
        </select>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useAuthStore } from '@/stores/auth'
import type { WorkspaceUserQueryParams } from '@/types/team'
import Button from '@/components/ui/Button.vue'

defineProps<{
  search: string
  sort: WorkspaceUserQueryParams['sort']
  order: WorkspaceUserQueryParams['order']
  status: WorkspaceUserQueryParams['status']
  pageSize: number
}>()

defineEmits<{
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
</script>
