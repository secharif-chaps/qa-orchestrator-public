<template>
  <LayoutsCompanyCard :title="$t('communications.title')" icon="fa-bullhorn">
    <div class="flex flex-col gap-4">

    <!-- Task state -->
    <TaskState
      v-if="companyId"
      :company-id="companyId"
      :required-task-types="[]"
      :loading-title="$t('communications.loading.title')"
      :loading-description="$t('communications.loading.description')"
    />
    <!-- Coming Soon State -->
    <Card>
      <div class="text-center py-12">
        <div class="text-5xl text-slate-300 dark:text-slate-600 mb-4">
          <i class="fa fa-bullhorn"></i>
        </div>
        <h1 class="text-2xl font-semibold text-slate-900 dark:text-slate-100 mb-2">{{ $t('communications.comingSoon') }}</h1>
        <p class="text-slate-500 dark:text-slate-400">Corporate communications feature will be available soon.</p>
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
  title: `Mint - ${t('communications.title')}`,
  meta: [{ name: 'description', content: t('communications.title') }],
})

const { companyName, companyId, fetchCompany } = useCompanyData()

onMounted(async () => {
  if (!company.value) {
    await fetchCompany()
  }
})

</script>
