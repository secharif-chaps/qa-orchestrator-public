# Dify Workflow JSON Formats

## Overview

This document defines the expected JSON output format for each Dify workflow task type. These formats are consumed by the backend webhook callback (`/api/webhooks/dify/task-callback`) and saved to the normalized database tables.

Each workflow must return data in a consistent structure using the SourcedValue pattern to ensure all user-facing information includes proper source attribution.

## SourcedValue Pattern

Every user-facing text field should include source attribution. The pattern consists of:

```json
{
  "value": "The actual content",
  "source": "https://example.com/source-url"
}
```

### Source Field Values

The `source` field must contain one of:

| Type | Example | Description |
|------|---------|-------------|
| URL | `"https://company.com/about"` | Direct link to the source webpage |
| AI Tool | `"mistral"`, `"perplexity"` | Name of the AI tool that generated the insight |
| Chaps-e | `"Chaps-e"` | AI-generated analysis or categorization by our system |

### Fields Without Translation Support

The following field types do NOT get translation columns (stored as-is):
- **Proper nouns**: Names, brands, locations (e.g., CEO name, company name)
- **Numbers**: Year, count, revenue amounts
- **URLs**: LinkedIn profiles, social media links
- **Dates**: Event dates, posted dates

---

## Task Types

### 1. Profile Task

**Purpose**: Extracts core company information including leadership, financials, and general business details.

**Database Tables Written**:
- `company_profile` (1:1)

**Expected Output Format**:

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

**Field Mapping to Database**:

| JSON Field | Database Column | Translation Column |
|------------|-----------------|-------------------|
| `insights` | `insights`, `insights_source` | `insights_value_fr` |
| `groupName` | `group_name`, `group_name_source` | No (proper noun) |
| `businessLine` | `business_line`, `business_line_source` | `business_line_value_fr` |
| `catchphrase` | `catchphrase`, `catchphrase_source` | `catchphrase_value_fr` |
| `establishmentYear` | `establishment_year`, `establishment_year_source` | No (number) |
| `employeeCount` | `employee_count`, `employee_count_source` | No (number) |
| `revenue` | `revenue`, `revenue_source` | No (number) |
| `ceo` | `ceo`, `ceo_source` | No (proper noun) |
| `hq` | `hq`, `hq_source` | No (location) |

---

### 2. Digital Task

**Purpose**: Analyzes the company's digital presence, strategy, and online services.

**Database Tables Written**:
- `company_digital` (1:1)
- `company_online_services` (1:N)
- `company_social_media_accounts` (1:N)

**Expected Output Format**:

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

**Field Mapping to Database**:

| JSON Field | Database Table/Column | Translation Column |
|------------|----------------------|-------------------|
| `insights` | `company_digital.insights`, `insights_source` | `insights_value_fr` |
| `digitalStrategy.overallStrategy` | `company_digital.overall_strategy`, `overall_strategy_source` | `overall_strategy_value_fr` |
| `digitalStrategy.digitalTransformation` | `company_digital.digital_transformation`, `digital_transformation_source` | `digital_transformation_value_fr` |
| `digitalStrategy.eCommerceCapabilities` | `company_digital.ecommerce_capabilities`, `ecommerce_capabilities_source` | `ecommerce_capabilities_value_fr` |
| `digitalStrategy.mobileStrategy` | `company_digital.mobile_strategy`, `mobile_strategy_source` | `mobile_strategy_value_fr` |
| `digitalStrategy.digitalMarketingApproach` | `company_digital.digital_marketing_approach`, `digital_marketing_approach_source` | `digital_marketing_approach_value_fr` |
| `loyaltyProgram` | `company_digital.loyalty_program`, `loyalty_program_source` | `loyalty_program_value_fr` |
| `onlineServices[].name` | `company_online_services.name`, `name_source` | `name_value_fr` |
| `onlineServices[].description` | `company_online_services.description`, `description_source` | `description_value_fr` |
| `socialMediaAccounts[].platform` | `company_social_media_accounts.platform`, `platform_source` | No (proper noun) |
| `socialMediaAccounts[].url` | `company_social_media_accounts.url`, `url_source` | No (URL) |

---

### 3. Timeline Task

**Purpose**: Captures significant events in the company's history with dates, descriptions, and impact assessment.

