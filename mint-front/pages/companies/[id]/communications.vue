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
    <div>
      <h1>{{ $t('communications.comingSoon') }}</h1>
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
