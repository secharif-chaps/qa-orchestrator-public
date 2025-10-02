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

// Types pour la structure avec sources
export type SourcedValue<T> = {
  value: T
  source: string
  favicon?: string
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
  workspace_id: number

  // Optional folder information (populated for recent companies)
  folder_id?: string
  folder_name?: string

  profile: {
    groupName?: SourcedValue<string>
    businessLine: SourcedValue<string>
    catchphrase: SourcedValue<string>
    establishmentYear: SourcedValue<string>
    employeeCount: SourcedValue<string>
    revenue: SourcedValue<string>
    ceo: SourcedValue<string>
    hq: SourcedValue<string>
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
    customerType: string
    marketingPositioning: string
    range: SourcedValue<string>[]
    partnerBrands: SourcedValue<string>[]
    privateLabels: SourcedValue<string>[]
    categories: {
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
    responsibility: string
    responsibility_initiatives: SourcedValue<string>[]
    charity_actions: SourcedValue<string>[]
    sustainability_programs: SourcedValue<string>[]
    community_involvement: SourcedValue<string>[]
    diversity_inclusion: SourcedValue<string>[]
    ethical_practices: SourcedValue<string>[]
    awards_certifications: SourcedValue<string>[]
  }

  press: {
    insights?: string
    articles?: {
      value: string
      sources: string[]
    }[]
    press_releases?: {
      value: string
      sources: string[]
    }[]
    media_mentions?: {
      value: string
      sources: string[]
    }[]
    awards_recognition?: {
      value: string
      sources: string[]
    }[]
    product_launches?: {
      value: string
      sources: string[]
    }[]
    executive_interviews?: {
      value: string
      sources: string[]
    }[]
    financial_news?: {
      value: string
      sources: string[]
    }[]
    partnership_announcements?: {
      value: string
      sources: string[]
    }[]
  }

  team?: TeamMember[]
}
