import { defineQueryOptions } from '@pinia/colada'
import { getCompanies, getCompanyById, getRecentCompanies } from '@/api/companies'

export const COMPANY_QUERY_KEYS = {
  root: ['companies'] as const,
  recent: (limit: number) => [...COMPANY_QUERY_KEYS.root, 'recent', limit] as const,
  byId: (id: string) => [...COMPANY_QUERY_KEYS.root, id] as const,
  withFilters: (filters: {
    page: number
    size: number
    name: string
    sort?: string
    order?: 'asc' | 'desc'
  }) => [...COMPANY_QUERY_KEYS.root, { filters }] as const,
}

export const companyByIdQuery = defineQueryOptions(({ id }: { id: string }) => ({
  key: COMPANY_QUERY_KEYS.byId(id),
  query: () => {
    // Ensure we don't make API calls with invalid IDs
    if (!id || id === 'null' || id === 'undefined' || id.trim() === '') {
      throw new Error('Invalid company ID')
    }
    return getCompanyById(id)
  },
}))

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
