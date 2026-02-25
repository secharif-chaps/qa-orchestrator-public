# Specification: Company Data Structure Refactoring

## Goal

Transform company data storage from 8 JSON columns to a normalized relational database structure with typed schemas, consistent SourcedValue pattern across all fields, and translation-ready columns for future i18n support.

## User Stories

- As a developer, I want type-safe company data access so that I can avoid runtime errors from untyped JSON structures
- As a product owner, I want source tracking on every user-facing field so that users know where information came from

## Specific Requirements

**SourcedValue Pattern Definition**
- Every user-facing text field follows the pattern: `field_name`, `field_name_source`, `field_name_value_fr`
- Source contains: URL for scraped data, tool name (e.g., "mistral", "perplexity") for AI tools, "Chaps-e" for AI-generated insights
- Translation columns (`_value_fr`) are placeholders only - populated in future translation spec
- Fields that are proper nouns (names, brands, locations) do NOT get translation columns
- Fields that are numbers (year, count, revenue) do NOT get translation columns

**Database Schema - Main Company Table**
- Remove 8 JSON columns: `profile`, `digital`, `timeline`, `products`, `jobs`, `csr`, `press`, `team`
- Keep core fields: `id`, `name`, `website`, `owner_id`, `owner_username`, `organization_id`, `created_at`, `updated_at`, `is_deleted`, `error`
- Keep raw knowledge fields: `raw_mistral_knowledge`, `raw_claude_knowledge`, `raw_wikipedia_knowledge`, `raw_scraped_website_knowledge`
- Keep task relationship unchanged

**1:1 Section Tables**
- `company_profile`: insights, group_name, business_line, catchphrase, establishment_year, employee_count, revenue, ceo, hq - each with SourcedValue pattern (translation columns only where applicable)
- `company_digital`: insights, overall_strategy, digital_transformation, ecommerce_capabilities, mobile_strategy, digital_marketing_approach, loyalty_program
- `company_timeline`: insights only (events moved to 1:N table)
- `company_products`: insights, customer_type, marketing_positioning
- `company_jobs`: insights broken into total_openings, top_departments, hiring_focus, growth_indicators
- `company_csr`: insights, responsibility
- `company_press`: insights only (items moved to 1:N table)

**1:N Child Tables**
- `company_online_services`: id, company_id FK, name, description with SourcedValue
- `company_social_media_accounts`: id, company_id FK, platform, url (no translation)
- `company_timeline_events`: id, company_id FK, date, title, description, category, location, impact with SourcedValue
- `company_product_items`: id, company_id FK, type ENUM('range', 'partner_brand', 'private_label'), value with source, value_fr only for 'range' type
- `company_product_categories`: id, company_id FK, category_name with translation, items TEXT[] with items_value_fr TEXT[]
- `company_job_offers`: id, company_id FK, title, location, department, description, requirements, posted_date with SourcedValue
- `company_csr_initiatives`: id, company_id FK, type ENUM (7 types), value with SourcedValue
- `company_press_items`: id, company_id FK, type ENUM (8 types), value with SourcedValue
- `company_team_members`: id, company_id FK, parent_id FK (nullable, for adjacency list hierarchy), position, first_name, last_name, linkedin_url with SourcedValue

**Team Hierarchy Using Adjacency List**
- CEO has `parent_id = NULL`, direct reports have `parent_id` pointing to CEO's id
- Recursive queries needed to build tree structure
- No circular reference validation required (data comes from Dify)
- Frontend reconstructs tree from flat list using parent_id

**SQLAlchemy Models**
- One model per table with proper relationships defined
- Use `back_populates` for bidirectional relationships
- 1:1 tables use `uselist=False` in relationship
- All models include `created_at`, `updated_at` timestamps
- Use proper SQLAlchemy Enums for type columns

**Pydantic Schemas**
- Define `SourcedValue[T]` generic schema with `value`, `source`, `value_fr` fields
- Create typed schemas for each section (replace `Dict[str, Any]`)
- Response schemas maintain frontend-compatible structure
- Validation on source field (must be URL or known tool name or "Chaps-e")

## Visual Design

No visual mockups provided - this is a backend-focused refactoring with minimal frontend changes.

## Existing Code to Leverage

**`back/app/models/company.py`**
- Current Company model structure to extend
- Relationship pattern with Task model to replicate for new tables
- Keycloak user reference pattern (owner_id, owner_username) already established

