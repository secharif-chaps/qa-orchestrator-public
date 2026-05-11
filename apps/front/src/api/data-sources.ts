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
  apiSecret?: string,
): Promise<DataSourceConfig> => {
  const body: Record<string, string> = { api_key: apiKey }
  if (apiSecret !== undefined) {
    body.api_secret = apiSecret
  }
  return apiClient.put<DataSourceConfig>(
    `/organizations/${organizationId}/data-sources/${source}/config`,
    body,
    { silent: true },
  )
}
