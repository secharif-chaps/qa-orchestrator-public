<template>
  <Card>
    <!-- Header -->
    <div class="mb-6 flex items-center justify-between">
      <div>
        <h2 class="text-xl font-semibold">
          {{ $t('settings.tokens.management', 'Token Management') }}
        </h2>
        <p class="text-secondary mt-1">
          {{
            $t('settings.tokens.managementDescription', 'Manage your organization token balance')
          }}
        </p>
      </div>

      <div class="flex items-center gap-2">
        <Button
          variant="tertiary"
          icon="fa fa-refresh"
          :loading="isRefreshing"
          :disabled="isRefreshing"
          :title="$t('settings.tokens.refresh', 'Refresh token balance')"
          icon-only
          @click="handleRefresh"
        />

        <Button
          variant="secondary"
          icon="fa fa-history"
          :label="$t('settings.tokens.viewHistory', 'View History')"
          @click="$router.push('/tokens/history')"
        />
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="isLoading" class="p-8 text-center">
      <div class="border-primary mx-auto mb-4 h-8 w-8 animate-spin rounded-full border-b-2"></div>
      <p class="text-secondary">
        {{ $t('settings.tokens.loading', 'Loading token balance...') }}
      </p>
    </div>

    <!-- Error State -->
    <Alert
      v-else-if="error"
      variant="danger"
      :title="t('settings.tokens.errorTitle')"
      :description="errorMessage"
      icon="fa-exclamation-circle"
    />

    <!-- Token Balance Display -->
    <div v-else class="flex flex-col gap-6">
      <!-- Global Balance Card -->
      <div
        class="from-primary/5 to-primary/10 border-primary-stroke rounded-xl border bg-gradient-to-br p-6"
      >
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-4">
            <div class="bg-primary/20 flex h-14 w-14 items-center justify-center rounded-xl">
              <i class="fa fa-coins text-primary text-2xl"></i>
            </div>
            <div>
              <p class="text-secondary mb-1 text-sm">
                {{ $t('settings.tokens.globalBalance', 'Organization Token Balance') }}
              </p>
              <div class="flex items-baseline gap-2">
                <span class="text-primary text-4xl font-bold">
                  {{ balance.toLocaleString() }}
                </span>
                <span class="text-secondary">
                  {{ $t('settings.tokens.credits', 'credits') }}
                </span>
              </div>
            </div>
          </div>

          <div class="text-right">
            <p class="text-secondary mb-1 text-sm">
              {{ $t('settings.tokens.companyEquivalent', 'Company Equivalent') }}
            </p>
            <div class="flex items-baseline justify-end gap-1">
              <span class="text-2xl font-semibold" :class="companyEquivalentColor">
                {{ companyEquivalent }}
              </span>
              <span class="text-secondary">
                {{
                  companyEquivalent === 1
                    ? $t('settings.tokens.company', 'company')
                    : $t('settings.tokens.companies', 'companies')
                }}
              </span>
            </div>
          </div>
        </div>
      </div>

      <!-- Add Tokens Section -->
      <div class="flex flex-col gap-4" data-testid="quick-add-section">
        <h3 class="text-lg font-medium">
          {{ $t('settings.tokens.addTokens', 'Add Tokens') }}
        </h3>

        <!-- Quick Add Buttons -->
        <div class="flex flex-col gap-3">
          <label class="text-secondary text-sm font-medium">
            {{ $t('settings.tokens.quickAdd', 'Quick Add (by company count)') }}
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
          <label class="text-secondary text-sm font-medium">
            {{ $t('settings.tokens.customAmount', 'Custom Amount') }}
          </label>
          <div class="flex items-center gap-3">
            <Input
              id="custom-token-amount"
              v-model="customAmount"
              type="number"
              :placeholder="$t('settings.tokens.enterAmount', 'Enter token amount...')"
              :min="1"
              :max="100000"
              :disabled="addTokensMutation.isLoading.value"
              class="max-w-xs"
            />
            <Button
              variant="primary"
              icon="fa fa-plus"
              :label="$t('settings.tokens.add', 'Add')"
              :loading="addTokensMutation.isLoading.value && pendingAmount === Number(customAmount)"
              :disabled="!canAddCustomAmount"
              @click="handleCustomAdd"
            />
          </div>
          <p class="text-secondary text-xs">
            {{
              $t(
                'settings.tokens.addHelper',
                'Enter the number of tokens to add, or use quick-add buttons above.',
              )
            }}
          </p>
        </div>
      </div>

      <!-- Module Status Section -->
      <div class="border-primary-stroke flex flex-col gap-4 border-t pt-4">
        <h3 class="text-lg font-medium">
          {{ $t('settings.tokens.moduleStatus', 'Module Status') }}
        </h3>

        <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
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
import { useI18n } from 'vue-i18n'

const { t } = useI18n()
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
  if (typeof err === 'object' && 'message' in err)
    return String((err as { message: unknown }).message)
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