**Database Tables Written**:
- `company_timeline` (1:1)
- `company_timeline_events` (1:N)

**Expected Output Format**:

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

**Field Mapping to Database**:

| JSON Field | Database Table/Column | Translation Column |
|------------|----------------------|-------------------|
| `insights` | `company_timeline.insights`, `insights_source` | `insights_value_fr` |
| `events[].date` | `company_timeline_events.date`, `date_source` | No (date) |
| `events[].title` | `company_timeline_events.title`, `title_source` | `title_value_fr` |
| `events[].description` | `company_timeline_events.description`, `description_source` | `description_value_fr` |
| `events[].category` | `company_timeline_events.category`, `category_source` | `category_value_fr` |
| `events[].location` | `company_timeline_events.location`, `location_source` | No (location) |
| `events[].impact` | `company_timeline_events.impact`, `impact_source` | `impact_value_fr` |

**Event Categories** (typical values):
- Foundation
- Acquisition
- Expansion
- Product Launch
- Leadership Change
- Partnership
- Milestone
- Award
- Crisis

---

### 4. Products Task

**Purpose**: Documents the company's product portfolio, target customers, and market positioning.

**Database Tables Written**:
- `company_products` (1:1)
- `company_product_items` (1:N)
- `company_product_categories` (1:N)

**Expected Output Format**:

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

**Field Mapping to Database**:

| JSON Field | Database Table/Column | Translation Column |
|------------|----------------------|-------------------|
| `insights` | `company_products.insights`, `insights_source` | `insights_value_fr` |
| `customerType` | `company_products.customer_type`, `customer_type_source` | `customer_type_value_fr` |
| `marketingPositioning` | `company_products.marketing_positioning`, `marketing_positioning_source` | `marketing_positioning_value_fr` |
| `range[]` | `company_product_items` (type='range') | `value_value_fr` |
| `partnerBrands[]` | `company_product_items` (type='partner_brand') | No (brand name) |
| `privateLabels[]` | `company_product_items` (type='private_label') | No (brand name) |
| `categories` | `company_product_categories` | `category_name_value_fr`, `items_value_fr[]` |

**Product Item Types** (enum):
- `range` - Company's own product ranges (gets translation)
- `partner_brand` - External partner brands (no translation - proper noun)
- `private_label` - Private label/house brands (no translation - proper noun)

---

### 5. Jobs Task

**Purpose**: Analyzes the company's hiring activity and job market presence.

**Database Tables Written**:
- `company_jobs` (1:1)
- `company_job_offers` (1:N)

**Expected Output Format**:

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

**Field Mapping to Database**:

| JSON Field | Database Table/Column | Translation Column |
|------------|----------------------|-------------------|
| `insights.total_openings` | `company_jobs.insights_total_openings`, `insights_total_openings_source` | No (number) |
| `insights.top_departments` | `company_jobs.insights_top_departments`, `insights_top_departments_source` | `insights_top_departments_value_fr` |
| `insights.hiring_focus` | `company_jobs.insights_hiring_focus`, `insights_hiring_focus_source` | `insights_hiring_focus_value_fr` |
| `insights.growth_indicators` | `company_jobs.insights_growth_indicators`, `insights_growth_indicators_source` | `insights_growth_indicators_value_fr` |
| `offers[].title` | `company_job_offers.title`, `title_source` | `title_value_fr` |
| `offers[].location` | `company_job_offers.location`, `location_source` | No (location) |
| `offers[].department` | `company_job_offers.department`, `department_source` | `department_value_fr` |
| `offers[].description` | `company_job_offers.description`, `description_source` | `description_value_fr` |
| `offers[].requirements` | `company_job_offers.requirements`, `requirements_source` | `requirements_value_fr` |
| `offers[].posted_date` | `company_job_offers.posted_date`, `posted_date_source` | No (date) |

**Note**: The `insights` field for jobs is structured differently from other tasks - it contains multiple sub-fields rather than a single text block.

---

### 6. CSR Task

**Purpose**: Documents the company's Corporate Social Responsibility initiatives and commitments.

**Database Tables Written**:
- `company_csr` (1:1)
- `company_csr_initiatives` (1:N)

**Expected Output Format**:

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

**Field Mapping to Database**:

