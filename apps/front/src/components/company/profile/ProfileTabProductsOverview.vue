<template>
  <div class="text-neutral-black-font gap-md flex flex-col text-base">
    <!-- Customer Type -->
    <div v-if="getSourcedValue(company?.products?.customerType)" class="space-y-2xs">
      <div class="gap-2xs flex items-center">
        <h4 class="font-bold">
          {{ $t('screen.profile.sections.products.customerType') }}
        </h4>
        <Source :sourced-value="company?.products?.customerType" />
      </div>
      <p>
        {{ getSourcedValue(company?.products?.customerType) }}
      </p>
    </div>

    <!-- Marketing Positioning -->
    <div v-if="getSourcedValue(company?.products?.marketingPositioning)" class="space-y-2xs">
      <div class="gap-2xs flex items-center">
        <h4 class="font-bold">
          {{ $t('screen.profile.sections.products.marketingPositioning') }}
        </h4>
        <Source :sourced-value="company?.products?.marketingPositioning" />
      </div>
      <p>
        {{ getSourcedValue(company?.products?.marketingPositioning) }}
      </p>
    </div>

    <!-- No data message -->
    <div v-if="!hasAnyProductData" class="py-4 text-center">
      {{ $t('common.noData') }}
    </div>

    <!-- View Products Button -->
    <div v-if="hasAnyProductData">
      <Button
        variant="accent"
        :label="$t('screen.profile.sections.products.viewProducts')"
        icon-right="fa-arrow-circle-right"
        size="sm"
        @click="viewProducts"
      />
    </div>
  </div>
</template>

<script lang="ts" setup>
import Source from '@/components/company/Source.vue'
import { getSourcedValue } from '@/components/helpers/sourcedValues'
import { companyByIdQuery } from '@/queries/companies'
import { Button } from '@owlint/feathers-vue'
import { useQuery } from '@pinia/colada'
import { computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'

const route = useRoute()
const router = useRouter()

const companyId = computed(() => String((route.params as Record<string, string>).companyId || ''))

const viewProducts = () => {
  router.push({
    name: '/folders/[folderId]/companies/[companyId]/products',
  })
}

const { data: company } = useQuery(() =>
  companyByIdQuery({
    id: companyId.value,
  }),
)

const hasAnyProductData = computed(() => {
  const products = company.value?.products
  if (!products) return false

  return !!(products.customerType || products.marketingPositioning)
})
</script>
