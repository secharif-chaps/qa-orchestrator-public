<template>
  <Card padding="p-6">
    <!-- Header with filters -->
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
      <h3 class="text-lg font-semibold">
        {{ $t('credits.topUsers.title', 'Classement des utilisateurs') }}
      </h3>

      <div class="flex flex-wrap items-center gap-3">
        <!-- Module filter -->
        <CreditModuleFilter v-model="selectedModule" />

        <!-- Period filter -->
        <CreditDateFilter v-model:period="selectedPeriod" />

        <!-- Search -->
        <Searchbar
          id="top-users-search"
          v-model="searchQuery"
          :placeholder="$t('credits.topUsers.search', 'Rechercher...')"
          size="sm"
        />
      </div>
    </div>

    <!-- Loading state -->
    <div v-if="loading" class="flex justify-center py-12">
      <div class="text-center">
        <i class="fa fa-spinner text-secondary mb-2 animate-spin text-2xl"></i>
        <p class="text-secondary text-sm">{{ $t('common.loading', 'Chargement...') }}</p>
      </div>
    </div>

    <!-- Table -->
    <template v-else>
      <TopCreditUsersTable :users="users" />

      <!-- Pagination -->
      <Pagination
        v-if="paginationMeta"
        v-model:current-page="currentPage"
        :meta="paginationMeta"
        :item-name="$t('credits.topUsers.itemName', 'utilisateurs')"
        @update-per-page="$emit('updatePerPage', $event)"
      />
    </template>
  </Card>
</template>

<script setup lang="ts">
/**
 * Top credit users card with filters, table, and pagination.
 */
import { Searchbar } from '@owlint/feathers-vue'
import type { TopCreditUser } from '@/types/credits'
import type { PaginationMeta } from '@/types/pagination'
import Card from '@/components/ui/Card.vue'
import CreditModuleFilter from './CreditModuleFilter.vue'
import CreditDateFilter from './CreditDateFilter.vue'
import TopCreditUsersTable from './TopCreditUsersTable.vue'
import Pagination from '@/components/ui/Pagination.vue'

interface Props {
  users: TopCreditUser[]
  loading?: boolean
  paginationMeta?: PaginationMeta | null
}

withDefaults(defineProps<Props>(), {
  loading: false,
  paginationMeta: null,
})

defineEmits<{
  updatePerPage: [value: number]
}>()

const selectedModule = defineModel<string>('module', { default: 'all' })
const selectedPeriod = defineModel<string>('period', { default: '30d' })
const searchQuery = defineModel<string>('search', { default: '' })
const currentPage = defineModel<number>('page', { default: 1 })
</script>