| JSON Field | Database Table/Column | Translation Column |
|------------|----------------------|-------------------|
| `insights` | `company_csr.insights`, `insights_source` | `insights_value_fr` |
| `responsibility` | `company_csr.responsibility`, `responsibility_source` | `responsibility_value_fr` |
| `initiatives[].type` | `company_csr_initiatives.type` (enum) | No (enum value) |
| `initiatives[].value` | `company_csr_initiatives.value`, `value_source` | `value_value_fr` |

**CSR Initiative Types** (enum):
- `responsibility` - General corporate responsibility
- `charity` - Charitable donations and foundations
- `sustainability` - Environmental sustainability
- `community` - Community engagement programs
- `diversity` - Diversity and inclusion initiatives
- `ethics` - Business ethics and governance
- `awards` - CSR-related awards and recognition

---

### 7. Press Task

**Purpose**: Aggregates press coverage, media mentions, and notable news about the company.

**Database Tables Written**:
- `company_press` (1:1)
- `company_press_items` (1:N)

**Expected Output Format**:

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

**Field Mapping to Database**:

| JSON Field | Database Table/Column | Translation Column |
|------------|----------------------|-------------------|
| `insights` | `company_press.insights`, `insights_source` | `insights_value_fr` |
| `items[].type` | `company_press_items.type` (enum) | No (enum value) |
| `items[].value` | `company_press_items.value`, `value_source` | `value_value_fr` |

**Press Item Types** (enum):
- `article` - News articles about the company
- `press_release` - Official company press releases
- `media_mention` - Mentions in media coverage
- `award` - Awards and recognition
- `product_launch` - Product launch announcements
- `interview` - Executive interviews
- `financial` - Financial news and earnings reports
- `partnership` - Partnership and collaboration announcements

---

### 8. Team Task

**Purpose**: Maps the company's leadership structure and organizational hierarchy.

**Database Tables Written**:
- `company_team_members` (1:N with self-referencing hierarchy)

**Expected Output Format**:

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

**Field Mapping to Database**:

| JSON Field | Database Column | Translation Column |
|------------|-----------------|-------------------|
| `position` | `position`, `position_source` | `position_value_fr` |
| `firstName` | `first_name`, `first_name_source` | No (proper noun) |
| `lastName` | `last_name`, `last_name_source` | No (proper noun) |
| `linkedinUrl` | `linkedin_url`, `linkedin_url_source` | No (URL) |
| `subordinates` | `parent_id` (foreign key to parent member) | N/A |

**Hierarchy Implementation**:

The team hierarchy uses an **adjacency list pattern**:
- CEO/top-level leaders have `parent_id = NULL`
- Direct reports have `parent_id` pointing to their manager's `id`
- The backend flattens the nested JSON structure and rebuilds the tree using `parent_id` relationships
- The frontend receives a flat list and reconstructs the tree for display

**Processing Logic**:
1. Parse nested JSON structure
2. Insert top-level members first (get their IDs)
3. Insert subordinates with `parent_id` referencing parent's ID
4. Recursively process all levels of the hierarchy

---

## Validation Rules

### Required Fields

Each task type has required fields that must be present:

| Task Type | Required Fields |
|-----------|-----------------|
| Profile | `profile.insights` |
| Digital | `digital.insights` |
| Timeline | `timeline.insights` |
| Products | `products.insights` |
| Jobs | `jobs.insights.total_openings` |
| CSR | `csr.insights` |
| Press | `press.insights` |
| Team | `team.members` (at least one) |

### SourcedValue Validation

For any SourcedValue object:
- `value` - Required, can be string, number, or array depending on field
- `source` - Required, must be:
  - A valid URL (starts with `http://` or `https://`)
  - A known AI tool name: `"mistral"`, `"perplexity"`, `"claude"`
  - The string `"Chaps-e"` for AI-generated insights

### Null Handling

- Fields can be `null` if information is not available
- Empty arrays `[]` are acceptable for list fields
- Missing optional fields are treated as `null`

---

## Error Handling

If a Dify workflow returns malformed JSON or fails validation:

1. The webhook logs the error details
2. The task status is set to `failed`
3. The company's `error` field is updated with error description
4. Partial data is NOT saved (transaction rollback)

Workflows should ensure all output conforms to these specifications before returning.
