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
  pending?: boolean
  error?: string
  name: string
  website: string

  pending_states: {
    [key: string]: {
      pending: boolean
      error?: string
    }
  }

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
