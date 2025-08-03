<template>
  <div class="bg-bg1 rounded-lg p-4">
    <div class="flex flex-col gap-4">
      <div class="col-span-2">
        <h3 class="space-x-2 font-bold text-primary">
          <i class="fa fa-chart-sine"></i>
          <span>{{ $t('profile.sections.digital.title') }}</span>
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
                (service: { value: string }) => service.value,
              ) || []
            ).join(', ') || $t('common.notFound')
          }}
        </p>
      </div>
    </div>
  </div>
</template>

<script lang="ts" setup>
import { useQuery } from '@pinia/colada'
import { companyByIdQuery } from '@/queries/companies'
import { useRoute } from 'vue-router'
import { computed } from 'vue'
import { getSourcedValue } from '@/components/helpers/sourcedValues'
import Source from '../Source.vue'

const route = useRoute()

const companyId = computed(() => route.params.companyId as string)

const { data: company } = useQuery(companyByIdQuery, () => ({
  id: companyId.value,
}))
</script>