**`back/app/schemas/company.py`**
- CompanyBase, CompanyCreate, CompanyUpdate patterns to extend
- Validator patterns for name/website to reuse
- CompanyResponse structure - new schemas should produce compatible output

**`front/src/types/company.ts`**
- `SourcedValue<T>` generic already defined - backend schemas should match
- Current Company interface shows expected field structure
- TeamMember interface with `subordinates[]` hierarchy - frontend already handles tree

**`back/app/api/endpoints/webhooks.py`**
- `dify_task_callback` function shows how Dify data is received
- `_update_company_data` method in CompanyService must be refactored to save to new tables
- Current parsing logic for data_collection vs other task types

**`back/app/services/company.py`**
- `_parse_json_fields` helper must be removed/refactored after migration
- `_update_company_data` method must save to normalized tables instead of JSON columns
- Create/Update company logic needs minimal changes

## Out of Scope

- Translation population logic (future spec)
- Translation API endpoint (future spec)
- Language selection in frontend (future spec)
- Any language other than French for translation placeholders
- Lazy migration (all data migrates at once)
- Historical data versioning
- Search/filtering across normalized fields (future optimization)
- Performance indexing beyond foreign keys (optimize later based on usage)

---

## Appendix A: Complete Database Schema

### Main Table

```sql
companies
  id SERIAL PRIMARY KEY
  name VARCHAR NOT NULL
  website VARCHAR NOT NULL
  owner_id VARCHAR NOT NULL
  owner_username VARCHAR
  organization_id VARCHAR NOT NULL
  created_at TIMESTAMPTZ DEFAULT now()
  updated_at TIMESTAMPTZ DEFAULT now()
  is_deleted BOOLEAN DEFAULT FALSE
  error VARCHAR
  raw_mistral_knowledge TEXT
  raw_claude_knowledge TEXT
  raw_wikipedia_knowledge TEXT
  raw_scraped_website_knowledge TEXT
```

### 1:1 Section Tables

```sql
company_profile
  company_id INTEGER PRIMARY KEY REFERENCES companies(id) ON DELETE CASCADE
  insights TEXT
  insights_source TEXT
  insights_value_fr TEXT
  group_name TEXT
  group_name_source TEXT
  business_line TEXT
  business_line_source TEXT
  business_line_value_fr TEXT
  catchphrase TEXT
  catchphrase_source TEXT
  catchphrase_value_fr TEXT
  establishment_year TEXT
  establishment_year_source TEXT
  employee_count TEXT
  employee_count_source TEXT
  revenue TEXT
  revenue_source TEXT
  ceo TEXT
  ceo_source TEXT
  hq TEXT
  hq_source TEXT
  created_at TIMESTAMPTZ DEFAULT now()
  updated_at TIMESTAMPTZ DEFAULT now()

company_digital
  company_id INTEGER PRIMARY KEY REFERENCES companies(id) ON DELETE CASCADE
  insights TEXT
  insights_source TEXT
  insights_value_fr TEXT
  overall_strategy TEXT
  overall_strategy_source TEXT
  overall_strategy_value_fr TEXT
  digital_transformation TEXT
  digital_transformation_source TEXT
  digital_transformation_value_fr TEXT
  ecommerce_capabilities TEXT
  ecommerce_capabilities_source TEXT
  ecommerce_capabilities_value_fr TEXT
  mobile_strategy TEXT
  mobile_strategy_source TEXT
  mobile_strategy_value_fr TEXT
  digital_marketing_approach TEXT
  digital_marketing_approach_source TEXT
  digital_marketing_approach_value_fr TEXT
  loyalty_program TEXT
  loyalty_program_source TEXT
  loyalty_program_value_fr TEXT
  created_at TIMESTAMPTZ DEFAULT now()
  updated_at TIMESTAMPTZ DEFAULT now()

company_timeline
  company_id INTEGER PRIMARY KEY REFERENCES companies(id) ON DELETE CASCADE
  insights TEXT
  insights_source TEXT
  insights_value_fr TEXT
  created_at TIMESTAMPTZ DEFAULT now()
  updated_at TIMESTAMPTZ DEFAULT now()

company_products
  company_id INTEGER PRIMARY KEY REFERENCES companies(id) ON DELETE CASCADE
  insights TEXT
  insights_source TEXT
  insights_value_fr TEXT
  customer_type TEXT
  customer_type_source TEXT
  customer_type_value_fr TEXT
  marketing_positioning TEXT
  marketing_positioning_source TEXT
  marketing_positioning_value_fr TEXT
  created_at TIMESTAMPTZ DEFAULT now()
  updated_at TIMESTAMPTZ DEFAULT now()

company_jobs
  company_id INTEGER PRIMARY KEY REFERENCES companies(id) ON DELETE CASCADE
  insights_total_openings INTEGER
  insights_total_openings_source TEXT
  insights_top_departments TEXT
  insights_top_departments_source TEXT
  insights_top_departments_value_fr TEXT
  insights_hiring_focus TEXT
  insights_hiring_focus_source TEXT
  insights_hiring_focus_value_fr TEXT
  insights_growth_indicators TEXT
  insights_growth_indicators_source TEXT
  insights_growth_indicators_value_fr TEXT
  created_at TIMESTAMPTZ DEFAULT now()
  updated_at TIMESTAMPTZ DEFAULT now()

company_csr
  company_id INTEGER PRIMARY KEY REFERENCES companies(id) ON DELETE CASCADE
  insights TEXT
  insights_source TEXT
  insights_value_fr TEXT
  responsibility TEXT
  responsibility_source TEXT
  responsibility_value_fr TEXT
  created_at TIMESTAMPTZ DEFAULT now()
  updated_at TIMESTAMPTZ DEFAULT now()

company_press
  company_id INTEGER PRIMARY KEY REFERENCES companies(id) ON DELETE CASCADE
  insights TEXT
  insights_source TEXT
  insights_value_fr TEXT
  created_at TIMESTAMPTZ DEFAULT now()
  updated_at TIMESTAMPTZ DEFAULT now()
```

