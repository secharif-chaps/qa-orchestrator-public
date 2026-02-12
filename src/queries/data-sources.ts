import { defineQueryOptions } from '@pinia/colada'
import { getDataSourceConfig } from '@/api/data-sources'

export const DATA_SOURCE_KEYS = {
  root: ['data-sources'] as const,
  config: (organizationId: string, source: string) =>
    [...DATA_SOURCE_KEYS.root, 'config', organizationId, source] as const,
}

export const dataSourceConfigQuery = defineQueryOptions(
  ({ organizationId, source }: { organizationId: string; source: string }) => ({
    key: DATA_SOURCE_KEYS.config(organizationId, source),
    query: () => {
      if (!organizationId || organizationId.trim() === '') {
        throw new Error('Invalid organization ID')
      }
      return getDataSourceConfig(organizationId, source)
    },
  }),
)
