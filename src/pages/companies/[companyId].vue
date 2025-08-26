<template>
  <CompanyCard :title="title" icon="fa-building">
    <div class="flex flex-col gap-4">
      <TasksFlow v-if="displayTasks" />
      <RouterView />
    </div>
    <!-- Floating AI Chat -->
    <FloatingChat />
  </CompanyCard>
</template>

<script lang="ts" setup>
import CompanyCard from '@/components/company/CompanyCard.vue'
import TasksFlow from '@/components/company/tasks/TasksFlow.vue'
import FloatingChat from '@/components/company/FloatingChat.vue'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'

const route = useRoute()

const displayTasks = computed(() => {
  return route.name === '/companies/[companyId]/'
})

const { t } = useI18n()

const title = computed(() => {
  switch (route.name) {
    case '/companies/[companyId]/':
      return t('dashboard.title')
    case '/companies/[companyId]/profile':
      return t('profile.title')
    case '/companies/[companyId]/products':
      return t('products.title')
    case '/companies/[companyId]/jobs':
      return t('jobs.title')
    case '/companies/[companyId]/timeline':
      return t('timeline.title')
    case '/companies/[companyId]/team':
      return t('team.title')
    case '/companies/[companyId]/press':
      return 'Press & Media Coverage'
    default:
      return ''
  }
})
</script>
