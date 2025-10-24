<template>
  <div>
    <div class="flex items-center gap-4">
      <!-- Search Input -->
      <div class="flex-1">
        <Input
          :model-value="search"
          icon="fa fa-search"
          :placeholder="$t('admin.users.search.placeholder', 'Search by username or email...')"
          clearable
          @update:model-value="handleSearchInput"
        />
      </div>

      <!-- Workspace Filter Dropdown -->
      <Dropdown align="left" width="md">
        <template #trigger>
          <Button variant="secondary" icon="fa fa-filter">
            {{ workspaceFilterLabel }}
          </Button>
        </template>

        <template #content="{ close }">
          <DropdownItem @click="selectWorkspaceFilter(null, close)">
            <i class="fa fa-users"></i>
            {{ $t('admin.users.filter.allUsers', 'All users') }}
          </DropdownItem>

          <DropdownItem @click="selectWorkspaceFilter('none', close)">
            <i class="fa fa-user-slash"></i>
            {{ $t('admin.users.filter.noWorkspace', 'No workspace') }}
          </DropdownItem>

          <DropdownDivider />

          <DropdownItem
            v-for="workspace in workspaces"
            :key="workspace.id"
            @click="selectWorkspaceFilter(workspace.id.toString(), close)"
          >
            <i class="fa fa-building"></i>
            {{ workspace.name }}
          </DropdownItem>
        </template>
      </Dropdown>

      <!-- Sort Dropdown -->
      <Dropdown align="right" width="md">
        <template #trigger>
          <Button variant="secondary" icon="fa fa-sort">
            {{ sortLabel }}
          </Button>
        </template>

        <template #content="{ close }">
          <!-- Sort by Username -->
          <div class="px-4 py-2 text-xs font-semibold text-secondary uppercase">
            {{ $t('admin.users.sort.sortBy', 'Sort by') }}
          </div>

          <DropdownItem @click="selectSort('username', close)">
            <i class="fa fa-user"></i>
            {{ $t('admin.users.sort.username', 'Username') }}
          </DropdownItem>

          <DropdownItem @click="selectSort('workspace', close)">
            <i class="fa fa-building"></i>
            {{ $t('admin.users.sort.workspace', 'Workspace') }}
          </DropdownItem>

          <DropdownItem @click="selectSort('created_at', close)">
            <i class="fa fa-calendar"></i>
            {{ $t('admin.users.sort.createdDate', 'Created Date') }}
          </DropdownItem>

          <DropdownDivider />

          <!-- Sort order -->
          <div class="px-4 py-2 text-xs font-semibold text-secondary uppercase">
            {{ $t('admin.users.sort.order', 'Order') }}
          </div>

          <DropdownItem @click="selectOrder('asc', close)">
            <i class="fa fa-sort-amount-up"></i>
            {{ $t('admin.users.sort.ascending', 'Ascending (A-Z)') }}
          </DropdownItem>

          <DropdownItem @click="selectOrder('desc', close)">
            <i class="fa fa-sort-amount-down"></i>
            {{ $t('admin.users.sort.descending', 'Descending (Z-A)') }}
          </DropdownItem>
        </template>
      </Dropdown>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import Input from '@/components/ui/Input.vue'
import Button from '@/components/ui/Button.vue'
import Dropdown from '@/components/ui/Dropdown.vue'
import DropdownItem from '@/components/ui/DropdownItem.vue'
import DropdownDivider from '@/components/ui/DropdownDivider.vue'
import type { WorkspaceResponse } from '@/types/workspace'
import type { AdminUserQueryParams } from '@/types/admin-user'

interface Props {
  search: string
  workspaceFilter: string | null
  sort: AdminUserQueryParams['sort']
  order: AdminUserQueryParams['order']
  workspaces: WorkspaceResponse[]
}

const props = defineProps<Props>()

const emit = defineEmits<{
  'update:search': [value: string]
  'update:workspace-filter': [value: string | null]
  'update:sort': [value: AdminUserQueryParams['sort']]
  'update:order': [value: AdminUserQueryParams['order']]
}>()

// Computed labels
const workspaceFilterLabel = computed(() => {
  if (props.workspaceFilter === null) {
    return 'All users'
  }
  if (props.workspaceFilter === 'none') {
    return 'No workspace'
  }
  const workspace = props.workspaces.find((w) => w.id.toString() === props.workspaceFilter)
  return workspace ? workspace.name : 'Filter by workspace'
})

const sortLabel = computed(() => {
  const sortLabels = {
    username: 'Username',
    workspace: 'Workspace',
    created_at: 'Created Date',
  }
  const orderText = props.order === 'asc' ? 'A-Z' : 'Z-A'
  return `${sortLabels[props.sort]} (${orderText})`
})

// Event handlers
const handleSearchInput = (value: string) => {
  emit('update:search', value)
}

const selectWorkspaceFilter = (filter: string | null, close: () => void) => {
  emit('update:workspace-filter', filter)
  close()
}

const selectSort = (sort: AdminUserQueryParams['sort'], close: () => void) => {
  emit('update:sort', sort)
  close()
}

const selectOrder = (order: AdminUserQueryParams['order'], close: () => void) => {
  emit('update:order', order)
  close()
}
</script>
