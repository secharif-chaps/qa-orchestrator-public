<template>
  <div class="bg-bg1 rounded-lg p-4">
    <div class="text-center space-y-2">
      <div
        class="bg-emerald-100 dark:bg-emerald-400/10 lg:w-2/3 text-emerald-600 dark:text-emerald-400 mx-auto px-2 py-2 rounded"
      >
        <span>
          {{ getSourcedValue(company?.profile?.establishmentYear) ?? $t('common.notFound') }}
        </span>
      </div>
      <div>{{ $t('profile.sections.metrics.establishment') }}</div>
      <Source
        v-if="company?.profile?.establishmentYear"
        :sourced-value="company?.profile?.establishmentYear"
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
