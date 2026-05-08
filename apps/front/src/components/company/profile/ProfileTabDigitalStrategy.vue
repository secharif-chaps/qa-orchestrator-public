<template>
  <div class="flex flex-col gap-4">
    <!-- Digital Insights -->
    <ChapseAlert v-if="company?.digital?.insights" variant="mage">
      {{ company.digital.insights }}
    </ChapseAlert>

    <!-- Digital Strategy - detailed breakdown -->
    <div
      v-if="company?.digital?.digitalStrategy"
      class="text-neutral-black-font flex flex-col gap-3 text-base"
    >
      <h4 class="text-lg font-bold">
        {{ $t('screen.profile.sections.digital.strategy') }}
      </h4>

      <ProfileSourcedBlock
        v-for="block in digitalStrategyBlocks"
        :key="block.key"
        :title="digitalFieldLabelMap[block.key] ?? block.key"
        :sourced-value="block.sourcedValue"
      />
    </div>

    <!-- Online Services -->
    <div
      v-if="onlineServices?.length"
      class="text-neutral-black-font flex flex-col gap-3 text-base"
    >
      <h4 class="text-lg font-bold">
        {{ $t('screen.profile.sections.digital.onlineServices') }}
      </h4>
      <ProfileOnlineServiceItem
        v-for="service in onlineServices"
        :key="service.name"
        :service
        :source="onlineServicesSource"
      />
    </div>

    <!-- Loyalty Program -->
    <ProfileSourcedBlock
      v-if="company?.digital?.loyaltyProgram"
      :title="$t('screen.profile.sections.digital.loyaltyProgram')"
      :sourced-value="company.digital.loyaltyProgram"
    />

    <!-- No data message -->
    <div v-if="!hasAnyDigitalData" class="py-4 text-center">
      {{ $t('common.noData') }}
    </div>
  </div>
</template>

<script lang="ts" setup>
import ProfileOnlineServiceItem from '@/components/company/profile/ProfileOnlineServiceItem.vue'
import ProfileSourcedBlock from '@/components/company/profile/ProfileSourcedBlock.vue'
import { getSourcedSource, getSourcedValue } from '@/components/helpers/sourcedValues'
import ChapseAlert from '@/components/ui/ChapseAlert.vue'
import { companyByIdQuery } from '@/queries/companies'
import type { SourcedValue } from '@/types/company'
import { useQuery } from '@pinia/colada'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'

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
const { t } = useI18n()

const digitalFieldLabelMap: Record<string, string> = {
  overallStrategy: t('screen.profile.sections.digital.overallStrategy'),
  digitalTransformation: t('screen.profile.sections.digital.digitalTransformation'),
  eCommerceCapabilities: t('screen.profile.sections.digital.eCommerceCapabilities'),
  mobileStrategy: t('screen.profile.sections.digital.mobileStrategy'),
  digitalMarketingApproach: t('screen.profile.sections.digital.digitalMarketingApproach'),
}

const companyId = computed(() => String((route.params as Record<string, string>).companyId || ''))

const { data: company } = useQuery(() =>
  companyByIdQuery({
    id: companyId.value,
  }),
)

const digitalStrategy = computed((): DigitalStrategyContent | undefined => {
  return getSourcedValue(company.value?.digital?.digitalStrategy) as
    | DigitalStrategyContent
    | undefined
})

const digitalStrategyFields = [
  'overallStrategy',
  'digitalTransformation',
  'eCommerceCapabilities',
  'mobileStrategy',
  'digitalMarketingApproach',
] as const

const digitalStrategyBlocks = computed(() =>
  digitalStrategyFields
    .filter((key) => digitalStrategy.value?.[key])
    .map((key) => ({
      key,
      sourcedValue: digitalStrategy.value![key]!,
    })),
)

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
