<template>
  <div class="bg-bg1 rounded-lg p-4">
    <div class="flex flex-col gap-4">
      <div class="col-span-2">
        <h3 class="space-x-2 font-bold text-primary">
          <i class="fa fa-bullseye-arrow"></i>
          <span>{{ $t('profile.sections.target.title') }}</span>
        </h3>
      </div>

      <!-- Customer Type - individual property loading -->
      <h4>{{ $t('profile.sections.target.customerBase') }}</h4>
      <div v-if="company?.products?.customerType">
        <p class="text-sm text-secondary">
          {{ company?.products?.customerType ?? $t('common.notFound') }}
        </p>
      </div>
      <div v-else class="text-secondary italic">
        {{ $t('common.loading') }}
      </div>

      <!-- Marketing Positioning - individual property loading -->
      <h4>{{ $t('profile.sections.target.positioning') }}</h4>
      <div v-if="company?.products?.marketingPositioning">
        <p class="text-sm text-secondary">
          {{ company?.products?.marketingPositioning ?? $t('common.notFound') }}
        </p>
      </div>
      <div v-else class="text-secondary italic">
        {{ $t('common.loading') }}
      </div>
    </div>
  </div>
</template>

<script lang="ts" setup>
import { useQuery } from '@pinia/colada'
import { companyByIdQuery } from '@/queries/companies'
import { useRoute } from 'vue-router'
import { computed } from 'vue'

const route = useRoute()

const companyId = computed(() => route.params.companyId as string)

const { data: company } = useQuery(companyByIdQuery, () => ({
  id: companyId.value,
}))
</script>
