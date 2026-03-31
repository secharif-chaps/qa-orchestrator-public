<template>
  <div>
    <div class="flex items-center gap-4">
      <!-- Search Input with Clear Button -->
      <Searchbar
        ref="searchbar"
        id="user-search"
        :model-value="search"
        :placeholder="$t('admin.users.search.placeholder')"
        @update:model-value="handleSearchInput"
        class="w-[28rem]"
      >
        <Button
          v-if="search"
          icon="fa-close"
          kind="gray"
          size="sm"
          :aria-label="$t('admin.users.search.clear')"
          @click="handleClearSearch"
        />
      </Searchbar>

      <!-- Sort Dropdown -->
      <Dropdown align="right" width="md">
        <template #trigger>
          <Button variant="secondary" icon="fa fa-sort" :label="sortLabel" />
        </template>

        <template #content="{ close }">
          <!-- Sort by Username -->
          <div class="text-secondary px-4 py-2 text-xs font-semibold uppercase">
            {{ $t('admin.users.sort.sortBy') }}
          </div>

          <DropdownItem @click="selectSort('username', close)">
            <i class="fa fa-user"></i>
            {{ $t('admin.users.sort.username') }}
          </DropdownItem>

          <DropdownItem @click="selectSort('created_at', close)">
            <i class="fa fa-calendar"></i>
            {{ $t('admin.users.sort.createdDate') }}
          </DropdownItem>

          <DropdownDivider />

          <!-- Sort order -->
          <div class="text-secondary px-4 py-2 text-xs font-semibold uppercase">
            {{ $t('admin.users.sort.order') }}
          </div>

          <DropdownItem @click="selectOrder('asc', close)">
            <i class="fa fa-sort-amount-up"></i>
            {{ $t('admin.users.sort.ascending') }}
          </DropdownItem>

          <DropdownItem @click="selectOrder('desc', close)">
            <i class="fa fa-sort-amount-down"></i>
            {{ $t('admin.users.sort.descending') }}
          </DropdownItem>
        </template>
      </Dropdown>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, useTemplateRef } from 'vue'
import { Button, Searchbar } from '@owlint/feathers-vue'
import Dropdown from '@/components/ui/Dropdown.vue'
import DropdownItem from '@/components/ui/DropdownItem.vue'
import DropdownDivider from '@/components/ui/DropdownDivider.vue'
import type { AdminUserQueryParams } from '@/types/admin-user'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()

interface Props {
  search: string
  sort: AdminUserQueryParams['sort']
  order: AdminUserQueryParams['order']
}

const props = defineProps<Props>()

const searchbarRef = useTemplateRef('searchbar')

const emit = defineEmits<{
  'update:search': [value: string]
  'update:sort': [value: AdminUserQueryParams['sort']]
  'update:order': [value: AdminUserQueryParams['order']]
}>()

const sortLabel = computed(() => {
  const sortLabels: Record<AdminUserQueryParams['sort'], string> = {
    username: t('admin.users.sort.username'),
    created_at: t('admin.users.sort.createdDate'),
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
