<template>
  <div class="bg-bg1 rounded-lg p-4">
    <div class="flex flex-col gap-4">
      <div class="col-span-2">
        <h3 class="space-x-2 font-bold text-primary">
          <i class="fa fa-box-open"></i>
          <span>{{ $t('profile.sections.products.title') }}</span>
        </h3>
      </div>

      <!-- Product Range - individual property loading -->
      <h4>{{ $t('profile.sections.products.range') }}</h4>
      <div class="text-sm flex flex-col gap-2">
        <p class="text-secondary">
          {{
            (company?.products?.range?.map((p: any) => p.value) || []).join(', ') ||
            $t('common.notFound')
          }}
        </p>
      </div>

      <!-- Partner Brands - individual property loading -->
      <h4>{{ $t('profile.sections.products.partnerBrands') }}</h4>
      <div class="pl-4 text-sm flex flex-col gap-2">
        <ul class="list-disc text-secondary">
          <li
            class="space-x-2"
            v-for="brand in company?.products?.partnerBrands || []"
            :key="brand.value"
          >
            <span class="text-sm">
              {{ brand.value }}
            </span>
            <Source :sourced-value="company?.profile?.groupName" />
          </li>
          <li v-if="company?.products?.partnerBrands?.length === 0" class="text-sm">
            {{ $t('common.notFound') }}
          </li>
        </ul>
      </div>

      <!-- Private Labels - individual property loading -->
      <h4>{{ $t('profile.sections.products.privateLabels', { company: company?.name }) }}</h4>
      <div class="pl-4 text-sm flex flex-col gap-2">
        <ul class="list-disc text-secondary">
          <li
            class="space-x-2"
            v-for="brand in company?.products?.privateLabels || []"
            :key="brand.value"
          >
            <span class="text-sm">
              {{ brand.value }}
            </span>
            <Source :sourced-value="company?.profile?.groupName" />
          </li>
          <li v-if="company?.products?.privateLabels?.length === 0" class="text-sm">
            {{ $t('common.notFound') }}
          </li>
        </ul>
      </div>
    </div>
  </div>
</template>

<script lang="ts" setup>
import { useQuery } from '@pinia/colada'
import { companyByIdQuery } from '@/queries/companies'
import { useRoute } from 'vue-router'
import { computed } from 'vue'
import Source from '../Source.vue'

const route = useRoute()

const companyId = computed(() => route.params.companyId as string)

const { data: company } = useQuery(companyByIdQuery, () => ({
  id: companyId.value,
}))
</script>
