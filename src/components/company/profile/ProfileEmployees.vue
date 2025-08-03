<template>
  <div class="bg-bg1 rounded-lg p-4">
    <div class="text-center space-y-2">
      <div
        class="bg-orange-100 dark:bg-orange-400/10 lg:w-2/3 text-orange-500 dark:text-orange-400 mx-auto px-2 py-2 rounded"
      >
        <span>
          {{ getSourcedValue(company?.profile?.employeeCount) ?? $t('common.notFound') }}
        </span>
      </div>
      <div>{{ $t('profile.sections.metrics.employees') }}</div>
      <Source
        v-if="company?.profile?.employeeCount"
        :sourced-value="company?.profile?.employeeCount"
      />
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
