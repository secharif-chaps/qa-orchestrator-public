export type Company = {
  insights: string;
  profile: {
    name: string;
    website: string;
    group_name?: string;
    business_line: string;
    catchphrase: string;
    establishment_year: string;
    employee_count: string;
    revenue: string;
  };
  products_and_services: {
    product_range: string[];
    partner_brands: string[];
    private_labels: string[];
  };
  target_audience_and_customer_base: {
    customer_type: string;
    marketing_positioning: string;
  };
  digital_strategy_and_social_media: {
    digital_strategy: string;
    loyalty_program: string;
    online_services: string[];
  };
  csr: {
    responsibility_initiatives: string[];
    charity_actions: string[];
  };
  recent_news: string[];
  social_media: {
    name: string;
    url: string;
  }[];
  partners_and_competitors_graph: {
    nodes: {
      id: string;
      name: string;
      type: 'company' | 'partner' | 'competitor';
      description: string;
    }[];
    edges: {
      source: string;
      target: string;
      relation_type: 'partnership' | 'competition';
      details: string;
    }[];
  };
};
