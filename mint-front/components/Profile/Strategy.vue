<template>
  <Card>
    <div class="flex flex-col gap-4">
      <div class="col-span-2">
        <h3 class="space-x-2 font-bold text-primary">
          <i class="fa fa-chart-sine"></i>
          <span>{{ title }}</span>
        </h3>
      </div>

      <!-- Digital Strategy - individual property loading -->
      <div>{{ $t('profile.sections.digital.strategy') }}</div>
      <div class="text-secondary text-sm">
        {{ getSourcedValue(company?.digital?.strategy) || $t('common.notFound') }}
        <Source :sourced-value="company?.digital?.strategy" />
      </div>

      <!-- Loyalty Program - individual property loading -->
      <div>{{ $t('profile.sections.digital.loyaltyProgram') }}</div>
      <div class="text-secondary text-sm">
        {{ getSourcedValue(company?.digital?.loyaltyProgram) || $t('common.notFound') }}
        <Source :sourced-value="company?.digital?.loyaltyProgram" />
      </div>

      <!-- Online Services - individual property loading -->
      <div>{{ $t('profile.sections.digital.onlineServices') }}</div>
      <div class="text-secondary text-sm">
        {{
          (
            company?.digital?.onlineServices?.map(
              (service: { value: string }) => service.value
            ) || []
          ).join(', ') || $t('common.notFound')
        }}
      </div>
    </div>
  </Card>
</template>

<script lang="ts" setup>
import { useI18n } from 'vue-i18n'

const { t } = useI18n()
const { company, getSourcedValue } = useCompanyData()

defineProps<{
  title: string
}>()
</script>
