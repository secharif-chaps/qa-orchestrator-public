<template>
  <div class="min-h-screen p-6">
    <!-- Header -->
    <div class="max-w-5xl mx-auto mb-6">
      <div class="flex items-center gap-4 mb-2">
        <h1 class="text-headline-3xl font-bold">
          {{ $t('tokens.history.title', 'Token History') }}
        </h1>
      </div>
      <p class="text-sm text-gray-600 dark:text-gray-400">
        {{ $t('tokens.history.subtitle', 'View all token transactions for your organization') }}
      </p>
    </div>

    <!-- Total Credits Card -->
    <div class="max-w-5xl mx-auto mb-6">
      <div
        class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 p-6"
      >
        <div class="flex items-center justify-between">
          <div>
            <h2 class="text-sm font-semibold text-gray-600 dark:text-gray-400 uppercase mb-1">
              {{ $t('tokens.history.totalCredits', 'Total Available Credits') }}
            </h2>
            <p class="text-3xl font-bold text-sage-600 dark:text-sage-400">
              {{ totalTokens.toLocaleString() }}
            </p>
          </div>
          <Tag
            :variant="totalTokens > 0 ? 'success' : 'warning'"
            :label="totalTokens > 0 ? $t('tokens.history.active', 'Active') : $t('tokens.history.empty', 'Empty')"
            size="lg"
            rounded
            :icon="totalTokens > 0 ? 'fa fa-check-circle' : 'fa fa-exclamation-circle'"
          />
        </div>
      </div>
    </div>

    <!-- Filters Section -->
    <div class="max-w-5xl mx-auto mb-6">
      <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4">
        <div class="flex flex-wrap items-end gap-4">
          <!-- Transaction Type Filter -->
          <div class="flex flex-col gap-2">
            <label class="text-sm font-medium text-secondary">
              {{ $t('tokens.history.transactionType', 'Transaction Type') }}
            </label>
            <select
              v-model="filters.transaction_type"
              class="px-3 py-2 bg-base-200 border border-primary-stroke rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary min-w-40"
            >
              <option value="">{{ $t('tokens.history.allTypes', 'All Types') }}</option>
              <option value="add">{{ $t('tokens.history.type.add', 'Add') }}</option>
              <option value="consume">{{ $t('tokens.history.type.consume', 'Consume') }}</option>
              <option value="adjustment">{{ $t('tokens.history.type.adjustment', 'Adjustment') }}</option>
            </select>
          </div>

          <!-- Reference Type Filter -->
          <div class="flex flex-col gap-2">
            <label class="text-sm font-medium text-secondary">
              {{ $t('tokens.history.referenceType', 'Reference Type') }}
            </label>
            <select
              v-model="filters.reference_type"
              class="px-3 py-2 bg-base-200 border border-primary-stroke rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary min-w-40"
            >
              <option value="">{{ $t('tokens.history.allReferences', 'All References') }}</option>
              <option value="company">{{ $t('tokens.history.ref.company', 'Company') }}</option>
              <option value="manual">{{ $t('tokens.history.ref.manual', 'Manual') }}</option>
              <option value="csv_import">{{ $t('tokens.history.ref.csv_import', 'CSV Import') }}</option>
              <option value="system">{{ $t('tokens.history.ref.system', 'System') }}</option>
            </select>
          </div>

          <!-- Date Range (simplified) -->
          <div class="flex flex-col gap-2">
            <label class="text-sm font-medium text-secondary">
              {{ $t('tokens.history.dateFrom', 'Date From') }}
            </label>
            <input
              v-model="filters.date_from"
              type="date"
              class="px-3 py-2 bg-base-200 border border-primary-stroke rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary"
            />
          </div>

          <div class="flex flex-col gap-2">
            <label class="text-sm font-medium text-secondary">
              {{ $t('tokens.history.dateTo', 'Date To') }}
            </label>
            <input
              v-model="filters.date_to"
              type="date"
              class="px-3 py-2 bg-base-200 border border-primary-stroke rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary"
            />
          </div>

          <!-- Clear Filters Button -->
          <Button
            variant="tertiary"
            size="sm"
            icon="fa fa-times"
            :label="$t('tokens.history.clearFilters', 'Clear Filters')"
            @click="clearFilters"
          />
        </div>
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="isLoading" class="flex items-center justify-center py-16">
      <i class="fa fa-spinner fa-spin text-4xl text-sage-500"></i>
    </div>

    <!-- Error State -->
    <div v-else-if="error" class="max-w-5xl mx-auto">
      <Alert
        variant="danger"
        title="Unable to load token history"
        :description="errorMessage"
        icon="fa-exclamation-triangle"
      />
    </div>

    <!-- Token History Table -->
    <div v-else class="max-w-5xl mx-auto">
      <!-- Empty State -->
      <div
        v-if="!historyData?.items?.length"
        class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 p-12 text-center"
      >
        <div
          class="w-20 h-20 mx-auto mb-4 rounded-full bg-gray-100 dark:bg-gray-700 flex items-center justify-center"
        >
          <i class="fa fa-coins text-4xl text-gray-400 dark:text-gray-500"></i>
        </div>
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">
          {{ $t('tokens.history.noHistory', 'No token history yet') }}
        </h3>
        <p class="text-sm text-gray-600 dark:text-gray-400 max-w-md mx-auto">
          {{
            $t(
              'tokens.history.noHistoryDesc',
              'Token transactions will appear here when tokens are added or consumed.',
            )
          }}
        </p>
      </div>

      <!-- Transaction Table -->
      <div v-else class="flex flex-col gap-4">
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
          <table class="w-full">
            <thead class="bg-base-200">
              <tr>
                <th class="text-left px-4 py-3 text-sm font-semibold text-secondary">
                  {{ $t('tokens.history.table.date', 'Date') }}
                </th>
                <th class="text-left px-4 py-3 text-sm font-semibold text-secondary">
                  {{ $t('tokens.history.table.type', 'Type') }}
                </th>
                <th class="text-right px-4 py-3 text-sm font-semibold text-secondary">
                  {{ $t('tokens.history.table.amount', 'Amount') }}
                </th>
                <th class="text-right px-4 py-3 text-sm font-semibold text-secondary">
                  {{ $t('tokens.history.table.balanceAfter', 'Balance After') }}
                </th>
                <th class="text-left px-4 py-3 text-sm font-semibold text-secondary">
                  {{ $t('tokens.history.table.reference', 'Reference') }}
                </th>
                <th class="text-left px-4 py-3 text-sm font-semibold text-secondary">
                  {{ $t('tokens.history.table.user', 'User') }}
                </th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
              <tr
                v-for="transaction in historyData?.items ?? []"
                :key="transaction.id"
                class="hover:bg-base-100 transition-colors"
              >
                <td class="px-4 py-3 text-sm">
                  {{ formatDateTime(transaction.created_at) }}
                </td>
                <td class="px-4 py-3">
                  <Tag
                    :variant="getTransactionTypeVariant(transaction.transaction_type)"
                    :label="getTransactionTypeLabel(transaction.transaction_type)"
                    size="xs"
                  />
                </td>
                <td class="px-4 py-3 text-right">
                  <span
                    class="font-semibold"
                    :class="transaction.amount > 0 ? 'text-success' : 'text-error'"
                  >
                    {{ transaction.amount > 0 ? '+' : '' }}{{ transaction.amount.toLocaleString() }}
                  </span>
                </td>
                <td class="px-4 py-3 text-right text-sm text-secondary">
                  {{ transaction.balance_after.toLocaleString() }}
                </td>
                <td class="px-4 py-3 text-sm">
                  <div class="flex items-center gap-2">
                    <i :class="getReferenceTypeIcon(transaction.reference_type)" class="text-secondary"></i>
                    <span class="capitalize">{{ transaction.reference_type.replace('_', ' ') }}</span>
                    <span v-if="transaction.reference_id" class="text-xs text-secondary">
                      ({{ truncateId(transaction.reference_id) }})
                    </span>
                  </div>
                </td>
                <td class="px-4 py-3 text-sm text-secondary">
                  {{ truncateId(transaction.created_by) || '-' }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <Pagination
          v-if="(historyData?.pages ?? 0) > 1"
          v-model:current-page="currentPage"
          :meta="paginationMeta"
          :page-size-options="pageSizeOptions"
          item-name="transactions"
          @update-per-page="updatePageSize"
        />
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, reactive } from 'vue'
import { useQuery } from '@pinia/colada'
import { useI18n } from 'vue-i18n'
import { Alert, Button } from '@owlint/feathers-vue'
import { organizationBalanceQuery, tokenHistoryQuery } from '@/queries/tokens'
import { currentOrganizationQuery } from '@/queries/organization'
import Tag from '@/components/ui/Tag.vue'
import Pagination from '@/components/ui/Pagination.vue'
import type { TransactionType, ReferenceType, TokenHistoryFilters } from '@/types/tokens'
import type { PaginationMeta } from '@/types/pagination'
import type { BadgeVariant } from '@/components/ui/Tag.vue'

