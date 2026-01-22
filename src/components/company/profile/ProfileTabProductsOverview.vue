<template>
  <div class="flex flex-col gap-4">
    <!-- Customer Type -->
    <div v-if="getSourcedValue(company?.products?.customerType)">
      <h4 class="font-medium text-secondary mb-2 flex items-center gap-2">
        {{ $t('profile.sections.products.customerType', 'Customer Type') }}
      </h4>
      <p class="text-sm text-secondary">
        {{ getSourcedValue(company?.products?.customerType) }}
        <Source :sourced-value="company?.products?.customerType" />
      </p>
    </div>

    <!-- Marketing Positioning -->
    <div v-if="getSourcedValue(company?.products?.marketingPositioning)">
      <h4 class="font-medium text-secondary mb-2 flex items-center gap-2">

        {{ $t('profile.sections.products.marketingPositioning', 'Marketing Positioning') }}
      </h4>
      <p class="text-sm text-secondary">
        {{ getSourcedValue(company?.products?.marketingPositioning) }}
        <Source :sourced-value="company?.products?.marketingPositioning" />
      </p>
    </div>

    <!-- No data message -->
    <div v-if="!hasAnyProductData" class="text-secondary text-center py-4">
      {{ $t('common.noData') }}
    </div>

    <!-- View Products Button -->
    <div v-if="hasAnyProductData" class="flex justify-end">
      <Button
        variant="secondary"
        :label="$t('profile.sections.products.viewProducts', 'View Products')"
        icon="fa fa-arrow-right"
        icon-position="right"
        @click="viewProducts"
      />
    </div>
  </div>
</template>

<script lang="ts" setup>
import { useQuery } from '@pinia/colada'
import { companyByIdQuery } from '@/queries/companies'
import { useRoute, useRouter } from 'vue-router'
import { computed } from 'vue'
import { getSourcedValue } from '@/components/helpers/sourcedValues'
import Source from '@/components/company/Source.vue'
import { Button } from '@owlint/feathers-vue'

const route = useRoute()
const router = useRouter()

const companyId = computed(() => route.params.companyId as string)

const viewProducts = () => {
  router.push({
    query: { ...route.query, section: 'products' },
  })
}

const { data: company } = useQuery(companyByIdQuery, () => ({
  id: companyId.value,
}))

const hasAnyProductData = computed(() => {
  const products = company.value?.products
  if (!products) return false

  return !!(products.customerType || products.marketingPositioning)
})
</script>
