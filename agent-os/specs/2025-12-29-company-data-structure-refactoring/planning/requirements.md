# Company Data Structure Refactoring - Requirements

## Overview

Transform the company data storage from JSON columns to a normalized relational database structure with:
- Dedicated tables for each data section
- Consistent SourcedValue pattern across all fields
- Translation-ready columns (only `_fr` for now as placeholder)

## Current State

### Database (8 JSON columns in `companies` table)
- `profile` - JSON
- `digital` - JSON
- `timeline` - JSON
- `products` - JSON
- `jobs` - JSON
- `csr` - JSON
- `press` - JSON
- `team` - JSON (list)

Plus 4 raw knowledge fields (strings).

### Problems with Current State
1. No type safety in backend (everything is `Dict[str, Any]`)
2. Inconsistent patterns (Press uses `sources: string[]` instead of `source: string`)
3. Some fields aren't sourced (timeline events, product categories, job offers, social media)
4. Cannot query individual fields efficiently
5. No support for translations

## Target State

### SourcedValue Pattern
Every user-facing text field should follow this pattern:
```
field_name TEXT           -- Original value (English from Dify)
field_name_source TEXT    -- Source URL or "Chaps-e" for AI-generated
field_name_value_fr TEXT  -- French translation (empty for now, placeholder for future)
```

### Source Values
- **Web scraping**: The URL that was scraped
- **Tools**: The tool name used
- **AI-generated insights**: "Chaps-e" (our chatbot name)

### Translation Rules

#### Fields that should NOT be translated (proper nouns, numbers, identifiers):
- Company name, website
- Group name (e.g., "LVMH")
- CEO name
- Headquarters location
- Establishment year, employee count, revenue
- Social media platform names and URLs
- Team member names, LinkedIn URLs
- Dates, locations
- Partner brands, private labels (brand names)

#### Fields that SHOULD be translated (descriptive text):
- Business line, catchphrase
- All insights (profile, digital, timeline, products, jobs, csr, press)
- Digital strategy fields (all 5 sub-fields)
- Online services (name, description)
- Loyalty program
- Timeline events (title, description, category, impact)
- Products (customer type, marketing positioning, range descriptions)
- Product categories
- Job offers (title, department, description, requirements)
- Job insights (top_departments, hiring_focus, growth_indicators)
- All CSR items
- All press items
- Team member positions

## Proposed Database Schema

### Main Table (lean)
```
companies
├── id SERIAL PRIMARY KEY
├── name VARCHAR NOT NULL
├── website VARCHAR NOT NULL
├── owner_id VARCHAR NOT NULL
├── owner_username VARCHAR
├── organization_id VARCHAR NOT NULL
├── created_at TIMESTAMPTZ
├── updated_at TIMESTAMPTZ
├── is_deleted BOOLEAN DEFAULT FALSE
├── error VARCHAR
├── raw_mistral_knowledge TEXT
├── raw_claude_knowledge TEXT
├── raw_wikipedia_knowledge TEXT
└── raw_scraped_website_knowledge TEXT
```

### Section Tables (1:1 relationships)

#### company_profile
```
company_id (PK, FK)
insights, insights_source, insights_value_fr
group_name, group_name_source (NO translation - proper noun)
business_line, business_line_source, business_line_value_fr
catchphrase, catchphrase_source, catchphrase_value_fr
establishment_year, establishment_year_source (NO translation)
employee_count, employee_count_source (NO translation)
revenue, revenue_source (NO translation)
ceo, ceo_source (NO translation)
hq, hq_source (NO translation)
```

#### company_digital
```
company_id (PK, FK)
insights, insights_source, insights_value_fr
overall_strategy, overall_strategy_source, overall_strategy_value_fr
digital_transformation, digital_transformation_source, digital_transformation_value_fr
ecommerce_capabilities, ecommerce_capabilities_source, ecommerce_capabilities_value_fr
mobile_strategy, mobile_strategy_source, mobile_strategy_value_fr
digital_marketing_approach, digital_marketing_approach_source, digital_marketing_approach_value_fr
loyalty_program, loyalty_program_source, loyalty_program_value_fr
```

#### company_timeline
```
company_id (PK, FK)
insights, insights_source, insights_value_fr
```

#### company_products
```
company_id (PK, FK)
insights, insights_source, insights_value_fr
customer_type, customer_type_source, customer_type_value_fr
marketing_positioning, marketing_positioning_source, marketing_positioning_value_fr
```

