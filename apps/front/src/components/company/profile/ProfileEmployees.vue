<template>
  <div class="bg-base-200 rounded-card border-primary-stroke border p-4">
    <div class="relative flex items-center gap-6">
      <i class="fa fa-users text-secondary text-2xl"></i>
      <div>
        <h2>
          {{ getSourcedValue(company?.profile?.employeeCount) ?? $t('common.notFound') }}
        </h2>
        <div>{{ $t('profile.sections.metrics.employees') }}</div>
      </div>

      <div class="absolute top-0 right-0">
        <Source :sourced-value="company?.profile?.employeeCount" />
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

const companyId = computed(() => String((route.params as Record<string, string>).companyId || ''))

const { data: company } = useQuery(() =>
  companyByIdQuery({
    id: companyId.value,
  }),
)
</script>