### 1:N Child Tables

```sql
company_online_services
  id SERIAL PRIMARY KEY
  company_id INTEGER NOT NULL REFERENCES companies(id) ON DELETE CASCADE
  name TEXT
  name_source TEXT
  name_value_fr TEXT
  description TEXT
  description_source TEXT
  description_value_fr TEXT
  created_at TIMESTAMPTZ DEFAULT now()
  INDEX idx_online_services_company_id (company_id)

company_social_media_accounts
  id SERIAL PRIMARY KEY
  company_id INTEGER NOT NULL REFERENCES companies(id) ON DELETE CASCADE
  platform TEXT
  platform_source TEXT
  url TEXT
  url_source TEXT
  created_at TIMESTAMPTZ DEFAULT now()
  INDEX idx_social_media_company_id (company_id)

company_timeline_events
  id SERIAL PRIMARY KEY
  company_id INTEGER NOT NULL REFERENCES companies(id) ON DELETE CASCADE
  date TEXT
  date_source TEXT
  title TEXT
  title_source TEXT
  title_value_fr TEXT
  description TEXT
  description_source TEXT
  description_value_fr TEXT
  category TEXT
  category_source TEXT
  category_value_fr TEXT
  location TEXT
  location_source TEXT
  impact TEXT
  impact_source TEXT
  impact_value_fr TEXT
  created_at TIMESTAMPTZ DEFAULT now()
  INDEX idx_timeline_events_company_id (company_id)

company_product_items
  id SERIAL PRIMARY KEY
  company_id INTEGER NOT NULL REFERENCES companies(id) ON DELETE CASCADE
  type product_item_type_enum NOT NULL  -- 'range', 'partner_brand', 'private_label'
  value TEXT
  value_source TEXT
  value_value_fr TEXT  -- Only populated for 'range' type
  created_at TIMESTAMPTZ DEFAULT now()
  INDEX idx_product_items_company_id (company_id)

company_product_categories
  id SERIAL PRIMARY KEY
  company_id INTEGER NOT NULL REFERENCES companies(id) ON DELETE CASCADE
  category_name TEXT
  category_name_value_fr TEXT
  items TEXT[]
  items_value_fr TEXT[]
  created_at TIMESTAMPTZ DEFAULT now()
  INDEX idx_product_categories_company_id (company_id)

company_job_offers
  id SERIAL PRIMARY KEY
  company_id INTEGER NOT NULL REFERENCES companies(id) ON DELETE CASCADE
  title TEXT
  title_source TEXT
  title_value_fr TEXT
  location TEXT
  location_source TEXT
  department TEXT
  department_source TEXT
  department_value_fr TEXT
  description TEXT
  description_source TEXT
  description_value_fr TEXT
  requirements TEXT
  requirements_source TEXT
  requirements_value_fr TEXT
  posted_date TEXT
  posted_date_source TEXT
  created_at TIMESTAMPTZ DEFAULT now()
  INDEX idx_job_offers_company_id (company_id)

company_csr_initiatives
  id SERIAL PRIMARY KEY
  company_id INTEGER NOT NULL REFERENCES companies(id) ON DELETE CASCADE
  type csr_initiative_type_enum NOT NULL
    -- 'responsibility', 'charity', 'sustainability', 'community', 'diversity', 'ethics', 'awards'
  value TEXT
  value_source TEXT
  value_value_fr TEXT
  created_at TIMESTAMPTZ DEFAULT now()
  INDEX idx_csr_initiatives_company_id (company_id)

company_press_items
  id SERIAL PRIMARY KEY
  company_id INTEGER NOT NULL REFERENCES companies(id) ON DELETE CASCADE
  type press_item_type_enum NOT NULL
    -- 'article', 'press_release', 'media_mention', 'award', 'product_launch', 'interview', 'financial', 'partnership'
  value TEXT
  value_source TEXT
  value_value_fr TEXT
  created_at TIMESTAMPTZ DEFAULT now()
  INDEX idx_press_items_company_id (company_id)

company_team_members
  id SERIAL PRIMARY KEY
  company_id INTEGER NOT NULL REFERENCES companies(id) ON DELETE CASCADE
  parent_id INTEGER REFERENCES company_team_members(id) ON DELETE SET NULL
  position TEXT
  position_source TEXT
  position_value_fr TEXT
  first_name TEXT
  first_name_source TEXT
  last_name TEXT
  last_name_source TEXT
  linkedin_url TEXT
  linkedin_url_source TEXT
  created_at TIMESTAMPTZ DEFAULT now()
  INDEX idx_team_members_company_id (company_id)
  INDEX idx_team_members_parent_id (parent_id)
```

