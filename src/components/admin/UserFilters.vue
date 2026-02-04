<template>
  <div>
    <div class="flex items-center gap-4">
      <!-- Search Input with Clear Button -->
      <div class="relative w-96">
        <Searchbar
          ref="searchbarRef"
          id="user-search"
          :model-value="search"
          :placeholder="$t('admin.users.search.placeholder', 'Search by username or email...')"
          @update:model-value="handleSearchInput"
          class="w-full"
        />
        <button
          v-if="search"
          type="button"
          class="absolute right-3 top-1/2 -translate-y-1/2 flex items-center justify-center w-5 h-5 text-secondary hover:text-primary transition-colors"
          :aria-label="$t('admin.users.search.clear', 'Clear search')"
          @click="handleClearSearch"
        >
          <Icon icon="fa fa-times" class="text-secondary" />
        </button>
      </div>

      <!-- Sort Dropdown -->
      <Dropdown align="right" width="md">
        <template #trigger>
          <Button variant="secondary" icon="fa fa-sort" :label="sortLabel" />
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
import { computed, ref } from 'vue'
import { Button, Icon, Searchbar } from '@owlint/feathers-vue'
import Dropdown from '@/components/ui/Dropdown.vue'
import DropdownItem from '@/components/ui/DropdownItem.vue'
import DropdownDivider from '@/components/ui/DropdownDivider.vue'
import type { AdminUserQueryParams } from '@/types/admin-user'

interface Props {
  search: string
  sort: AdminUserQueryParams['sort']
  order: AdminUserQueryParams['order']
}

const props = defineProps<Props>()

// Template ref to access Searchbar's focus method
const searchbarRef = ref<InstanceType<typeof Searchbar> | null>(null)

const emit = defineEmits<{
  'update:search': [value: string]
  'update:sort': [value: AdminUserQueryParams['sort']]
  'update:order': [value: AdminUserQueryParams['order']]
}>()

const sortLabel = computed(() => {
  const sortLabels: Record<AdminUserQueryParams['sort'], string> = {
    username: 'Username',
    created_at: 'Created Date',
  }
  const orderText = props.order === 'asc' ? 'A-Z' : 'Z-A'
  return `${sortLabels[props.sort]} (${orderText})`
})

// Event handlers
const handleSearchInput = (value: string | number) => {
  emit('update:search', String(value))
}

const handleClearSearch = () => {
  emit('update:search', '')
  // Refocus the search input after clearing
  searchbarRef.value?.focus()
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