const { t, locale } = useI18n()

// Filter state
const filters = reactive<TokenHistoryFilters>({
  transaction_type: undefined,
  reference_type: undefined,
  date_from: undefined,
  date_to: undefined,
  page: 1,
  size: 10,
})

const pageSizeOptions = [10, 25, 50]

// Get current organization
const { data: currentOrganization, isLoading: isLoadingOrg } = useQuery(
  currentOrganizationQuery,
  () => ({}),
)

// Fetch global token balance using the spread pattern
const { data: balanceData, isLoading: isLoadingBalance } = useQuery({
  ...organizationBalanceQuery({ organizationId: currentOrganization.value?.id ?? '' }),
  enabled: () => !!currentOrganization.value?.id,
})

// Fetch token history with filters using the spread pattern
const { data: historyData, isLoading: isLoadingHistory, error } = useQuery({
  ...tokenHistoryQuery({
    organizationId: currentOrganization.value?.id ?? '',
    filters: {
      ...filters,
      transaction_type: filters.transaction_type || undefined,
      reference_type: filters.reference_type || undefined,
      date_from: filters.date_from || undefined,
      date_to: filters.date_to || undefined,
    },
  }),
  enabled: () => !!currentOrganization.value?.id,
})

// Computed values
const isLoading = computed(
  () => isLoadingOrg.value || isLoadingBalance.value || isLoadingHistory.value
)

