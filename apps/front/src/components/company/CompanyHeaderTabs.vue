<template>
  <PageHeaderTabs
    :tabs="tabs"
    :route-params="{ folderId, companyId }"
    visits-storage-key="chapsmind:company-tab-visits"
  />
</template>

<script lang="ts" setup>
import PageHeaderTabs, { type PageHeaderTab } from '@/components/ui/PageHeaderTabs.vue'
import { computed } from 'vue'

interface Props {
  folderId: string
  companyId: string
  /** Number of job offers to display as badge on the jobs tab */
  jobOffersCount?: number
}

const { jobOffersCount } = defineProps<Props>()

const tabs = computed<PageHeaderTab[]>(() => [
  {
    id: 'profile',
    routeName: '/folders/[folderId]/companies/[companyId]/',
    label: 'screen.company.tabs.profile',
    pinned: true,
  },
  {
    id: 'timeline',
    routeName: '/folders/[folderId]/companies/[companyId]/timeline',
    label: 'screen.company.tabs.timeline',
  },
  {
    id: 'products',
    routeName: '/folders/[folderId]/companies/[companyId]/products',
    label: 'screen.company.tabs.products',
  },
  {
    id: 'team',
    routeName: '/folders/[folderId]/companies/[companyId]/team',
    label: 'screen.company.tabs.team',
  },
  {
    id: 'jobs',
    routeName: '/folders/[folderId]/companies/[companyId]/jobs',
    label: 'screen.company.tabs.jobs',
    badge: jobOffersCount,
  },
  {
    id: 'press',
    routeName: '/folders/[folderId]/companies/[companyId]/press',
    label: 'screen.company.tabs.press',
  },
  {
    id: 'csr',
    routeName: '/folders/[folderId]/companies/[companyId]/csr',
    label: 'screen.company.tabs.csr',
  },
  {
    id: 'financial',
    routeName: '/folders/[folderId]/companies/[companyId]/financial',
    label: 'screen.company.tabs.financial',
  },
])
</script>
