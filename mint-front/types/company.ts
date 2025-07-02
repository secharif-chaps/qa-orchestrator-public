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

export interface CompanyResponse {
  id: number
  name: string
  website: string
  owner_username: string
  profile?: Record<string, any>
  digital?: Record<string, any>
  timeline?: Record<string, any>
  products?: Record<string, any>
  jobs?: Record<string, any>
  csr?: Record<string, any>
  press?: Record<string, any>
  team?: Record<string, any>[]
  error?: string | null
  created_at: string
  updated_at: string
  tasks: TaskResponse[]
}

// Types pour la structure avec sources
export type SourcedValue<T> = {
  value: T
  source: string
}

export interface TeamMember {
  position: string
  firstName: string
  lastName: string
  subordinates?: TeamMember[]
}

export interface Company {
  id?: number
  pending?: boolean
  error?: string
  name: string
  website: string

  tasks: TaskResponse[]

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
    strategy: SourcedValue<string>
    loyaltyProgram: SourcedValue<string>
    onlineServices: SourcedValue<string>[]
    socialMedia: {
      name: string
      url: SourcedValue<string>
    }[]
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
    responsibility_initiatives: SourcedValue<string>[]
    charity_actions: SourcedValue<string>[]
  }
  press: {
    articles: SourcedValue<string>[]
  }

  team?: TeamMember[]
}