const totalTokens = computed(() => balanceData.value?.balance ?? 0)

// Extract error message safely
const errorMessage = computed(() => {
  const err = error.value
  if (!err) return 'There was a problem loading the token history. Please try again later.'
  if (typeof err === 'string') return err
  if (err instanceof Error) return err.message
  if (typeof err === 'object' && 'message' in err) return String((err as { message: unknown }).message)
  return 'There was a problem loading the token history. Please try again later.'
})

const currentPage = computed({
  get: () => filters.page ?? 1,
  set: (value: number) => {
    filters.page = value
  },
})

const paginationMeta = computed<PaginationMeta | null>(() => {
  if (!historyData.value) return null

  return {
    current_page: historyData.value.page,
    per_page: historyData.value.size,
    total: historyData.value.total,
    last_page: historyData.value.pages,
    from: (historyData.value.page - 1) * historyData.value.size + 1,
    to: Math.min(historyData.value.page * historyData.value.size, historyData.value.total),
  }
})

// Helper functions
function formatDateTime(dateString: string | null | undefined): string {
  if (!dateString) return '-'

  const date = new Date(dateString)
  const localeCode = locale.value === 'fr-FR' ? 'fr-FR' : 'en-US'
  return date.toLocaleDateString(localeCode, {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}

function getTransactionTypeVariant(type: TransactionType): BadgeVariant {
  const variants: Record<TransactionType, BadgeVariant> = {
    add: 'success',
    consume: 'error',
    adjustment: 'info',
  }
  return variants[type] || 'slate'
}

function getTransactionTypeLabel(type: TransactionType): string {
  const labels: Record<TransactionType, string> = {
    add: t('tokens.history.type.add', 'Add'),
    consume: t('tokens.history.type.consume', 'Consume'),
    adjustment: t('tokens.history.type.adjustment', 'Adjustment'),
  }
  return labels[type] || type
}

function getReferenceTypeIcon(type: ReferenceType): string {
  const icons: Record<ReferenceType, string> = {
    company: 'fa fa-building',
    csv_import: 'fa fa-file-csv',
    manual: 'fa fa-user',
    system: 'fa fa-cog',
  }
  return icons[type] || 'fa fa-circle'
}

function truncateId(id: string | null): string {
  if (!id) return ''
  if (id.length <= 12) return id
  return `${id.slice(0, 8)}...`
}

function clearFilters() {
  filters.transaction_type = undefined
  filters.reference_type = undefined
  filters.date_from = undefined
  filters.date_to = undefined
  filters.page = 1
}

function updatePageSize(size: number) {
  filters.size = size
  filters.page = 1
}
</script>

<route lang="yaml">
meta:
  requiresAuth: true
  title: 'Token History'
  permissions:
    - organization.read
</route>