---

## Appendix B: Dify Workflow JSON Format

Each Dify workflow must return data in the SourcedValue format. The webhook callback receives this data and saves it to the appropriate tables.

### Profile Task Output

```json
{
  "profile": {
    "insights": "AI-generated summary of the company profile...",
    "groupName": {
      "value": "LVMH",
      "source": "https://wikipedia.org/wiki/LVMH"
    },
    "businessLine": {
      "value": "Luxury goods and fashion retail",
      "source": "https://company.com/about"
    },
    "catchphrase": {
      "value": "The art of living",
      "source": "https://company.com"
    },
    "establishmentYear": {
      "value": "1987",
      "source": "https://wikipedia.org/wiki/LVMH"
    },
    "employeeCount": {
      "value": "196,000",
      "source": "https://company.com/investors"
    },
    "revenue": {
      "value": "79.2B EUR",
      "source": "https://company.com/investors"
    },
    "ceo": {
      "value": "Bernard Arnault",
      "source": "https://wikipedia.org/wiki/Bernard_Arnault"
    },
    "hq": {
      "value": "Paris, France",
      "source": "https://company.com/contact"
    }
  }
}
```

### Digital Task Output

```json
{
  "digital": {
    "insights": "The company has a strong digital presence with...",
    "digitalStrategy": {
      "overallStrategy": {
        "value": "Omnichannel approach combining luxury e-commerce...",
        "source": "https://company.com/digital"
      },
      "digitalTransformation": {
        "value": "Invested heavily in AR/VR experiences...",
        "source": "https://news.com/article/123"
      },
      "eCommerceCapabilities": {
        "value": "Full e-commerce platform with personalization...",
        "source": "https://company.com/shop"
      },
      "mobileStrategy": {
        "value": "Native apps for iOS and Android...",
        "source": "https://company.com/apps"
      },
      "digitalMarketingApproach": {
        "value": "Influencer partnerships and social media...",
        "source": "https://marketing-weekly.com/analysis"
      }
    },
    "onlineServices": [
      {
        "name": {
          "value": "Virtual Try-On",
          "source": "https://company.com/features"
        },
        "description": {
          "value": "AR-powered virtual try-on for accessories",
          "source": "https://company.com/features"
        }
      }
    ],
    "socialMediaAccounts": [
      {
        "platform": "Instagram",
        "url": "https://instagram.com/company",
        "source": "https://company.com"
      }
    ],
    "loyaltyProgram": {
      "value": "VIP membership program with exclusive benefits...",
      "source": "https://company.com/vip"
    }
  }
}
```

