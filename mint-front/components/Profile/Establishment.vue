<template>
  <Card>
    <div class="text-center space-y-2">
      <div
        class="bg-emerald-100 dark:bg-emerald-400/10 lg:w-2/3 text-emerald-600 dark:text-emerald-400 mx-auto px-2 py-2 rounded"
      >
        <span >
          {{
            getSourcedValue(company?.profile?.establishmentYear) ?? $t('common.notFound')
          }}
        </span>
        
      </div>
      <div>{{ $t('profile.sections.metrics.establishment') }}</div>
      <Source
        v-if="company?.profile?.establishmentYear"
        :sourced-value="company?.profile?.establishmentYear"
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
