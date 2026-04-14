import type { TaskResponse } from './task'

export interface CompanyCreate {
  name: string
  website: string
  owner_username: string
}

export interface CompanyUpdate {
  name?: string | null
  website?: string | null
  profile?: Record<string, unknown> | null
  digital?: Record<string, unknown> | null
  timeline?: Record<string, unknown> | null
  products?: Record<string, unknown> | null
  jobs?: Record<string, unknown> | null
  csr?: Record<string, unknown> | null
  press?: Record<string, unknown> | null
  team?: Record<string, unknown>[] | null
}

/**
 * Generic type for values with source attribution.
 * Matches backend SourcedValue[T] Pydantic schema.
 *
 * @template T - The type of the value field (string, number, array, object)
 *
 * @property value - The actual value of type T
 * @property source - Source URL or tool name (e.g., "Chaps-e", "mistral", URL)
 * @property favicon - Optional favicon URL for the source
 * @property value_fr - Optional French translation placeholder (future i18n support)
 */
export type SourcedValue<T> = {
  value: T
  source: string
  favicon?: string
  value_fr?: string
}

/**
 * Press item interface for normalized press data.
 * Uses singular 'source' field instead of the old 'sources[]' array.
 *
 * This change aligns with the backend database normalization where each
 * press item has a single source reference.
 */
export interface PressItem {
  value: string
  source: string
  value_fr?: string
}

export interface CorporateEntity {
  name: string
  country?: string
  source?: string
}

export interface SanctionItem {
  entity_name: string
  country?: string
  sanction_type?: string
  date?: string
  description?: string
  sanction_nature?: string
  source_code?: string
  risk_level?: string
  risk_justification?: string
  is_onu_eu_ofac?: boolean
  weblinks?: Array<{ uri: string; caption?: string | null; date?: string | null }>
}

export interface SanctionsData {
  overall_risk_level?: string
  overall_risk_justification?: string
  insights?: string
  items?: SanctionItem[]
}

export interface FinancialMetric {
  metricName: string
  period: string
  value: string | null
  unit: string | null
  source: string | null
}

export interface FundingRound {
  roundType: string | null
  amount: string | null
  date: string | null
  leadInvestor: string | null
  valuation: string | null
  source: string | null
}

export interface FinancialData {
  insights: SourcedValue<string> | null
  companyType: SourcedValue<string> | null
  tickerSymbol: SourcedValue<string> | null
  stockExchange: SourcedValue<string> | null
  currency: SourcedValue<string> | null
  revenue: SourcedValue<string> | null
  revenueGrowth: SourcedValue<string> | null
  grossMargin: SourcedValue<string> | null
  ebitdaMargin: SourcedValue<string> | null
  netMargin: SourcedValue<string> | null
  marketCap: SourcedValue<string> | null
  enterpriseValue: SourcedValue<string> | null
  peRatio: SourcedValue<string> | null
  evEbitda: SourcedValue<string> | null
  evRevenue: SourcedValue<string> | null
  totalFunding: SourcedValue<string> | null
  lastValuation: SourcedValue<string> | null
  debtToEquity: SourcedValue<string> | null
  freeCashFlow: SourcedValue<string> | null
  metrics: FinancialMetric[]
  fundingRounds: FundingRound[]
}

export interface TeamMember {
  position: string
  firstName: string
  lastName: string
  linkedinUrl?: string
  subordinates?: TeamMember[]
}

export interface Company {
  id?: number
  pending?: boolean
  error?: string
  name: string
  website: string
  tasks: TaskResponse[]

  created_at: string
  updated_at: string
  created_by: string
  updated_by: string

  owner_id?: string // Keycloak user UUID
  owner_username: string
  organization_id: string

  // Optional folder information (populated for recent companies)
  folder_id?: string
  folder_name?: string
  folder_is_owner?: boolean | null
  folder_share_role?: string | null

  profile: {
    // Added insights field with SourcedValue pattern
    insights?: SourcedValue<string>
    groupName?: SourcedValue<string>
    businessLine?: SourcedValue<string>
    catchphrase?: SourcedValue<string>
    establishmentYear?: SourcedValue<string>
    employeeCount?: SourcedValue<string>
    revenue?: SourcedValue<string>
    ceo?: SourcedValue<string>
    hq?: SourcedValue<string>
  }

  digital: {
    insights?: string
    digitalStrategy?: SourcedValue<{
      overallStrategy?: SourcedValue<string>
      digitalTransformation?: SourcedValue<string>
      eCommerceCapabilities?: SourcedValue<string>
      mobileStrategy?: SourcedValue<string>
      digitalMarketingApproach?: SourcedValue<string>
    }>
    onlineServices?: SourcedValue<{
      services: {
        name: string
        description: string
      }[]
    }>
    socialMediaAccounts?: {
      platform: string
      url: string
      source?: string
    }[]

    loyaltyProgram?: SourcedValue<string>
  }

  timeline: {
    insights?: string

    events?: {
      date: string | SourcedValue<string>
      title: string | SourcedValue<string>
      description: string | SourcedValue<string>
      category: string | SourcedValue<string>
      location?: string | SourcedValue<string>
      impact?: string | SourcedValue<string>
      source?: string
    }[]
  }

  products?: {
    insights?: string
    customerType?: SourcedValue<string>
    marketingPositioning?: SourcedValue<string>
    range?: SourcedValue<string>[]
    partnerBrands?: SourcedValue<string>[]
    privateLabels?: SourcedValue<string>[]
    categories?: {
      [key: string]: string[]
    }
  }

  jobs: {
    offers?: {
      title?: string | SourcedValue<string>
      location?: string | SourcedValue<string>
      department?: string | SourcedValue<string>
      description?: string | SourcedValue<string>
      requirements?: string | SourcedValue<string>
      posted_date?: string | SourcedValue<string>
      source?: string
    }[]
    insights?: {
      total_openings: SourcedValue<number>
      top_departments: SourcedValue<string[] | string>
      hiring_focus: SourcedValue<string>
      growth_indicators: SourcedValue<string>
    }
  }

  csr: {
    insights?: string
    responsibility?: string | SourcedValue<string>
    responsibility_initiatives?: SourcedValue<string>[]
    charity_actions?: SourcedValue<string>[]
    sustainability_programs?: SourcedValue<string>[]
    community_involvement?: SourcedValue<string>[]
    diversity_inclusion?: SourcedValue<string>[]
    ethical_practices?: SourcedValue<string>[]
    awards_certifications?: SourcedValue<string>[]
  }

  /**
   * Press section with normalized press items.
   * BREAKING CHANGE: Press items now use singular 'source' field instead of 'sources[]' array.
   * This aligns with the backend database normalization.
   */
  press: {
    insights?: string
    articles?: PressItem[]
    press_releases?: PressItem[]
    media_mentions?: PressItem[]
    awards_recognition?: PressItem[]
    product_launches?: PressItem[]
    executive_interviews?: PressItem[]
    financial_news?: PressItem[]
    partnership_announcements?: PressItem[]
  }

  team?: TeamMember[]

  corporate_structure: {
    parents?: CorporateEntity[]
    subsidiaries?: CorporateEntity[]
    affiliates?: CorporateEntity[]
    branches?: CorporateEntity[]
    regional_entities?: CorporateEntity[]
  }

  sanctions?: SanctionsData

  financial?: FinancialData

  // Raw knowledge fields (debug/admin only)
  raw_mistral_knowledge?: string | null
  raw_gpt_knowledge?: string | null
  raw_wikipedia_knowledge?: string | null
  raw_scraped_website_knowledge?: string | null
}