### Timeline Task Output

```json
{
  "timeline": {
    "insights": "The company has a rich history spanning...",
    "events": [
      {
        "date": {
          "value": "1987",
          "source": "https://wikipedia.org/wiki/LVMH"
        },
        "title": {
          "value": "Company Founded",
          "source": "https://wikipedia.org/wiki/LVMH"
        },
        "description": {
          "value": "Merger of Louis Vuitton and Moet Hennessy...",
          "source": "https://wikipedia.org/wiki/LVMH"
        },
        "category": {
          "value": "Foundation",
          "source": "Chaps-e"
        },
        "location": {
          "value": "Paris, France",
          "source": "https://wikipedia.org/wiki/LVMH"
        },
        "impact": {
          "value": "Created the world's largest luxury goods conglomerate",
          "source": "Chaps-e"
        }
      }
    ]
  }
}
```

### Products Task Output

```json
{
  "products": {
    "insights": "The company offers a diverse product portfolio...",
    "customerType": {
      "value": "High-net-worth individuals and aspirational consumers",
      "source": "Chaps-e"
    },
    "marketingPositioning": {
      "value": "Premium luxury positioning with heritage emphasis",
      "source": "Chaps-e"
    },
    "range": [
      {
        "value": "Leather Goods Collection",
        "source": "https://company.com/products"
      }
    ],
    "partnerBrands": [
      {
        "value": "Tiffany & Co.",
        "source": "https://company.com/brands"
      }
    ],
    "privateLabels": [
      {
        "value": "Maison Francis Kurkdjian",
        "source": "https://company.com/brands"
      }
    ],
    "categories": {
      "Fashion": ["Clothing", "Accessories", "Footwear"],
      "Watches & Jewelry": ["Watches", "Fine Jewelry", "High Jewelry"]
    }
  }
}
```

### Jobs Task Output

```json
{
  "jobs": {
    "insights": {
      "total_openings": {
        "value": 250,
        "source": "https://careers.company.com"
      },
      "top_departments": {
        "value": ["Retail", "Digital", "Marketing", "Finance"],
        "source": "https://careers.company.com"
      },
      "hiring_focus": {
        "value": "Expanding digital and e-commerce capabilities",
        "source": "Chaps-e"
      },
      "growth_indicators": {
        "value": "50% increase in tech roles YoY",
        "source": "Chaps-e"
      }
    },
    "offers": [
      {
        "title": {
          "value": "Senior Software Engineer",
          "source": "https://careers.company.com/job/123"
        },
        "location": {
          "value": "Paris, France",
          "source": "https://careers.company.com/job/123"
        },
        "department": {
          "value": "Digital Technology",
          "source": "https://careers.company.com/job/123"
        },
        "description": {
          "value": "Lead the development of e-commerce platform...",
          "source": "https://careers.company.com/job/123"
        },
        "requirements": {
          "value": "5+ years experience in Python, cloud architecture...",
          "source": "https://careers.company.com/job/123"
        },
        "posted_date": {
          "value": "2024-12-15",
          "source": "https://careers.company.com/job/123"
        }
      }
    ]
  }
}
```

### CSR Task Output

```json
{
  "csr": {
    "insights": "The company demonstrates strong commitment to CSR...",
    "responsibility": {
      "value": "Committed to carbon neutrality by 2030...",
      "source": "https://company.com/sustainability"
    },
    "initiatives": [
      {
        "type": "sustainability",
        "value": "100% renewable energy in all stores by 2025",
        "source": "https://company.com/sustainability"
      },
      {
        "type": "charity",
        "value": "Annual donation of 5M EUR to arts foundations",
        "source": "https://company.com/foundation"
      },
      {
        "type": "diversity",
        "value": "50% women in leadership positions",
        "source": "https://company.com/diversity"
      }
    ]
  }
}
```

### Press Task Output

