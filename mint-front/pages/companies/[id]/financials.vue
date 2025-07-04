<template>
  <LayoutsCompanyCard :title="$t('financials.title')" icon="fa-chart-line">
    <div class="flex flex-col gap-4">
      <!-- Task state -->
      <TaskState
        v-if="companyId"
        :company-id="companyId"
        :required-task-types="[]"
        :loading-title="$t('financials.loading.title')"
        :loading-description="$t('financials.loading.description')"
      />
      <!-- Coming Soon State -->
      <Card>
        <div class="text-center py-12">
          <div class="text-5xl text-slate-300 dark:text-slate-600 mb-4">
            <i class="fa fa-chart-line"></i>
          </div>
          <h1 class="text-2xl font-semibold text-slate-900 dark:text-slate-100 mb-2">{{ $t('financials.comingSoon') }}</h1>
          <p class="text-slate-500 dark:text-slate-400">Financial data and analytics will be available soon.</p>
        </div>
      </Card>
    </div>
  </LayoutsCompanyCard>
</template>

<script lang="ts" setup>
import { OButton, OIcon } from '@owlint/feathers-vue'
import TaskState from '~/components/TaskState.vue'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()

// Set page metadata
useHead({
  title: `Mint - ${t('financials.title')}`,
  meta: [{ name: 'description', content: t('financials.title') }],
})

const { companyId, fetchCompany } = useCompanyData()

onMounted(async () => {
  if (!company.value) {
    await fetchCompany()
  }
})

</script>
