<template>
  <div class="min-h-screen p-6">
    <!-- Header -->
    <div class="mx-auto mb-6 max-w-5xl">
      <div class="mb-2 flex items-center gap-4">
        <h1 class="text-headline-3xl font-bold">
          {{ $t('settings.tokens.history.title') }}
        </h1>
      </div>
      <p class="text-sm text-gray-600 dark:text-gray-400">
        {{ $t('settings.tokens.history.subtitle') }}
      </p>
    </div>

    <!-- Total Credits Card -->
    <div class="mx-auto mb-6 max-w-5xl">
      <div
        class="rounded-lg border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-800"
      >
        <div class="flex items-center justify-between">
          <div>
            <h2 class="mb-1 text-sm font-semibold text-gray-600 uppercase dark:text-gray-400">
              {{ $t('settings.tokens.history.totalCredits') }}
            </h2>
            <p class="text-sage-600 dark:text-sage-400 text-3xl font-bold">
              {{ totalTokens.toLocaleString() }}
            </p>
          </div>
          <Tag
            :intent="totalTokens > 0 ? 'success' : 'warning'"
            :label="
              totalTokens > 0
                ? $t('settings.tokens.history.active')
                : $t('settings.tokens.history.empty')
            "
            size="md"
            class="rounded-full"
            :icon="totalTokens > 0 ? 'fa-check-circle' : 'fa-exclamation-circle'"
          />
        </div>
      </div>
    </div>

    <!-- Filters Section -->
    <div class="mx-auto mb-6 max-w-5xl">
      <div
        class="rounded-md border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800"
      >
        <div class="flex flex-wrap items-end gap-4">
          <!-- Transaction Type Filter -->
          <div class="flex flex-col gap-2">
            <label class="text-neutral-black-font text-sm font-medium">
              {{ $t('settings.tokens.history.transactionType') }}
            </label>
            <Select
              v-model="filters.transaction_type"
              :options="transactionTypeOptions"
              :placeholder="$t('settings.tokens.history.allTypes')"
              class="min-w-40"
            >
              <template #items="{ options }">
                <SelectItem v-for="opt in options" :key="opt.value" :value="opt.value">
                  {{ opt.label }}
                </SelectItem>
              </template>
            </Select>
          </div>

          <!-- Reference Type Filter -->
          <div class="flex flex-col gap-2">
            <label class="text-neutral-black-font text-sm font-medium">
              {{ $t('settings.tokens.history.referenceType') }}
            </label>
            <Select
              v-model="filters.reference_type"
              :options="referenceTypeOptions"
              :placeholder="$t('settings.tokens.history.allReferences')"
              class="min-w-40"
            >
              <template #items="{ options }">
                <SelectItem v-for="opt in options" :key="opt.value" :value="opt.value">
                  {{ opt.label }}
                </SelectItem>
              </template>
            </Select>
          </div>

          <!-- Date Range (simplified) -->
          <div class="flex flex-col gap-2">
            <label class="text-neutral-black-font text-sm font-medium">
              {{ $t('settings.tokens.history.dateFrom') }}
            </label>
            <input
              v-model="filters.date_from"
              type="date"
              class="bg-primary-lightest border-primary-lighter-stroke focus:ring-primary/20 focus:border-primary rounded-sm border px-3 py-2 text-sm focus:ring-2 focus:outline-none"
            />
          </div>

          <div class="flex flex-col gap-2">
            <label class="text-neutral-black-font text-sm font-medium">
              {{ $t('settings.tokens.history.dateTo') }}
            </label>
            <input
              v-model="filters.date_to"
              type="date"
              class="bg-primary-lightest border-primary-lighter-stroke focus:ring-primary/20 focus:border-primary rounded-sm border px-3 py-2 text-sm focus:ring-2 focus:outline-none"
            />
          </div>

          <!-- Clear Filters Button -->
          <Button
            variant="tertiary"
            size="sm"
            icon="fa-times"
            :label="$t('settings.tokens.history.clearFilters')"
            @click="clearFilters"
          />
        </div>
      </div>
    </div>

    <!-- Loading State -->
    <TokensHistorySkeleton v-if="isLoading" />

    <!-- Error State -->
    <div v-else-if="error" class="mx-auto max-w-5xl">
      <Alert
        variant="danger"
        :title="$t('settings.tokens.history.errorTitle')"
        :description="errorMessage"
        icon="fa-exclamation-triangle"
      />
    </div>

    <!-- Token History Table -->
    <div v-else class="mx-auto max-w-5xl">
      <!-- Empty State -->
      <div
        v-if="!historyData?.items?.length"
        class="rounded-lg border border-gray-200 bg-white p-12 text-center dark:border-gray-700 dark:bg-gray-800"
      >
        <div
          class="mx-auto mb-4 flex h-20 w-20 items-center justify-center rounded-full bg-gray-100 dark:bg-gray-700"
        >
          <i class="fa fa-coins text-4xl text-gray-400 dark:text-gray-500"></i>
        </div>
        <h3 class="mb-2 text-lg font-semibold text-gray-900 dark:text-white">
          {{ $t('settings.tokens.history.noHistory') }}
        </h3>
        <p class="mx-auto max-w-112 text-sm text-gray-600 dark:text-gray-400">
          {{ $t('settings.tokens.history.noHistoryDesc') }}
        </p>
      </div>

      <!-- Transaction Table -->
      <div v-else class="flex flex-col gap-4">
        <div
          class="overflow-hidden rounded-md border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800"
        >
          <table class="w-full">
            <thead class="bg-primary-lightest">
              <tr>
                <th class="text-neutral-black-font px-4 py-3 text-left text-sm font-semibold">
                  {{ $t('settings.tokens.history.table.date') }}
                </th>
                <th class="text-neutral-black-font px-4 py-3 text-left text-sm font-semibold">
                  {{ $t('settings.tokens.history.table.type') }}
                </th>
                <th class="text-neutral-black-font px-4 py-3 text-right text-sm font-semibold">
                  {{ $t('settings.tokens.history.table.amount') }}
                </th>
                <th class="text-neutral-black-font px-4 py-3 text-right text-sm font-semibold">
                  {{ $t('settings.tokens.history.table.balanceAfter') }}
                </th>
                <th class="text-neutral-black-font px-4 py-3 text-left text-sm font-semibold">
                  {{ $t('settings.tokens.history.table.reference') }}
                </th>
                <th class="text-neutral-black-font px-4 py-3 text-left text-sm font-semibold">
                  {{ $t('settings.tokens.history.table.user') }}
                </th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
              <tr
                v-for="transaction in historyData?.items ?? []"
                :key="transaction.id"
                class="transition-colors hover:bg-white"
              >
                <td class="px-4 py-3 text-sm">
                  {{ formatDate(transaction.created_at, 'long') }}
                </td>
                <td class="px-4 py-3">
                  <Tag
                    :intent="getTransactionTypeVariant(transaction.transaction_type)"
                    :label="getTransactionTypeLabel(transaction.transaction_type)"
                    size="xs"
                  />
                </td>
                <td class="px-4 py-3 text-right">
                  <span
                    class="font-semibold"
                    :class="transaction.amount > 0 ? 'text-success' : 'text-error'"
                  >
                    {{
                      transaction.amount > 0
                        ? t('common.positiveAmount', { value: transaction.amount.toLocaleString() })
                        : transaction.amount.toLocaleString()
                    }}
                  </span>
                </td>
                <td class="text-neutral-black-font px-4 py-3 text-right text-sm">
                  {{ transaction.balance_after.toLocaleString() }}
                </td>
                <td class="px-4 py-3 text-sm">
                  <div class="flex items-center gap-2">
                    <i
                      :class="getReferenceTypeIcon(transaction.reference_type)"
                      class="text-neutral-black-font"
                    ></i>
                    <span class="capitalize">{{
                      transaction.reference_type.replace('_', ' ')
                    }}</span>
                    <span v-if="transaction.reference_id" class="text-neutral-black-font text-xs">{{
                      t('common.inParentheses', { value: truncateId(transaction.reference_id) })
                    }}</span>
                  </div>
                </td>
                <td class="text-neutral-black-font px-4 py-3 text-sm">
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
          :item-name="$t('settings.tokens.history.itemName')"
          @update-per-page="updatePageSize"
        />
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import TokensHistorySkeleton from '@/components/tokens/TokensHistorySkeleton.vue'
import Pagination from '@/components/ui/Pagination.vue'
import { useDateTime } from '@/composables/useDateTime'
import { currentOrganizationQuery } from '@/queries/organization'
import { organizationBalanceQuery, tokenHistoryQuery } from '@/queries/tokens'
import type { ReferenceType, TokenHistoryFilters, TransactionType } from '@/types/tokens'
import { transformToPaginationMeta } from '@/utils/pagination'
import { Alert, Button, Select, SelectItem, Tag, type Intent } from '@owlint/feathers-vue'
import { useQuery } from '@pinia/colada'
import { computed, reactive } from 'vue'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()
const { formatDate } = useDateTime()

