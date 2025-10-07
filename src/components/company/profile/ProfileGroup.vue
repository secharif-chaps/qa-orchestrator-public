<template>
  <div class="bg-base-100 rounded-lg p-4 relative h-full">
    <div class="flex flex-col gap-2">
      <h4>Group</h4>
      <!-- Group name - individual property loading -->
      <div>
        <p class="text-primary-light-content">
          {{ getSourcedValue(company?.profile?.groupName) ?? 'Not found' }}
        </p>
      </div>
      <div class="absolute top-2 right-2">
        <Source :sourced-value="company?.profile?.groupName" />
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
