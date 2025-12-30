import type { TaskResponse } from './task'

export interface CompanyCreate {
  name: string
  website: string
  owner_username: string
}

export interface CompanyUpdate {
  name?: string | null
  website?: string | null
  profile?: Record<string, any> | null
  digital?: Record<string, any> | null
  timeline?: Record<string, any> | null
  products?: Record<string, any> | null
  jobs?: Record<string, any> | null
  csr?: Record<string, any> | null
  press?: Record<string, any> | null
  team?: Record<string, any>[] | null
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

  owner_username: string
  organization_id: string

  // Optional folder information (populated for recent companies)
  folder_id?: string
  folder_name?: string

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
      overallStrategy: string
      digitalTransformation: string
      eCommerceCapabilities: string
      mobileStrategy: string
      digitalMarketingApproach: string
    }>
    onlineServices?: SourcedValue<
      {
        name: string
        description: string
      }[]
    >
    socialMediaAccounts?: {
      platform: string
      url: string
    }[]

    loyaltyProgram?: SourcedValue<string>
  }

  timeline: {
    insights?: string

    events?: {
      date: string
      title: string
      description: string
      category: string
      location: string
      impact: string
      source: string
    }[]
  }

  products?: {
    insights?: string
    customerType?: string
    marketingPositioning?: string
    range?: SourcedValue<string>[]
    partnerBrands?: SourcedValue<string>[]
    privateLabels?: SourcedValue<string>[]
    categories?: {
      [key: string]: string[]
    }
  }

  jobs: {
    offers?: {
      title: string
      location: string
      department: string
      description: string
      requirements: string
      posted_date: string
    }[]
    insights?: {
      total_openings: SourcedValue<number>
      top_departments: SourcedValue<string[]>
      hiring_focus: SourcedValue<string>
      growth_indicators: SourcedValue<string>
    }
  }

  csr: {
    insights?: string
    responsibility?: string
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

  // Raw knowledge fields (debug/admin only)
  raw_mistral_knowledge?: string | null
  raw_claude_knowledge?: string | null
  raw_wikipedia_knowledge?: string | null
  raw_scraped_website_knowledge?: string | null
}
