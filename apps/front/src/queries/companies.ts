import { defineQueryOptions } from '@pinia/colada'
import { getCompanies, getCompanyById, getRecentCompanies } from '@/api/companies'

export const COMPANY_QUERY_KEYS = {
  root: ['companies'] as const,
  recent: (limit: number) => [...COMPANY_QUERY_KEYS.root, 'recent', limit] as const,
  byId: (id: string, language?: string) =>
    [...COMPANY_QUERY_KEYS.root, id, language ?? 'default'] as const,
  withFilters: (filters: {
    page: number
    size: number
    name: string
    sort?: string
    order?: 'asc' | 'desc'
  }) => [...COMPANY_QUERY_KEYS.root, { filters }] as const,
}

export const companyByIdQuery = defineQueryOptions(
  ({ id, language }: { id: string; language?: string }) => ({
    key: COMPANY_QUERY_KEYS.byId(id, language),
    enabled: !!id && id !== 'null' && id !== 'undefined',
    query: () => getCompanyById(id, language),
  }),
)

export const recentCompaniesQuery = defineQueryOptions(({ limit }: { limit: number }) => ({
  key: COMPANY_QUERY_KEYS.recent(limit),
  query: () => getRecentCompanies(limit),
}))

export const companiesQuery = defineQueryOptions(
  ({
    filters,
  }: {
    filters: {
      page: number
      size: number
      name: string
      sort?: string
      order?: 'asc' | 'desc'
    }
  }) => ({
    key: COMPANY_QUERY_KEYS.withFilters(filters),
    query: () => getCompanies(filters),
  }),
)
