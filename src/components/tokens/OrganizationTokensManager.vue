<template>
  <Card>
    <!-- Header -->
    <div class="flex items-center justify-between mb-6">
      <div>
        <h2 class="text-xl font-semibold">
          {{ $t('tokens.management', 'Token Management') }}
        </h2>
        <p class="text-secondary mt-1">
          {{ $t('tokens.managementDescription', 'Manage your organization token balance') }}
        </p>
      </div>

      <div class="flex items-center gap-2">
        <Button
          variant="tertiary"
          icon="fa fa-refresh"
          :loading="isRefreshing"
          :disabled="isRefreshing"
          :title="$t('tokens.refresh', 'Refresh token balance')"
          icon-only
          @click="handleRefresh"
        />

        <Button
          variant="secondary"
          icon="fa fa-history"
          :label="$t('tokens.viewHistory', 'View History')"
          @click="$router.push('/tokens/history')"
        />
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="isLoading" class="text-center p-8">
      <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary mx-auto mb-4"></div>
      <p class="text-secondary">
        {{ $t('tokens.loading', 'Loading token balance...') }}
      </p>
    </div>

    <!-- Error State -->
    <Alert
      v-else-if="error"
      variant="danger"
      title="Error"
      :description="errorMessage"
      icon="fa-exclamation-circle"
    />

    <!-- Token Balance Display -->
    <div v-else class="flex flex-col gap-6">
      <!-- Global Balance Card -->
      <div class="bg-gradient-to-br from-primary/5 to-primary/10 rounded-xl p-6 border border-primary-stroke">
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-4">
            <div class="w-14 h-14 bg-primary/20 rounded-xl flex items-center justify-center">
              <i class="fa fa-coins text-2xl text-primary"></i>
            </div>
            <div>
              <p class="text-sm text-secondary mb-1">
                {{ $t('tokens.globalBalance', 'Organization Token Balance') }}
              </p>
              <div class="flex items-baseline gap-2">
                <span class="text-4xl font-bold text-primary">
                  {{ balance.toLocaleString() }}
                </span>
                <span class="text-secondary">
                  {{ $t('tokens.credits', 'credits') }}
                </span>
              </div>
            </div>
          </div>

          <div class="text-right">
            <p class="text-sm text-secondary mb-1">
              {{ $t('tokens.companyEquivalent', 'Company Equivalent') }}
            </p>
            <div class="flex items-baseline gap-1 justify-end">
              <span class="text-2xl font-semibold" :class="companyEquivalentColor">
                {{ companyEquivalent }}
              </span>
              <span class="text-secondary">
                {{ companyEquivalent === 1 ? $t('tokens.company', 'company') : $t('tokens.companies', 'companies') }}
              </span>
            </div>
          </div>
        </div>
      </div>

      <!-- Add Tokens Section -->
      <div class="flex flex-col gap-4" data-testid="quick-add-section">
        <h3 class="text-lg font-medium">
          {{ $t('tokens.addTokens', 'Add Tokens') }}
        </h3>

        <!-- Quick Add Buttons -->
        <div class="flex flex-col gap-3">
          <label class="text-sm font-medium text-secondary">
            {{ $t('tokens.quickAdd', 'Quick Add (by company count)') }}
          </label>
          <div class="flex flex-wrap items-center gap-2">
            <Button
              v-for="amount in quickAddAmounts"
              :key="amount.companies"
              variant="secondary"
              size="sm"
              :loading="addTokensMutation.isLoading.value && pendingAmount === amount.tokens"
              :disabled="addTokensMutation.isLoading.value"
              :label="`${amount.companies} screens (${amount.tokens.toLocaleString()})`"
              @click="handleQuickAdd(amount.tokens)"
            />
          </div>
        </div>

        <!-- Custom Amount Input -->
        <div class="flex flex-col gap-2">
          <label class="text-sm font-medium text-secondary">
            {{ $t('tokens.customAmount', 'Custom Amount') }}
          </label>
          <div class="flex items-center gap-3">
            <Input
              id="custom-token-amount"
              v-model="customAmount"
              type="number"
              :placeholder="$t('tokens.enterAmount', 'Enter token amount...')"
              :min="1"
              :max="100000"
              :disabled="addTokensMutation.isLoading.value"
              class="max-w-xs"
            />
            <Button
              variant="primary"
              icon="fa fa-plus"
              :label="$t('tokens.add', 'Add')"
              :loading="addTokensMutation.isLoading.value && pendingAmount === Number(customAmount)"
              :disabled="!canAddCustomAmount"
              @click="handleCustomAdd"
            />
          </div>
          <p class="text-xs text-secondary">
            {{ $t('tokens.addHelper', 'Enter the number of tokens to add, or use quick-add buttons above.') }}
          </p>
        </div>
      </div>

      <!-- Module Status Section -->
      <div class="flex flex-col gap-4 pt-4 border-t border-primary-stroke">
        <h3 class="text-lg font-medium">
          {{ $t('tokens.moduleStatus', 'Module Status') }}
        </h3>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <ModuleStatusCard
            v-for="module in modules"
            :key="module.name"
            :module="module.name"
            :is-enabled="module.enabled"
            :organization-id="organizationId"
            @refresh="handleRefresh"
          />
        </div>
      </div>
    </div>
  </Card>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { useQuery } from '@pinia/colada'
