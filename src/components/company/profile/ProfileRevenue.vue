<template>
  <div class="bg-base-200 rounded-card border border-primary-stroke p-4">
    <div class="items-center flex gap-6 relative">
      <i class="fa fa-money-bill text-primary text-2xl"></i>
      <div>
        <h2>
          {{ getSourcedValue(company?.profile?.revenue) ?? $t('common.notFound') }}
        </h2>
        <div>{{ $t('profile.sections.metrics.revenue') }}</div>
      </div>

      <div class="absolute top-0 right-0">
        <Source :sourced-value="company?.profile?.revenue" />
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
