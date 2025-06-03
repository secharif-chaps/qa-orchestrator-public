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
      <div>
        <h1>{{ $t('financials.comingSoon') }}</h1>
      </div>
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
