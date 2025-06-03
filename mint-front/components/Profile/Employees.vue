<template>
  <Card>
    <div class="text-center space-y-2">
      <div
        class="bg-orange-100 lg:w-2/3 text-orange-500 mx-auto px-2 py-2 rounded"
      >
        <span >
          {{ getSourcedValue(company?.profile?.employeeCount) ?? $t('common.notFound') }}
        </span>
      </div>
      <div>{{ $t('profile.sections.metrics.employees') }}</div>
      <Source
        v-if="company?.profile?.employeeCount"
        :sourced-value="company?.profile?.employeeCount"
      />
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