const transactionTypeOptions = computed(() => [
  { value: '', label: t('settings.tokens.history.allTypes') },
  { value: 'add', label: t('settings.tokens.history.type.add') },
  { value: 'consume', label: t('settings.tokens.history.type.consume') },
  { value: 'adjustment', label: t('settings.tokens.history.type.adjustment') },
])

const referenceTypeOptions = computed(() => [
  { value: '', label: t('settings.tokens.history.allReferences') },
  { value: 'company', label: t('settings.tokens.history.ref.company') },
  { value: 'manual', label: t('settings.tokens.history.ref.manual') },
  { value: 'csv_import', label: t('settings.tokens.history.ref.csv_import') },
  { value: 'system', label: t('settings.tokens.history.ref.system') },
])

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
const { data: currentOrganization, isLoading: isLoadingOrg } = useQuery(() =>
  currentOrganizationQuery(),
)

// Fetch global token balance
const { data: balanceData, isLoading: isLoadingBalance } = useQuery(() =>
  organizationBalanceQuery({
    organizationId: currentOrganization.value?.id ?? '',
  }),
)

// Fetch token history with filters using the spread pattern
const {
  data: historyData,
  isLoading: isLoadingHistory,
  error,
} = useQuery({
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
  () => isLoadingOrg.value || isLoadingBalance.value || isLoadingHistory.value,
)

const totalTokens = computed(() => balanceData.value?.balance ?? 0)

// Extract error message safely
const errorMessage = computed(() => {
  const err = error.value
  if (!err) return 'There was a problem loading the token history. Please try again later.'
  if (typeof err === 'string') return err
  if (err instanceof Error) return err.message
  if (typeof err === 'object' && 'message' in err)
    return String((err as { message: unknown }).message)
  return 'There was a problem loading the token history. Please try again later.'
})

const currentPage = computed({
  get: () => filters.page ?? 1,
  set: (value: number) => {
    filters.page = value
  },
})

const paginationMeta = computed(() => transformToPaginationMeta(historyData.value))

function getTransactionTypeVariant(type: TransactionType): Intent {
  const intents: Record<TransactionType, Intent> = {
    add: 'success',
    consume: 'danger',
    adjustment: 'info',
  }
  return intents[type] || 'neutral'
}

function getTransactionTypeLabel(type: TransactionType): string {
  const labels: Record<TransactionType, string> = {
    add: t('settings.tokens.history.type.add'),
    consume: t('settings.tokens.history.type.consume'),
    adjustment: t('settings.tokens.history.type.adjustment'),
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
