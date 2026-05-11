# OpenSearch - WatchFile Events Index

This document describes the OpenSearch `watchfile_events` index which stores events extracted from documents with their **actors** and associated metadata.

## Overview

The `watchfile_events` index enables efficient storage and search of events automatically extracted from watchfile documents. Each event is enriched with:

- A short and explicit bilingual title (max 100 characters each in French and English)
- Temporal information (start and end dates)
- Bilingual description (French and English)
- A categorized event type
- Associated **actors** (with their roles)
- Links to source documents
- An extraction status

## Index Mapping

```json
{
  "mappings": {
    "properties": {
      "id": {
        "type": "keyword"
      },
      "title": {
        "type": "object",
        "properties": {
          "fr": {
            "type": "text",
            "analyzer": "french",
            "fields": {
              "keyword": {
                "type": "keyword",
                "ignore_above": 256
              }
            }
          },
          "en": {
            "type": "text",
            "analyzer": "english",
            "fields": {
              "keyword": {
                "type": "keyword",
                "ignore_above": 256
              }
            }
          }
        }
      },
      "start_date": {
        "type": "date"
      },
      "end_date": {
        "type": "date"
      },
      "description": {
        "type": "object",
        "properties": {
          "fr": {
            "type": "text",
            "analyzer": "french",
            "fields": {
              "keyword": {
                "type": "keyword",
                "ignore_above": 512
              }
            }
          },
          "en": {
            "type": "text",
            "analyzer": "english",
            "fields": {
              "keyword": {
                "type": "keyword",
                "ignore_above": 512
              }
            }
          }
        }
      },
      "event_type": {
        "type": "keyword"
      },
      "actors": {
        "type": "nested",
        "properties": {
          "id": {
            "type": "keyword"
          },
          "name": {
            "type": "keyword"
          },
          "role": {
            "type": "keyword"
          },
          "watchfile_id": {
            "type": "keyword"
          }
        }
      },
      "document_links": {
        "type": "nested",
        "properties": {
          "id": {
            "type": "keyword"
          },
          "text_extract": {
            "type": "text"
          }
        }
      },
      "extraction_status": {
        "type": "keyword"
      },
      "created_at": {
        "type": "date"
      }
    }
  }
}
```

## Field Descriptions

### Identifier Field

- **`id`** (keyword): Unique identifier of the event

### Title Field

- **`title`** (object, required): Bilingual event title (max 100 characters each)
  - **`title.fr`** (text): French title analyzed with `french` analyzer
  - **`title.en`** (text): English title analyzed with `english` analyzer
  - Each language has a `.keyword` sub-field for exact matching and sorting (max 256 characters)

### Temporal Fields

- **`start_date`** (date): Event start date
- **`end_date`** (date, optional): Event end date
- **`created_at`** (date): Record creation date in OpenSearch

### Descriptive Fields

- **`description`** (object): Bilingual event description
  - **`description.fr`** (text): French description with `french` analyzer
  - **`description.en`** (text): English description with `english` analyzer
  - Each language has a `.keyword` sub-field for sorting and aggregations (max 512 characters)

### Categorical Fields

- **`event_type`** (keyword): Event type from:
  - `commercial_business`: Commercial and business events
  - `financial`: Financial events
  - `organizational_hr`: Organizational and HR events
  - `technological_rd`: Technological and R&D events
  - `regulatory_political`: Regulatory and political events
  - `market_competitors`: Market and competition events
  - `societal_environmental`: Societal and environmental events

- **`extraction_status`** (keyword): Extraction status from:
  - `pending`: Awaiting extraction
  - `completed`: Extraction completed successfully
  - `failed`: Extraction failed

### Nested Fields

#### actors (nested)

List of **actors** involved in the event. Uses `nested` type to maintain relationships between properties of each actor.

- **`id`** (keyword): Unique identifier of the actor
- **`name`** (keyword): Name of the actor
- **`role`** (keyword): Role of the actor in the event
- **`watchfile_id`** (keyword): Identifier of the watchfile associated with the actor

#### document_links (nested)

List of documents linked to the event. Uses `nested` type to maintain relationships between documents and their extracts.

- **`id`** (keyword): Unique identifier of the document
- **`text_extract`** (text): Text extract from the document mentioning the event

## Migration

The index is created via the OpenSearch migration system:

```bash
# Check migration status
docker compose exec api php bin/console elasticsearch:migrations:migrate --status

# Execute pending migrations
docker compose exec api php bin/console elasticsearch:migrations:migrate

# Rollback last migration
docker compose exec api php bin/console elasticsearch:migrations:migrate --rollback
```

### Migration File

The migration is defined in `api/migrations/opensearch/Version20251007140000.php`.

## Fixtures and Test Data

### WatchFileEventFactory

The project provides a static factory for generating test event data:

**Location:** `api/src/DataFixtures/Factory/WatchFile/WatchFileEventFactory.php`

**Key Features:**

- Uses Faker for dynamic, realistic content generation
- Generates bilingual titles and descriptions with context-specific prefixes
- Creates 2-4 actors per event with varied roles
- Includes 1-3 document links with realistic extracts
- Properly handles date ranges and status assignment

### Loading Fixtures

```bash
# Load all fixtures (including WatchFileEventFixtures)
docker compose exec api php bin/console doctrine:fixtures:load --no-interaction
# Load WatchFileEventFixtures
docker compose exec api php bin/console doctrine:fixtures:load --group=WatchFileEventFixtures --no-interaction

# The fixtures will:
# 1. Find all existing watchfiles
# 2. Generate 10 events per watchfile
# 3. Index them in OpenSearch
```

**Fixture Implementation:** `api/src/DataFixtures/WatchFileEventFixtures.php`
