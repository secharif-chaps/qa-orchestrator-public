<template>
  <div class="bg-bg1 rounded-lg p-4">
    <div class="flex flex-col gap-2">
      <div class="col-span-2">
        <h3 class="space-x-2 font-bold text-primary">
          <i class="fa fa-hand-holding-heart"></i>
          <span>{{ $t('profile.sections.csr.title') }}</span>
        </h3>
      </div>

      <!-- CSR Initiatives - individual property loading -->
      <h4>{{ $t('profile.sections.csr.responsibility') }}</h4>
      <div class="p-4">
        <ul class="list-disc">
          <li
            class="space-x-2 text-secondary"
            v-for="initiative in company?.csr?.responsibility_initiatives || []"
            :key="initiative.value"
          >
            <span class="text-sm">
              {{ getSourcedValue(initiative) }}
            </span>
            <Source :sourced-value="initiative" />
          </li>
          <li
            v-if="company?.csr?.responsibility_initiatives?.length === 0"
            class="text-sm text-secondary italic"
          >
            {{ $t('common.notFound') }}
          </li>
        </ul>
      </div>

      <!-- Charity Actions - individual property loading -->
      <h4>{{ $t('profile.sections.csr.charity') }}</h4>
      <div class="p-4">
        <span class="text-sm text-secondary" v-if="company?.csr?.charity_actions">
          {{
            (
              company?.csr?.charity_actions.map((action: { value: string }) => action.value) || []
            ).join(', ') || $t('common.notFound')
          }}
        </span>
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
