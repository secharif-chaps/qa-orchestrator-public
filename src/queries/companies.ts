import { defineQueryOptions } from '@pinia/colada'
import { getCompanies, getCompanyById } from '@/api/companies'

export const COMPANY_QUERY_KEYS = {
  root: ['companies'] as const,
  byId: (id: string) => [...COMPANY_QUERY_KEYS.root, id] as const,
  withFilters: (filters: { page: number; size: number; name: string }) =>
    [...COMPANY_QUERY_KEYS.root, { filters }] as const,
}

export const companyByIdQuery = defineQueryOptions(({ id }: { id: string }) => ({
  key: COMPANY_QUERY_KEYS.byId(id),
  query: () => getCompanyById(id),
}))

export const companiesQuery = defineQueryOptions(
  ({ filters }: { filters: { page: number; size: number; name: string } }) => ({
    key: COMPANY_QUERY_KEYS.withFilters(filters),
    query: () => getCompanies(filters),
  }),
)