#### company_jobs
```
company_id (PK, FK)
insights_total_openings, insights_total_openings_source (NO translation - number)
insights_top_departments, insights_top_departments_source, insights_top_departments_value_fr
insights_hiring_focus, insights_hiring_focus_source, insights_hiring_focus_value_fr
insights_growth_indicators, insights_growth_indicators_source, insights_growth_indicators_value_fr
```

#### company_csr
```
company_id (PK, FK)
insights, insights_source, insights_value_fr
responsibility, responsibility_source, responsibility_value_fr
```

#### company_press
```
company_id (PK, FK)
insights, insights_source, insights_value_fr
```

### Child Tables (1:N relationships)

#### company_online_services
```
id, company_id (FK)
name, name_source, name_value_fr
description, description_source, description_value_fr
```

#### company_social_media_accounts
```
id, company_id (FK)
platform, platform_source (NO translation)
url, url_source (NO translation)
```

#### company_timeline_events
```
id, company_id (FK)
date, date_source (NO translation)
title, title_source, title_value_fr
description, description_source, description_value_fr
category, category_source, category_value_fr
location, location_source (NO translation)
impact, impact_source, impact_value_fr
```

#### company_product_items
```
id, company_id (FK)
type ENUM('range', 'partner_brand', 'private_label')
value, value_source
value_fr (only for 'range' type - brands don't translate)
```

#### company_product_categories
```
id, company_id (FK)
category_name, category_name_value_fr
items TEXT[] (array of category items)
items_value_fr TEXT[] (translated items)
```

#### company_job_offers
```
id, company_id (FK)
title, title_source, title_value_fr
location, location_source (NO translation)
department, department_source, department_value_fr
description, description_source, description_value_fr
requirements, requirements_source, requirements_value_fr
posted_date, posted_date_source (NO translation)
```

#### company_csr_initiatives
```
id, company_id (FK)
type ENUM('responsibility', 'charity', 'sustainability', 'community', 'diversity', 'ethics', 'awards')
value, value_source, value_value_fr
```

#### company_press_items
```
id, company_id (FK)
type ENUM('article', 'press_release', 'media_mention', 'award', 'product_launch', 'interview', 'financial', 'partnership')
value, value_source, value_value_fr
```

#### company_team_members
```
id, company_id (FK)
parent_id FK (nullable - CEO has no parent, for hierarchy)
position, position_source, position_value_fr
first_name, first_name_source (NO translation)
last_name, last_name_source (NO translation)
linkedin_url, linkedin_url_source (NO translation)
```

## Changes Required

### 1. Database Migration
- Create all new tables
- Migrate existing JSON data to normalized tables
- Drop JSON columns after successful migration

### 2. Backend Changes

#### SQLAlchemy Models
- Create new models for each table
- Define relationships (1:1 and 1:N)
- Add hybrid properties for convenience

#### Pydantic Schemas
- Create SourcedValue generic schema
- Create typed schemas for each section (not Dict[str, Any])
- Create response schemas that match frontend interface

#### API Endpoints
- Update company CRUD endpoints to work with new structure
- Maintain backward-compatible response format for frontend

#### Dify Callback Handler
- Update to parse new JSON format from Dify
- Save to appropriate tables instead of JSON columns

### 3. Frontend Changes

#### TypeScript Interfaces
- Update Company interface (structure stays similar)
- Ensure SourcedValue is used consistently
- Fix Press section to use standard SourcedValue (not sources[])

#### Components
- Minimal changes (data structure at API level stays similar)
- Press components may need updates for new source format

### 4. Dify Workflow Changes
- Document new expected JSON output format for each workflow
- All fields must include source information
- Insights must have source: "Chaps-e"

## Data Flow

```
Dify Workflows (returns JSON with SourcedValue pattern)
    ↓
Backend Callback Handler (parses and saves to normalized tables)
    ↓
SQLAlchemy Models (type-safe database access)
    ↓
Pydantic Schemas (typed API responses)
    ↓
Frontend TypeScript (type-safe UI)
```

## Migration Strategy

1. **Phase 1**: Create new tables (additive, non-breaking)
2. **Phase 2**: Update backend to write to new tables
3. **Phase 3**: Migrate existing data from JSON columns
4. **Phase 4**: Update backend to read from new tables
5. **Phase 5**: Drop old JSON columns

## Out of Scope

- Translation API endpoint (future spec)
- Translation population logic (future spec)
- Language selection in frontend (future spec)
- Only `_fr` columns added as placeholder pattern

## Success Criteria

1. All company data stored in normalized tables
2. Every user-facing text field has source tracking
3. Translation columns (`_fr`) exist but are empty
4. Frontend displays data correctly (no regression)
5. Dify workflows updated with new JSON format
6. All existing companies migrated successfully
