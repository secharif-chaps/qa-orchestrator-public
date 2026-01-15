/**
 * Pinia Colada queries for translation management.
 */

import { defineQueryOptions } from '@pinia/colada'
import {
  getTranslationLanguages,
  getCompanyTranslationStatus,
  getTranslationJob,
} from '@/api/translation'

/**
 * Query keys for translation operations.
 */
export const TRANSLATION_QUERY_KEYS = {
  root: ['translation'] as const,
  languages: () => [...TRANSLATION_QUERY_KEYS.root, 'languages'] as const,
  status: (companyId: string | number) =>
    [...TRANSLATION_QUERY_KEYS.root, 'status', String(companyId)] as const,
  job: (jobId: number) => [...TRANSLATION_QUERY_KEYS.root, 'job', jobId] as const,
}

/**
 * Query for available translation languages.
 *
 * @example
 * const { data: languages, isLoading } = useQuery(translationLanguagesQuery)
 */
export const translationLanguagesQuery = defineQueryOptions(() => ({
  key: TRANSLATION_QUERY_KEYS.languages(),
  query: () => getTranslationLanguages(),
}))

/**
 * Query for company translation status.
 *
 * @example
 * const { data: status } = useQuery(companyTranslationStatusQuery, () => ({ companyId: 123 }))
 */
export const companyTranslationStatusQuery = defineQueryOptions(
  ({ companyId }: { companyId: string | number }) => ({
    key: TRANSLATION_QUERY_KEYS.status(companyId),
    query: () => getCompanyTranslationStatus(companyId),
  }),
)

/**
 * Query for translation job progress.
 * Use refetchInterval for polling during active jobs.
 *
 * @example
 * const { data: job } = useQuery(translationJobQuery, () => ({ jobId: 123 }), {
 *   refetchInterval: 2000, // Poll every 2 seconds
 * })
 */
export const translationJobQuery = defineQueryOptions(({ jobId }: { jobId: number }) => ({
  key: TRANSLATION_QUERY_KEYS.job(jobId),
  query: () => getTranslationJob(jobId),
}))
