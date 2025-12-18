<template>
  <div class="bg-base-100 rounded-lg p-4">
    <div class="flex flex-col gap-4">
      <div class="flex items-center justify-between">
        <h3 class="space-x-2 font-bold text-secondary">
          <i class="fa fa-box-open"></i>
          <span>{{ $t('profile.sections.products.title') }}</span>
        </h3>
        <Button
          variant="tertiary"
          label="View Products"
          icon="fa fa-arrow-right"
          icon-position="right"
          size="sm"
          @click="viewProducts"
        />
      </div>

      <!-- Customer Type -->
      <div v-if="company?.products?.customerType">
        <h4 class="font-medium text-secondary mb-2 flex items-center gap-2">
          <i class="fa fa-users"></i>
          Customer Type
        </h4>
        <p class="text-sm text-secondary">
          {{ company.products.customerType }}
        </p>
      </div>

      <!-- Marketing Positioning -->
      <div v-if="company?.products?.marketingPositioning">
        <h4 class="font-medium text-secondary mb-2 flex items-center gap-2">
          <i class="fa fa-bullseye"></i>
          Marketing Positioning
        </h4>
        <p class="text-sm text-secondary">
          {{ company.products.marketingPositioning }}
        </p>
      </div>

      <!-- Product Range -->
      <div v-if="company?.products?.range?.length">
        <h4 class="font-medium text-secondary mb-2 flex items-center gap-2">
          <i class="fa fa-box"></i>
          {{ $t('profile.sections.products.range') }}
        </h4>
        <div class="text-sm flex flex-col gap-2">
          <div class="flex flex-wrap gap-2">
            <div
              v-for="product in company.products.range"
              :key="getSourcedValue(product)"
              class="bg-base-200 rounded-full px-3 py-1 text-secondary text-sm"
            >
              {{ getSourcedValue(product) }}
              <Source :sourced-value="product" />
            </div>
          </div>
        </div>
      </div>

      <!-- Partner Brands -->
      <div v-if="company?.products?.partnerBrands?.length">
        <h4 class="font-medium text-secondary mb-2 flex items-center gap-2">
          <i class="fa fa-handshake"></i>
          {{ $t('profile.sections.products.partnerBrands') }}
        </h4>
        <div class="text-sm flex flex-col gap-2">
          <div class="grid grid-cols-2 gap-2">
            <div
              v-for="brand in company.products.partnerBrands"
              :key="getSourcedValue(brand)"
              class="bg-base-200 rounded p-3 flex items-center justify-between"
            >
              <span class="text-secondary">{{ getSourcedValue(brand) }}</span>
              <Source :sourced-value="brand" />
            </div>
          </div>
        </div>
      </div>

      <!-- Private Labels -->
      <div v-if="company?.products?.privateLabels?.length">
        <h4 class="font-medium text-secondary mb-2 flex items-center gap-2">
          <i class="fa fa-tag"></i>
          {{ $t('profile.sections.products.privateLabels', { company: company?.name }) }}
        </h4>
        <div class="text-sm flex flex-col gap-2">
          <div class="grid grid-cols-2 gap-2">
            <div
              v-for="label in company.products.privateLabels"
              :key="getSourcedValue(label)"
              class="bg-base-200 rounded p-3 flex items-center justify-between"
            >
              <span class="text-secondary">{{ getSourcedValue(label) }}</span>
              <Source :sourced-value="label" />
            </div>
          </div>
        </div>
      </div>

      <!-- Product Categories -->
      <!-- <div v-if="company?.products?.categories && Object.keys(company.products.categories).length">
        <h4 class="font-medium text-secondary mb-2 flex items-center gap-2">
          <i class="fa fa-sitemap"></i>
          Product Categories
        </h4>
        <div class="text-sm space-y-3">
          <div
            v-for="(items, category) in company.products.categories"
            :key="category"
            class="bg-base-200 rounded p-3"
          >
            <h5 class="font-medium text-secondary mb-2">{{ category }}</h5>
            <div class="flex flex-wrap gap-1">
              <span
                v-for="item in items"
                :key="item"
                class="bg-primary/10 text-secondary rounded-full px-2 py-1 text-xs"
              >
                {{ item }}
              </span>
            </div>
          </div>
        </div>
      </div> -->

      <!-- No data message -->
      <div v-if="!hasAnyProductData" class="text-secondary text-center py-4">
        {{ $t('common.noData') }}
      </div>
    </div>
  </div>
</template>

<script lang="ts" setup>
import { useQuery } from '@pinia/colada'
import { companyByIdQuery } from '@/queries/companies'
import { useRoute, useRouter } from 'vue-router'
import { computed } from 'vue'
import { getSourcedValue } from '@/components/helpers/sourcedValues'
import Source from '../Source.vue'
import { Button } from '@owlint/feathers-vue'

const route = useRoute()
const router = useRouter()

const companyId = computed(() => route.params.companyId as string)

// Function to switch to products section
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

  return !!(
    products.insights ||
    products.customerType ||
    products.marketingPositioning ||
    (products.range && products.range.length > 0) ||
    (products.partnerBrands && products.partnerBrands.length > 0) ||
    (products.privateLabels && products.privateLabels.length > 0) ||
    (products.categories && Object.keys(products.categories).length > 0)
  )
})
</script>
