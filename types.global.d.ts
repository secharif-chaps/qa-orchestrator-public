// Types pour la structure avec sources
export type SourcedValue<T> = {
  value: T;
  source: string;
};

export type Company = {
  meta?: {
    company_name: string;
    query_date: string;
  };
  insights: SourcedValue<string>;
  profile: {
    name: SourcedValue<string>;
    website: SourcedValue<string>;
    group_name?: SourcedValue<string>;
    business_line: SourcedValue<string>;
    catchphrase: SourcedValue<string>;
    establishment_year: SourcedValue<string>;
    employee_count: SourcedValue<string>;
    revenue: SourcedValue<string>;
    ceo: SourcedValue<string>;
    hq: SourcedValue<string>;
  };
  products_and_services: {
    product_range: SourcedValue<string>[];
    partner_brands: SourcedValue<string>[];
    private_labels: SourcedValue<string>[];
  };
  target_audience_and_customer_base: {
    customer_type: SourcedValue<string>;
    marketing_positioning: SourcedValue<string>;
  };
  digital_strategy_and_social_media: {
    digital_strategy: SourcedValue<string>;
    loyalty_program: SourcedValue<string>;
    online_services: SourcedValue<string>[];
  };
  csr: {
    responsibility_initiatives: SourcedValue<string>[];
    charity_actions: SourcedValue<string>[];
  };
  recent_news: SourcedValue<string>[];
  social_media: {
    name: string;
    url: SourcedValue<string>;
  }[];
  timeline_events?: {
    date: string;
    title: string;
    description: string;
    category: string;
    location: string;
    impact: string;
    source: string;
  }[];
  products?: {
    [key: string]: string[];
  };
  job_offers?: {
    title: SourcedValue<string>;
    location: SourcedValue<string>;
    department: SourcedValue<string>;
    description: SourcedValue<string>;
    requirements: SourcedValue<string>;
    posted_date: SourcedValue<string>;
  }[];
  job_offers_insights?: {
    total_openings: SourcedValue<number>;
    top_departments: SourcedValue<string[]>;
    hiring_focus: SourcedValue<string>;
    growth_indicators: SourcedValue<string>;
  };
};