<template>
  <div class="bg-primary-lightest rounded-card border-primary-lighter-stroke border p-4">
    <div class="relative flex items-center gap-6">
      <i class="fa fa-money-bill text-neutral-black-font text-2xl"></i>
      <div>
        <h2>
          {{ getSourcedValue(company?.profile?.revenue) ?? $t('common.notFound') }}
        </h2>
        <div>{{ $t('screen.profile.sections.metrics.revenue') }}</div>
      </div>

      <div class="absolute top-0 right-0">
        <Source :sourced-value="company?.profile?.revenue" />
      </div>
    </div>
  </div>
</template>

<script lang="ts" setup>
import { getSourcedValue } from '@/components/helpers/sourcedValues'
import { companyByIdQuery } from '@/queries/companies'
import { useQuery } from '@pinia/colada'
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import Source from '../Source.vue'

const route = useRoute()

const companyId = computed(() => String((route.params as Record<string, string>).companyId || ''))

const { data: company } = useQuery(() =>
  companyByIdQuery({
    id: companyId.value,
  }),
)
</script>
