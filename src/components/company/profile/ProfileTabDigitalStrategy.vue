<template>
  <div class="flex flex-col gap-4">
    <!-- Digital Insights -->
    <ChapseAlert v-if="company?.digital?.insights" variant="mage">
      {{ company.digital.insights }}
    </ChapseAlert>

    <!-- Digital Strategy - detailed breakdown -->
    <div v-if="company?.digital?.digitalStrategy" class="flex flex-col gap-3">
      <h4 class="text-secondary font-medium">{{ $t('profile.sections.digital.strategy') }}</h4>

      <div v-if="digitalStrategy?.overallStrategy" class="text-sm">
        <h5 class="text-secondary mb-1 font-medium">
          {{ $t('profile.sections.digital.overallStrategy', 'Overall Strategy') }}
        </h5>
        <p class="text-secondary">
          {{ getSourcedValue(digitalStrategy.overallStrategy) }}
          <Source :sourced-value="digitalStrategy.overallStrategy" />
        </p>
      </div>

      <div v-if="digitalStrategy?.digitalTransformation" class="text-sm">
        <h5 class="text-secondary mb-1 font-medium">
          {{ $t('profile.sections.digital.digitalTransformation', 'Digital Transformation') }}
        </h5>
        <p class="text-secondary">
          {{ getSourcedValue(digitalStrategy.digitalTransformation) }}
          <Source :sourced-value="digitalStrategy.digitalTransformation" />
        </p>
      </div>

      <div v-if="digitalStrategy?.eCommerceCapabilities" class="text-sm">
        <h5 class="text-secondary mb-1 font-medium">
          {{ $t('profile.sections.digital.eCommerceCapabilities', 'E-Commerce Capabilities') }}
        </h5>
        <p class="text-secondary">
          {{ getSourcedValue(digitalStrategy.eCommerceCapabilities) }}
          <Source :sourced-value="digitalStrategy.eCommerceCapabilities" />
        </p>
      </div>

      <div v-if="digitalStrategy?.mobileStrategy" class="text-sm">
        <h5 class="text-secondary mb-1 font-medium">
          {{ $t('profile.sections.digital.mobileStrategy', 'Mobile Strategy') }}
        </h5>
        <p class="text-secondary">
          {{ getSourcedValue(digitalStrategy.mobileStrategy) }}
          <Source :sourced-value="digitalStrategy.mobileStrategy" />
        </p>
      </div>

      <div v-if="digitalStrategy?.digitalMarketingApproach" class="text-sm">
        <h5 class="text-secondary mb-1 font-medium">
          {{
            $t('profile.sections.digital.digitalMarketingApproach', 'Digital Marketing Approach')
          }}
        </h5>
        <p class="text-secondary">
          {{ getSourcedValue(digitalStrategy.digitalMarketingApproach) }}
          <Source :sourced-value="digitalStrategy.digitalMarketingApproach" />
        </p>
      </div>
    </div>

    <!-- Online Services -->
    <div v-if="onlineServices?.length">
      <h4 class="text-secondary mb-2 font-medium">
        {{ $t('profile.sections.digital.onlineServices') }}
      </h4>
      <div class="flex flex-col gap-2">
        <div v-for="service in onlineServices" :key="service.name" class="bg-base-200 rounded p-3">
          <h5 class="text-secondary mb-1 font-medium">{{ service.name }}</h5>
          <p class="text-secondary text-sm">{{ service.description }}</p>
        </div>
        <Source :source="onlineServicesSource" />
      </div>
    </div>

    <!-- Loyalty Program -->
    <div v-if="company?.digital?.loyaltyProgram">
      <h4 class="text-secondary mb-2 font-medium">
        {{ $t('profile.sections.digital.loyaltyProgram') }}
      </h4>
      <p class="text-secondary text-sm">
        {{ getSourcedValue(company.digital.loyaltyProgram) || $t('common.notFound') }}
      </p>
      <Source :sourced-value="company.digital.loyaltyProgram" />
    </div>

    <!-- No data message -->
    <div v-if="!hasAnyDigitalData" class="text-secondary py-4 text-center">
      {{ $t('common.noData') }}
    </div>
  </div>
</template>

<script lang="ts" setup>
import { useQuery } from '@pinia/colada'
import { companyByIdQuery } from '@/queries/companies'
import { useRoute } from 'vue-router'
import { computed } from 'vue'
import { getSourcedValue, getSourcedSource } from '@/components/helpers/sourcedValues'
import Source from '@/components/company/Source.vue'
import ChapseAlert from '@/components/ui/ChapseAlert.vue'
import type { SourcedValue } from '@/types/company'

interface DigitalStrategyContent {
  overallStrategy?: SourcedValue<string>
  digitalTransformation?: SourcedValue<string>
  eCommerceCapabilities?: SourcedValue<string>
  mobileStrategy?: SourcedValue<string>
  digitalMarketingApproach?: SourcedValue<string>
}

interface OnlineServicesContent {
  services: { name: string; description: string }[]
}

const route = useRoute()

const companyId = computed(() => route.params.companyId as string)

const { data: company } = useQuery(companyByIdQuery, () => ({
  id: companyId.value,
}))

const digitalStrategy = computed((): DigitalStrategyContent | undefined => {
  return getSourcedValue(company.value?.digital?.digitalStrategy) as
    | DigitalStrategyContent
    | undefined
})

const onlineServices = computed((): { name: string; description: string }[] => {
  const servicesData = getSourcedValue(company.value?.digital?.onlineServices) as
    | OnlineServicesContent
    | undefined
  return servicesData?.services || []
})

const onlineServicesSource = computed((): string | undefined => {
  return getSourcedSource(company.value?.digital?.onlineServices)
})

const hasAnyDigitalData = computed(() => {
  const digital = company.value?.digital
  if (!digital) return false

  return !!(
    digital.insights ||
    digital.digitalStrategy ||
    digital.onlineServices ||
    digital.loyaltyProgram
  )
})
</script>