```json
{
  "press": {
    "insights": "The company has received significant press coverage...",
    "items": [
      {
        "type": "article",
        "value": "LVMH reports record Q4 earnings driven by Asia growth",
        "source": "https://reuters.com/article/lvmh-earnings"
      },
      {
        "type": "award",
        "value": "Named Most Innovative Luxury Company 2024",
        "source": "https://luxuryawards.com/2024/winners"
      },
      {
        "type": "partnership",
        "value": "Strategic partnership with Apple for AR experiences",
        "source": "https://techcrunch.com/article/lvmh-apple"
      }
    ]
  }
}
```

### Team Task Output

```json
{
  "team": {
    "members": [
      {
        "position": {
          "value": "Chairman and CEO",
          "source": "https://company.com/leadership"
        },
        "firstName": {
          "value": "Bernard",
          "source": "https://company.com/leadership"
        },
        "lastName": {
          "value": "Arnault",
          "source": "https://company.com/leadership"
        },
        "linkedinUrl": {
          "value": "https://linkedin.com/in/bernard-arnault",
          "source": "https://linkedin.com"
        },
        "subordinates": [
          {
            "position": {
              "value": "Group Managing Director",
              "source": "https://company.com/leadership"
            },
            "firstName": {
              "value": "Antonio",
              "source": "https://company.com/leadership"
            },
            "lastName": {
              "value": "Belloni",
              "source": "https://company.com/leadership"
            },
            "linkedinUrl": null,
            "subordinates": []
          }
        ]
      }
    ]
  }
}
```

---

## Appendix C: Migration Plan

### Phase 1: Create New Tables (Non-Breaking)

1. Create Alembic migration with all new tables
2. Add foreign keys and indexes
3. Create SQLAlchemy models for all new tables
4. Deploy migration - existing JSON columns remain untouched

### Phase 2: Update Backend Write Path

1. Create service layer functions to save data to new tables
2. Update `_update_company_data` to write to both JSON columns AND new tables (dual-write)
3. Deploy and verify new data is saved correctly

### Phase 3: Migrate Existing Data

1. Create data migration script (separate from schema migration)
2. Parse existing JSON columns and insert into normalized tables
3. Run migration on staging, verify data integrity
4. Run migration on production during maintenance window

### Phase 4: Update Backend Read Path

1. Update `CompanyService.get_company` to read from normalized tables
2. Update `CompanyResponse` schema to build response from new tables
3. Remove `_parse_json_fields` helper function
4. Deploy and verify frontend displays correctly

### Phase 5: Cleanup

1. Remove dual-write logic (only write to new tables)
2. Create migration to drop old JSON columns
3. Remove any remaining JSON parsing code
4. Update frontend TypeScript interfaces if needed (likely minimal)

---

## Appendix D: API Contract Changes

### Response Structure

The API response structure remains backward-compatible. The `CompanyResponse` schema will build the same JSON structure from the normalized tables.

### Example Response (Unchanged)

```json
{
  "id": 123,
  "name": "LVMH",
  "website": "https://lvmh.com",
  "profile": {
    "groupName": {
      "value": "LVMH",
      "source": "https://wikipedia.org/wiki/LVMH"
    },
    "businessLine": {
      "value": "Luxury goods",
      "source": "https://lvmh.com/about"
    }
  },
  "digital": { ... },
  "timeline": { ... },
  "products": { ... },
  "jobs": { ... },
  "csr": { ... },
  "press": { ... },
  "team": [ ... ],
  "tasks": [ ... ]
}
```

### Internal Changes

- Backend builds response by joining normalized tables
- No frontend changes required for API consumption
- Press items now use standard `source` field instead of `sources[]` array

---

## Appendix E: Testing Requirements

### Unit Tests

- Test each SQLAlchemy model can be created and saved
- Test relationship cascades work correctly (delete company deletes children)
- Test SourcedValue Pydantic schema validation
- Test team hierarchy adjacency list queries

### Integration Tests

- Test Dify callback handler saves to all correct tables
- Test CompanyResponse builds correctly from normalized data
- Test migration script correctly transforms JSON to tables
- Test frontend displays migrated data correctly

### Migration Tests

- Run migration on copy of production database
- Verify row counts match (JSON items vs table rows)
- Verify data integrity (spot-check random companies)
- Verify performance is acceptable (query times)

### Regression Tests

- Verify existing frontend functionality unchanged
- Verify company creation flow still works
- Verify all 8 task types complete successfully
- Verify chat with company feature works with new structure
