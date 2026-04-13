<template>
  <Modal
    v-model:display-modal="showRefreshModal"
    :title="t('screen.company.refresh.title')"
    icon="fa fa-refresh"
    size="md"
    color=""
  >
    <template #description>
      <div class="flex flex-col gap-4">
        <p class="text-neutral-black-font">
          {{ t('screen.company.refresh.subtitle', { name: company?.name ?? '' }) }}
        </p>

        <p class="text-neutral-black-font text-sm">
          {{ t('screen.company.refresh.warning.message') }}
        </p>

        <!-- Token Consumption Notice with remaining tokens badge -->
        <div
          class="bg-warning-light text-warning-light-content border-warning-stroke rounded-sm border p-4"
        >
          <div class="flex items-center justify-between gap-3">
            <div class="flex items-center gap-3">
              <i class="fa fa-warning text-xl"></i>
              <div class="font-medium">{{ t('screen.company.refresh.consumptionNotice') }}</div>
            </div>
            <span
              class="inline-flex items-center gap-1.5 rounded-full bg-white px-2.5 py-1 text-sm font-medium"
              :class="remainingTokensColor"
            >
              <i class="fa fa-coins text-xs"></i>
              {{ remainingTokens }}
            </span>
          </div>
        </div>
      </div>
    </template>

    <template #footer>
      <Button
        variant="primary"
        icon="fa fa-refresh"
        :label="t('screen.company.refresh.confirm.button')"
        :loading="isLoading"
        :disabled="isLoading || !hasEnoughTokens"
        @click="handleRefresh"
      />
      <Button variant="tertiary" :label="t('common.cancel')" @click="showRefreshModal = false" />
    </template>
  </Modal>
</template>

<script setup lang="ts">
import { useRefreshCompany } from '@/mutations/companies'
import { currentOrganizationQuery } from '@/queries/organization'
import { organizationBalanceQuery } from '@/queries/tokens'
import type { Company } from '@/types/company'
import { Button, Modal } from '@owlint/feathers-vue'
import { useQuery } from '@pinia/colada'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

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
const { data: currentOrganization } = useQuery(() => currentOrganizationQuery())

// Get token balance
const { data: tokenBalanceData } = useQuery(() =>
  organizationBalanceQuery({ organizationId: currentOrganization.value?.id ?? '' }),
)

// Computed properties
const tokenBalance = computed(() => tokenBalanceData.value?.balance ?? 0)
const remainingTokens = computed(() => Math.max(0, tokenBalance.value - 35))
const hasEnoughTokens = computed(() => tokenBalance.value >= 35)

const remainingTokensColor = computed(() => {
  if (remainingTokens.value > 0) return 'text-success'
  if (remainingTokens.value === 0) return 'text-warning-light-content'
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
