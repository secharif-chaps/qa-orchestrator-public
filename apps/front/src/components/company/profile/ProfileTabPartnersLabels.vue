<template>
  <div class="flex flex-col gap-4">
    <!-- Partner Brands -->
    <div v-if="company?.products?.partnerBrands?.length">
      <h4 class="text-secondary mb-2 flex items-center gap-2 font-medium">
        {{ $t('screen.profile.sections.products.partnerBrands') }}
      </h4>
      <div class="grid grid-cols-2 gap-2">
        <div
          v-for="brand in company.products.partnerBrands"
          :key="getSourcedValue(brand)"
          class="bg-base-200 flex items-center justify-between rounded p-3"
        >
          <span class="text-secondary">{{ getSourcedValue(brand) }}</span>
          <Source :sourced-value="brand" />
        </div>
      </div>
    </div>

    <!-- Private Labels -->
    <div v-if="company?.products?.privateLabels?.length">
      <h4 class="text-secondary mb-2 flex items-center gap-2 font-medium">
        {{ $t('screen.profile.sections.products.privateLabels', { company: company?.name }) }}
      </h4>
      <div class="grid grid-cols-2 gap-2">
        <div
          v-for="label in company.products.privateLabels"
          :key="getSourcedValue(label)"
          class="bg-base-200 flex items-center justify-between rounded p-3"
        >
          <span class="text-secondary">{{ getSourcedValue(label) }}</span>
          <Source :sourced-value="label" />
        </div>
      </div>
    </div>

    <!-- No data message -->
    <div v-if="!hasAnyPartnerData" class="text-secondary py-4 text-center">
      {{ $t('common.noData') }}
    </div>
  </div>
</template>

<script lang="ts" setup>
import { useQuery } from '@pinia/colada'
import { companyByIdQuery } from '@/queries/companies'
import { useRoute } from 'vue-router'
import { computed } from 'vue'
import { getSourcedValue } from '@/components/helpers/sourcedValues'
import Source from '@/components/company/Source.vue'

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
