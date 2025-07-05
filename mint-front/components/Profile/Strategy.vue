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
      <h4>{{ $t('profile.sections.digital.strategy') }}</h4>
      <div class="text-sm flex flex-col gap-2">
        <p class="text-secondary">
          {{ getSourcedValue(company?.digital?.strategy) || $t('common.notFound') }}
        </p>
        <Source :sourced-value="company?.digital?.strategy" />
      </div>

      <!-- Loyalty Program - individual property loading -->
      <h4>{{ $t('profile.sections.digital.loyaltyProgram') }}</h4>
      <div class="text-sm flex flex-col gap-2">
        <p class="text-secondary">
          {{ getSourcedValue(company?.digital?.loyaltyProgram) || $t('common.notFound') }}
        </p>
        <Source :sourced-value="company?.digital?.loyaltyProgram" />
      </div>

      <!-- Online Services - individual property loading -->
      <h4>{{ $t('profile.sections.digital.onlineServices') }}</h4>
      <div class="text-sm flex flex-col gap-2">
        <p class="text-secondary">
        {{
          (
            company?.digital?.onlineServices?.map(
              (service: { value: string }) => service.value
            ) || []
            ).join(', ') || $t('common.notFound')
          }}
        </p>
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
