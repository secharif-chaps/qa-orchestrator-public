<template>
  <Card class="h-full">
    <div class="col-span-2">
      <h3 class="space-x-2 font-bold text-primary">
        <i class="fa fa-bullhorn"></i>
        <span>{{ title }}</span>
      </h3>
      <div class="mt-4">
        <!-- Recent News - individual property loading -->
        <div>
          <ul class="list-disc pl-4">
            <li class="space-x-2 text-secondary" v-for="news in company?.press?.articles || []">
              <span class="text-sm">
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
  </Card>
</template>

<script lang="ts" setup>
import { useI18n } from 'vue-i18n'

const { t } = useI18n()
const { company, getSourcedValue } = useCompanyData()

defineProps<{
  title: string
}>()
</script>
