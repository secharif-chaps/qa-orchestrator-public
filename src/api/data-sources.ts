import { apiClient } from './client'
import type { DataSourceConfig } from '@/types/data-source'

export const getDataSourceConfig = async (
  organizationId: string,
  source: string,
): Promise<DataSourceConfig> => {
  const response = await apiClient.get<DataSourceConfig>(
    `/organizations/${organizationId}/data-sources/${source}/config`,
  )
  return response
}

export const updateDataSourceConfig = async (
  organizationId: string,
  source: string,
  apiKey: string,
): Promise<DataSourceConfig> => {
  const response = await apiClient.put<DataSourceConfig>(
    `/organizations/${organizationId}/data-sources/${source}/config`,
    { api_key: apiKey },
  )
  return response
}
