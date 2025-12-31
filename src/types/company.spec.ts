/**
 * TypeScript Interface Compatibility Tests
 *
 * These are compile-time type tests that verify the frontend interfaces
 * match the expected API response structure from the backend.
 *
 * If this file compiles without errors, the interfaces are correct.
 * Run: pnpm run type-check
 */

import type {
  SourcedValue,
  Company,
  TeamMember,
  PressItem,
} from './company'

// =============================================================================
// Test 1: Company interface matches API response
// =============================================================================

/**
 * Mock API response structure matching backend CompanyResponse schema.
 * This represents the actual JSON structure returned by the API.
 * If this compiles, the Company interface matches the expected API structure.
 */
const mockApiResponse: Company = {
  id: 123,
  name: 'LVMH',
  website: 'https://lvmh.com',
  tasks: [],
  created_at: '2024-12-29T10:00:00Z',
  updated_at: '2024-12-29T10:00:00Z',
  created_by: 'admin',
  updated_by: 'admin',
  owner_username: 'admin',
  organization_id: 'org-123',
  profile: {
    insights: {
      value: 'AI-generated company summary',
      source: 'Chaps-e',
    },
    groupName: {
      value: 'LVMH Group',
      source: 'https://wikipedia.org/wiki/LVMH',
    },
    businessLine: {
      value: 'Luxury goods and fashion retail',
      source: 'https://lvmh.com/about',
    },
    catchphrase: {
      value: 'The art of living',
      source: 'https://lvmh.com',
    },
    establishmentYear: {
      value: '1987',
      source: 'https://wikipedia.org/wiki/LVMH',
    },
    employeeCount: {
      value: '196,000',
      source: 'https://lvmh.com/investors',
    },
    revenue: {
      value: '79.2B EUR',
      source: 'https://lvmh.com/investors',
    },
    ceo: {
      value: 'Bernard Arnault',
      source: 'https://wikipedia.org/wiki/Bernard_Arnault',
    },
    hq: {
      value: 'Paris, France',
      source: 'https://lvmh.com/contact',
    },
  },
  digital: {
    insights: 'Strong digital presence with omnichannel strategy',
    digitalStrategy: {
      value: {
        overallStrategy: { value: 'Omnichannel approach', source: 'https://lvmh.com/digital' },
        digitalTransformation: { value: 'Heavy AR/VR investment', source: 'https://lvmh.com/digital' },
        eCommerceCapabilities: { value: 'Full platform', source: 'https://lvmh.com/digital' },
        mobileStrategy: { value: 'Native apps', source: 'https://lvmh.com/digital' },
        digitalMarketingApproach: { value: 'Influencer partnerships', source: 'https://lvmh.com/digital' },
      },
      source: 'https://lvmh.com/digital',
    },
    onlineServices: {
      value: { services: [{ name: 'Virtual Try-On', description: 'AR-powered try-on' }] },
      source: 'https://lvmh.com/features',
    },
    socialMediaAccounts: [
      { platform: 'Instagram', url: 'https://instagram.com/lvmh' },
    ],
    loyaltyProgram: {
      value: 'VIP membership program',
      source: 'https://lvmh.com/vip',
    },
  },
  timeline: {
    insights: 'Rich history spanning over 150 years',
    events: [
      {
        date: '1987',
        title: 'Company Founded',
        description: 'Merger of Louis Vuitton and Moet Hennessy',
        category: 'Foundation',
        location: 'Paris, France',
        impact: 'Created largest luxury conglomerate',
        source: 'https://wikipedia.org/wiki/LVMH',
      },
    ],
  },
  products: {
    insights: 'Diverse product portfolio',
    customerType: { value: 'High-net-worth individuals', source: 'https://lvmh.com/customers' },
    marketingPositioning: { value: 'Premium luxury positioning', source: 'https://lvmh.com/brand' },
    range: [
      { value: 'Leather Goods Collection', source: 'https://lvmh.com/products' },
    ],
    partnerBrands: [
      { value: 'Tiffany & Co.', source: 'https://lvmh.com/brands' },
    ],
    privateLabels: [
      { value: 'Maison Francis Kurkdjian', source: 'https://lvmh.com/brands' },
    ],
    categories: {
      Fashion: ['Clothing', 'Accessories', 'Footwear'],
    },
  },
  jobs: {
    insights: {
      total_openings: { value: 250, source: 'https://careers.lvmh.com' },
      top_departments: {
        value: ['Retail', 'Digital', 'Marketing'],
        source: 'https://careers.lvmh.com',
      },
      hiring_focus: { value: 'Digital capabilities expansion', source: 'Chaps-e' },
      growth_indicators: { value: '50% increase in tech roles', source: 'Chaps-e' },
    },
    offers: [
      {
        title: 'Senior Software Engineer',
        location: 'Paris, France',
        department: 'Digital Technology',
        description: 'Lead e-commerce development',
        requirements: '5+ years Python experience',
        posted_date: '2024-12-15',
      },
    ],
  },
  csr: {
    insights: 'Strong commitment to CSR',
    responsibility: 'Carbon neutrality by 2030',
    responsibility_initiatives: [
      { value: 'Net zero emissions target', source: 'https://lvmh.com/sustainability' },
    ],
    charity_actions: [
      { value: 'Annual 5M EUR to arts foundations', source: 'https://lvmh.com/foundation' },
    ],
    sustainability_programs: [
      { value: '100% renewable energy by 2025', source: 'https://lvmh.com/sustainability' },
    ],
    community_involvement: [],
    diversity_inclusion: [
      { value: '50% women in leadership', source: 'https://lvmh.com/diversity' },
    ],
    ethical_practices: [],
    awards_certifications: [],
  },
  press: {
    insights: 'Significant press coverage',
    articles: [
      { value: 'Record Q4 earnings', source: 'https://reuters.com/article/lvmh' },
    ],
    press_releases: [],
    media_mentions: [],
    awards_recognition: [
      { value: 'Most Innovative Luxury Company 2024', source: 'https://luxuryawards.com/2024' },
    ],
    product_launches: [],
    executive_interviews: [],
    financial_news: [
      { value: 'Strong Asian market growth', source: 'https://bloomberg.com/lvmh' },
    ],
    partnership_announcements: [
      { value: 'Partnership with Apple for AR', source: 'https://techcrunch.com/lvmh-apple' },
    ],
  },
  team: [
    {
      position: 'Chairman and CEO',
      firstName: 'Bernard',
      lastName: 'Arnault',
      linkedinUrl: 'https://linkedin.com/in/bernard-arnault',
      subordinates: [
        {
          position: 'Group Managing Director',
          firstName: 'Antonio',
          lastName: 'Belloni',
          subordinates: [],
        },
      ],
    },
  ],
}

