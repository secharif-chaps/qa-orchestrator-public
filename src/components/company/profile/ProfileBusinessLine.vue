<template>
  <div class="bg-bg1 rounded-lg p-4">
    <div class="flex flex-col gap-2">
      <h4>Line of business</h4>
      <!-- Business line - individual property loading -->
      <div class="text-sm flex flex-col gap-2">
        <p class="text-secondary">
          {{ getSourcedValue(company?.profile?.businessLine) ?? 'Not found' }}
        </p>
      </div>
      <Source :sourced-value="company?.profile?.businessLine" />
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
