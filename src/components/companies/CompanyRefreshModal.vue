<template>
  <Modal
    v-model:display-modal="showRefreshModal"
    :title="t('company.refresh.title')"
    icon="fa fa-refresh"
    size="lg"
    color=""
  >
    <template #description>
      <div class="flex flex-col gap-4">
        <p class="text-secondary">
          {{ t('company.refresh.subtitle') }}
        </p>

        <p class="text-sm text-secondary">
          {{ t('company.refresh.warning.message') }}
        </p>

        <!-- Token Consumption Notice -->
        <div class="bg-warning-light text-warning-light-content border border-warning-stroke rounded-lg p-4">
          <div class="flex items-center gap-3">
            <i class="fa fa-warning text-xl"></i>
            <div class="flex flex-col gap-1">
              <div class="font-bold text-base">{{ t('company.refresh.consumptionNotice') }}</div>
            </div>
          </div>
        </div>

        <!-- Token Information -->
        <div class="bg-base-200 rounded-lg p-4">
          <div class="flex flex-col gap-3">
            <h4 class="font-medium text-base">
              {{ t('company.refresh.tokens.title') }}
            </h4>

            <div class="flex flex-col gap-2 text-sm">
              <div class="flex justify-between">
                <span class="text-secondary">{{ t('company.refresh.tokens.current') }}:</span>
                <span :class="tokenBalanceColor">{{ tokenBalance }}</span>
              </div>
              <div class="flex justify-between">
                <span class="text-secondary">{{ t('company.refresh.tokens.cost') }}:</span>
                <span class="text-error">35</span>
              </div>
              <div class="flex justify-between">
                <span class="text-secondary">{{ t('company.refresh.tokens.remaining') }}:</span>
                <span :class="remainingTokensColor">{{ remainingTokens }}</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Company Details -->
        <div v-if="company" class="bg-base-200 rounded-lg p-4">
          <div class="flex flex-col gap-3">
            <h4 class="font-medium text-base">
              {{ t('company.refresh.details') }}
            </h4>
            <div class="flex flex-col gap-2 text-sm">
              <div class="flex justify-between">
                <span class="text-secondary">{{ t('company.name') }}:</span>
                <span class="font-medium">{{ company.name }}</span>
              </div>
              <div v-if="company.website" class="flex justify-between">
                <span class="text-secondary">{{ t('company.website') }}:</span>
                <span class="text-xs">{{ company.website }}</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </template>

    <template #footer>
      <Button
        variant="primary"
        icon="fa fa-refresh"
        :label="t('company.refresh.confirm.button')"
        :loading="isLoading"
        :disabled="isLoading || !hasEnoughTokens"
        @click="handleRefresh"
      />
      <Button variant="tertiary" :label="t('common.cancel')" @click="showRefreshModal = false" />
    </template>
  </Modal>
</template>

<script setup lang="ts">
import { Button, Modal } from '@owlint/feathers-vue'
import { useI18n } from 'vue-i18n'
import { computed } from 'vue'
import type { Company } from '@/types/company'
import { useRefreshCompany } from '@/mutations/companies'
import { useQuery } from '@pinia/colada'
import { organizationBalanceQuery } from '@/queries/tokens'
import { currentOrganizationQuery } from '@/queries/organization'

const { t } = useI18n()

interface Props {
  company?: Company | null
}

const props = defineProps<Props>()

const showRefreshModal = defineModel<boolean>({
  required: true,
  default: false,
})

const emit = defineEmits<{
  'refresh-company': []
}>()

// Use mutation for refreshing with cache invalidation
const { refreshCompany, isLoading } = useRefreshCompany()

// Get current organization
const { data: currentOrganization } = useQuery(currentOrganizationQuery, () => ({}))

// Get token balance
const { data: tokenBalanceData } = useQuery({
  ...organizationBalanceQuery({ organizationId: currentOrganization.value?.id ?? '' }),
  enabled: () => !!currentOrganization.value?.id,
})

// Computed properties
const tokenBalance = computed(() => tokenBalanceData.value?.balance ?? 0)
const remainingTokens = computed(() => Math.max(0, tokenBalance.value - 35))
const hasEnoughTokens = computed(() => tokenBalance.value >= 35)

const tokenBalanceColor = computed(() => {
  if (tokenBalance.value >= 35) return 'text-success'
  return 'text-error'
})

const remainingTokensColor = computed(() => {
  if (remainingTokens.value >= 0) return 'text-success'
  return 'text-error'
})

const handleRefresh = async () => {
  if (!props.company?.id) return

  try {
    await refreshCompany({
      companyId: props.company.id.toString(),
      companyName: props.company.name,
    })

    // Emit event for parent
    emit('refresh-company')

    // Close modal
    showRefreshModal.value = false
  } catch (error) {
    // Error toast is shown by the mutation's onError handler
    console.error('Error refreshing company:', error)
  }
}
</script>