// =============================================================================
// Test 2: SourcedValue usage is consistent
// =============================================================================

// SourcedValue with string
const sourcedString: SourcedValue<string> = {
  value: 'Test Value',
  source: 'https://example.com',
}

// SourcedValue with optional favicon
const sourcedWithFavicon: SourcedValue<string> = {
  value: 'Test Value',
  source: 'https://example.com',
  favicon: 'https://example.com/favicon.ico',
}

// SourcedValue with optional value_fr (translation placeholder)
const sourcedWithTranslation: SourcedValue<string> = {
  value: 'Business consulting',
  source: 'https://example.com',
  value_fr: 'Conseil aux entreprises',
}

// SourcedValue with number
const sourcedNumber: SourcedValue<number> = {
  value: 250,
  source: 'https://example.com',
}

// SourcedValue with array
const sourcedArray: SourcedValue<string[]> = {
  value: ['Retail', 'Digital', 'Marketing'],
  source: 'https://example.com',
}

// SourcedValue with object
const sourcedObject: SourcedValue<{ name: string }> = {
  value: { name: 'Test' },
  source: 'https://example.com',
}

// =============================================================================
// Test 3: Press items use new source format (singular, not array)
// =============================================================================

// PressItem with singular source (correct)
const pressItem: PressItem = {
  value: 'Record Q4 earnings driven by Asia growth',
  source: 'https://reuters.com/article/lvmh-earnings',
}

// PressItem with optional value_fr
const pressItemWithTranslation: PressItem = {
  value: 'Record earnings in Q4',
  source: 'https://reuters.com',
  value_fr: 'Resultats records au T4',
}

// This line will cause a TypeScript error if sources (array) is used instead of source (string)
// Uncommenting the following would cause a compile error:
// const invalidPressItem: PressItem = { value: 'Test', sources: ['https://example.com'] }

// =============================================================================
// Test 4: Team hierarchy structure works
// =============================================================================

// Flat team member
const flatMember: TeamMember = {
  position: 'CEO',
  firstName: 'John',
  lastName: 'Doe',
}

// Team member with LinkedIn
const memberWithLinkedin: TeamMember = {
  position: 'CEO',
  firstName: 'John',
  lastName: 'Doe',
  linkedinUrl: 'https://linkedin.com/in/johndoe',
}

// Nested team hierarchy (adjacency list reconstructed as tree)
const ceoWithHierarchy: TeamMember = {
  position: 'Chairman and CEO',
  firstName: 'Bernard',
  lastName: 'Arnault',
  subordinates: [
    {
      position: 'Group Managing Director',
      firstName: 'Antonio',
      lastName: 'Belloni',
      subordinates: [
        {
          position: 'CFO',
          firstName: 'Jean',
          lastName: 'Jacques',
          subordinates: [],
        },
      ],
    },
    {
      position: 'COO',
      firstName: 'Marie',
      lastName: 'Dupont',
    },
  ],
}

// =============================================================================
// Test 5: Optional fields work correctly
// =============================================================================

// Company with optional folder fields
const companyWithFolder: Company = {
  ...mockApiResponse,
  folder_id: 'folder-123',
  folder_name: 'My Folder',
}

// Company with raw knowledge fields
const companyWithRaw: Company = {
  ...mockApiResponse,
  raw_mistral_knowledge: 'Mistral AI response',
  raw_claude_knowledge: 'Claude AI response',
  raw_wikipedia_knowledge: 'Wikipedia data',
  raw_scraped_website_knowledge: 'Scraped content',
}

// =============================================================================
// Export to prevent "unused variable" warnings
// =============================================================================
export {
  mockApiResponse,
  sourcedString,
  sourcedWithFavicon,
  sourcedWithTranslation,
  sourcedNumber,
  sourcedArray,
  sourcedObject,
  pressItem,
  pressItemWithTranslation,
  flatMember,
  memberWithLinkedin,
  ceoWithHierarchy,
  companyWithFolder,
  companyWithRaw,
}