import { Alert, Button, Input } from '@owlint/feathers-vue'
import Card from '../ui/Card.vue'
import ModuleStatusCard from './ModuleStatusCard.vue'
import { organizationBalanceQuery, organizationModulesQuery } from '@/queries/tokens'
import { useAddGlobalTokens } from '@/mutations/tokens'

// Token cost per company creation
const TOKENS_PER_COMPANY = 35

interface Props {
  organizationId: string
}

const props = defineProps<Props>()

// State
const isRefreshing = ref(false)
const customAmount = ref<number | string>('')
const pendingAmount = ref<number | null>(null)

// Query for global token balance
const {
  data: balanceData,
  isLoading: isLoadingBalance,
  error: balanceError,
  refetch: refetchBalance,
} = useQuery({
  ...organizationBalanceQuery({ organizationId: props.organizationId }),
  enabled: () => !!props.organizationId,
})

// Query for module configurations
const {
  data: modulesData,
  isLoading: isLoadingModules,
  error: modulesError,
  refetch: refetchModules,
} = useQuery({
  ...organizationModulesQuery({ organizationId: props.organizationId }),
  enabled: () => !!props.organizationId,
})

// Mutation for adding tokens
const addTokensMutation = useAddGlobalTokens()

// Computed values
const isLoading = computed(() => isLoadingBalance.value || isLoadingModules.value)
const error = computed(() => balanceError.value || modulesError.value)

// Extract error message safely
const errorMessage = computed(() => {
  const err = error.value
  if (!err) return ''
  if (typeof err === 'string') return err
  if (err instanceof Error) return err.message
  if (typeof err === 'object' && 'message' in err) return String((err as { message: unknown }).message)
  return 'An error occurred'
})

const balance = computed(() => balanceData.value?.balance ?? 0)
const companyEquivalent = computed(() => Math.floor(balance.value / TOKENS_PER_COMPANY))

const modules = computed(() => modulesData.value?.modules ?? [])

const companyEquivalentColor = computed(() => {
  if (companyEquivalent.value === 0) return 'text-error'
  if (companyEquivalent.value < 5) return 'text-warning'
  return 'text-success'
})

// Quick add amounts: 5, 10, 25, 50, 100 companies
const quickAddAmounts = computed(() => [
  { companies: 5, tokens: 5 * TOKENS_PER_COMPANY },
  { companies: 10, tokens: 10 * TOKENS_PER_COMPANY },
  { companies: 25, tokens: 25 * TOKENS_PER_COMPANY },
  { companies: 50, tokens: 50 * TOKENS_PER_COMPANY },
  { companies: 100, tokens: 100 * TOKENS_PER_COMPANY },
])

const canAddCustomAmount = computed(() => {
  const amount = Number(customAmount.value)
  return amount > 0 && amount <= 100000 && !addTokensMutation.isLoading.value
})

// Methods
async function handleRefresh() {
  isRefreshing.value = true
  try {
    await Promise.all([refetchBalance(), refetchModules()])
    // Small delay for visual feedback
    await new Promise((resolve) => setTimeout(resolve, 300))
  } finally {
    isRefreshing.value = false
  }
}

async function handleQuickAdd(amount: number) {
  pendingAmount.value = amount
  try {
    addTokensMutation.organizationId.value = props.organizationId
    addTokensMutation.amount.value = amount
    await addTokensMutation.addTokens()
  } finally {
    pendingAmount.value = null
  }
}

async function handleCustomAdd() {
  const amount = Number(customAmount.value)
  if (!canAddCustomAmount.value) return

  pendingAmount.value = amount
  try {
    addTokensMutation.organizationId.value = props.organizationId
    addTokensMutation.amount.value = amount
    await addTokensMutation.addTokens()
    customAmount.value = ''
  } finally {
    pendingAmount.value = null
  }
}
</script>
