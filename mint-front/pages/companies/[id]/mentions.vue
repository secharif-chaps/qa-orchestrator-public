<template>
  <LayoutsCompanyCard :title="$t('mentions.title')" icon="fa-quote-left">
    <div class="flex flex-col gap-4">
    
    <!-- Task state -->
    <TaskState
      v-if="companyId"
      :company-id="companyId"
      :required-task-types="[]"
      :loading-title="$t('mentions.loading.title')"
      :loading-description="$t('mentions.loading.description')"
    />
    <div>
      <h1>{{ $t('mentions.comingSoon') }}</h1>
    </div>
  </div>
  </LayoutsCompanyCard>
</template>

<script lang="ts" setup>
import TaskState from '~/components/TaskState.vue'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()

// Set page metadata
useHead({
  title: `Mint - ${t('mentions.title')}`,
  meta: [{ name: 'description', content: t('mentions.title') }],
})

const { company, companyId, fetchCompany } = useCompanyData()

onMounted(async () => {
  if (!company.value) {
    await fetchCompany()
  }
})

</script>
