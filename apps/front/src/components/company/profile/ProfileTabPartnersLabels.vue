<template>
  <div class="text-neutral-black-font gap-md flex flex-col text-base">
    <!-- Partner Brands -->
    <div v-if="company?.products?.partnerBrands?.length" class="space-y-2xs">
      <h4 class="font-bold">
        {{ $t('screen.profile.sections.products.partnerBrands') }}
      </h4>
      <div class="gap-2xs flex flex-wrap items-center">
        <Tag
          v-for="brand in company.products.partnerBrands"
          :key="getSourcedValue(brand)"
          color="yellow"
          size="sm"
        >
          <div class="gap-3xs flex items-center">
            <span>{{ stripParenthesisSuffix(getSourcedValue(brand)) }}</span>
            <Source :sourced-value="brand" />
          </div>
        </Tag>
      </div>
    </div>

    <!-- Private Labels -->
    <div v-if="company?.products?.privateLabels?.length" class="space-y-2xs">
      <h4 class="font-bold">
        {{ $t('screen.profile.sections.products.privateLabels', { company: company?.name }) }}
      </h4>
      <div class="gap-2xs flex flex-wrap items-center">
        <Tag
          v-for="label in company.products.privateLabels"
          :key="getSourcedValue(label)"
          color="yellow"
          size="sm"
        >
          <div class="gap-3xs flex items-center">
            <span>{{ stripParenthesisSuffix(getSourcedValue(label)) }}</span>
            <Source :sourced-value="label" />
          </div>
        </Tag>
      </div>
    </div>

    <!-- No data message -->
    <div v-if="!hasAnyPartnerData" class="py-4 text-center">
      {{ $t('common.noData') }}
    </div>
  </div>
</template>

<script lang="ts" setup>
import Source from '@/components/company/Source.vue'
import { getSourcedValue } from '@/components/helpers/sourcedValues'
import { companyByIdQuery } from '@/queries/companies'
import { Tag } from '@owlint/feathers-vue'
import { useQuery } from '@pinia/colada'
import { computed } from 'vue'
import { useRoute } from 'vue-router'

// TEMPORARY: Dify sometimes returns brand names with a parenthesized suffix like "Brand (FR)".
// Strip it until the workflow output is cleaned up upstream.
const stripParenthesisSuffix = (value: string | undefined) => value?.split('(')[0]?.trim()

const route = useRoute()

const companyId = computed(() => String((route.params as Record<string, string>).companyId || ''))

const { data: company } = useQuery(() =>
  companyByIdQuery({
    id: companyId.value,
  }),
)

const hasAnyPartnerData = computed(() => {
  const products = company.value?.products
  if (!products) return false

  return !!(
    (products.partnerBrands && products.partnerBrands.length > 0) ||
    (products.privateLabels && products.privateLabels.length > 0)
  )
})
</script>
