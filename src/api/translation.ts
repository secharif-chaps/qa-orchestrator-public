/**
 * API functions for translation management.
 */

import { apiClient } from './client'

export interface TranslationLanguage {
  code: string
  name: string
}

export interface LanguageTranslationStatus {
  language_name: string
  status: 'complete' | 'partial' | 'none'
  fields_translated: number
  fields_total: number
  percentage: number
}

export interface CompanyTranslationStatus {
  company_id: number
  translations: Record<string, LanguageTranslationStatus>
}

export interface TranslateResponse {
  company_id: number
  language_code: string
  fields_queued: number
  message: string
}

/**
 * Get available translation languages.
 *
 * @returns List of available language codes and names
 */
export async function getTranslationLanguages(): Promise<TranslationLanguage[]> {
  return apiClient.get<TranslationLanguage[]>('/translation/list')
}

/**
 * Get translation status for a company.
 *
 * @param companyId - Company ID to check status for
 * @returns Translation status for all languages
 */
export async function getCompanyTranslationStatus(
  companyId: number | string,
): Promise<CompanyTranslationStatus> {
  return apiClient.get<CompanyTranslationStatus>(`/translation/status/${companyId}`)
}

/**
 * Request translation of a company to a specific language.
 *
 * @param companyId - Company ID to translate
 * @param languageCode - Target language code
 * @returns Translation request response
 */
export async function requestTranslation(
  companyId: number | string,
  languageCode: string,
): Promise<TranslateResponse> {
  return apiClient.post<TranslateResponse>(`/translation/translate/${companyId}`, {
    language_code: languageCode,
  })
}
