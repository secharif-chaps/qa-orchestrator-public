import { defineQueryOptions } from '@pinia/colada'
import { getCompanyTasks } from '@/api/tasks'

export const TASK_QUERY_KEYS = {
  root: ['tasks'] as const,
  byCompanyId: (companyId: string) => [...TASK_QUERY_KEYS.root, 'company', companyId] as const,
}

export const companyTasksQuery = defineQueryOptions(({ companyId }: { companyId: string }) => ({
  key: TASK_QUERY_KEYS.byCompanyId(companyId),
  enabled: !!companyId && companyId !== 'null' && companyId !== 'undefined',
  query: () => getCompanyTasks(companyId),
}))
