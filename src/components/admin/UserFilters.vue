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

      <!-- Organization Filter Dropdown -->
      <Dropdown align="left" width="md">
        <template #trigger>
          <Button variant="secondary" icon="fa fa-filter">
            {{ organizationFilterLabel }}
          </Button>
        </template>

        <template #content="{ close }">
          <DropdownItem @click="selectOrganizationFilter(null, close)">
            <i class="fa fa-users"></i>
            {{ $t('admin.users.filter.allUsers', 'All users') }}
          </DropdownItem>

          <DropdownItem @click="selectOrganizationFilter('none', close)">
            <i class="fa fa-user-slash"></i>
            {{ $t('admin.users.filter.noOrganization', 'No organization') }}
          </DropdownItem>

          <DropdownDivider />

          <DropdownItem
            v-for="organization in organizations"
            :key="organization.id"
            @click="selectOrganizationFilter(organization.id, close)"
          >
            <i class="fa fa-building"></i>
            {{ organization.name }}
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

          <DropdownItem @click="selectSort('organization', close)">
            <i class="fa fa-building"></i>
            {{ $t('admin.users.sort.organization', 'Organization') }}
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
import type { OrganizationAdminResponse } from '@/types/organization'
import type { AdminUserQueryParams } from '@/types/admin-user'

interface Props {
  search: string
  organizationFilter: string | null
  sort: AdminUserQueryParams['sort']
  order: AdminUserQueryParams['order']
  organizations: OrganizationAdminResponse[]
}

const props = defineProps<Props>()

const emit = defineEmits<{
  'update:search': [value: string]
  'update:organization-filter': [value: string | null]
  'update:sort': [value: AdminUserQueryParams['sort']]
  'update:order': [value: AdminUserQueryParams['order']]
}>()

// Computed labels
const organizationFilterLabel = computed(() => {
  if (props.organizationFilter === null) {
    return 'All users'
  }
  if (props.organizationFilter === 'none') {
    return 'No organization'
  }
  const organization = props.organizations.find((org) => org.id === props.organizationFilter)
  return organization ? organization.name : 'Filter by organization'
})

const sortLabel = computed(() => {
  const sortLabels = {
    username: 'Username',
    organization: 'Organization',
    created_at: 'Created Date',
  }
  const orderText = props.order === 'asc' ? 'A-Z' : 'Z-A'
  return `${sortLabels[props.sort]} (${orderText})`
})

// Event handlers
const handleSearchInput = (value: string) => {
  emit('update:search', value)
}

const selectOrganizationFilter = (filter: string | null, close: () => void) => {
  emit('update:organization-filter', filter)
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
