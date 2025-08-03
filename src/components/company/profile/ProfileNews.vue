<template>
  <div class="bg-bg1 rounded-lg p-4">
    <div class="col-span-2">
      <h3 class="space-x-2 font-bold text-primary">
        <i class="fa fa-bullhorn"></i>
        <span>{{ $t('profile.sections.news.title') }}</span>
      </h3>
      <div class="mt-4">
        <!-- Recent News - individual property loading -->
        <div>
          <ul class="list-disc pl-4 text-sm flex flex-col gap-2">
            <li class="space-x-2" v-for="news in company?.press?.articles || []" :key="news.source">
              <span class="text-sm text-secondary">
                {{ getSourcedValue(news) }}
              </span>
              <Source :sourced-value="news" />
            </li>
            <li v-if="company?.press?.articles?.length === 0" class="text-secondary">
              {{ $t('common.noData') }}
            </li>
          </ul>
        </div>
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
