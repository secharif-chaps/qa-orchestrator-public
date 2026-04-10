---
name: opensearch
description: >
  OpenSearch/Elasticsearch integration for full-text search, indexing, and query building.
  Use when adding search functionality beyond SQL ILIKE, creating search indices,
  building OpenSearch query DSL, implementing faceted search, or optimizing search relevance.
  Activates when working on search infrastructure, configuring OpenSearch clients,
  or implementing advanced search features (fuzzy matching, aggregations, synonyms).
  CRITICAL - Always use parameterized queries and sanitize user input before building search queries.
allowed-tools: Read, Write, Edit, Glob, Grep, Bash
metadata:
  author: chaps-e
  version: "1.0"
---

## When to use this skill

- When adding full-text search beyond PostgreSQL ILIKE
- When creating or modifying OpenSearch index mappings
- When building search queries with filters, aggregations, or fuzzy matching
- When implementing bulk indexing or reindexing operations
- When optimizing search relevance or performance
- When configuring OpenSearch clients (opensearch-py)

# OpenSearch Integration

**CRITICAL**: Always sanitize user input before building queries. Never interpolate raw user input into query DSL.

## Client Setup

### Python (opensearch-py)

```python
from opensearchpy import AsyncOpenSearch

opensearch_client = AsyncOpenSearch(
    hosts=[{"host": settings.OPENSEARCH_HOST, "port": settings.OPENSEARCH_PORT}],
    http_auth=(settings.OPENSEARCH_USER, settings.OPENSEARCH_PASSWORD),
    use_ssl=settings.OPENSEARCH_USE_SSL,
    verify_certs=settings.OPENSEARCH_VERIFY_CERTS,
    ssl_show_warn=False,
)
```

### PHP (Symfony)

```php
// config/packages/opensearch.yaml
opensearch:
    clients:
        default:
            hosts: ['%env(OPENSEARCH_URL)%']
            basicAuthentication:
                username: '%env(OPENSEARCH_USER)%'
                password: '%env(OPENSEARCH_PASSWORD)%'
```

## Index Management

### Index Mapping

```python
COMPANY_INDEX_MAPPING = {
    "settings": {
        "number_of_shards": 1,
        "number_of_replicas": 1,
        "analysis": {
            "analyzer": {
                "company_name_analyzer": {
                    "type": "custom",
                    "tokenizer": "standard",
                    "filter": ["lowercase", "asciifolding"],
                }
            }
        },
    },
    "mappings": {
        "properties": {
            "name": {"type": "text", "analyzer": "company_name_analyzer", "fields": {"keyword": {"type": "keyword"}}},
            "website": {"type": "keyword"},
            "organization_id": {"type": "keyword"},
            "created_at": {"type": "date"},
            "is_deleted": {"type": "boolean"},
        }
    },
}
```

### Create/Update Index

```python
async def create_index(client: AsyncOpenSearch, index_name: str) -> None:
    exists = await client.indices.exists(index=index_name)
    if not exists:
        await client.indices.create(index=index_name, body=COMPANY_INDEX_MAPPING)
```

## Query Patterns

### Multi-Match Search

```python
async def search_companies(
    client: AsyncOpenSearch,
    query: str,
    organization_id: str,
    page: int = 1,
    size: int = 10,
) -> dict:
    body = {
        "query": {
            "bool": {
                "must": [
                    {"multi_match": {
                        "query": query,
                        "fields": ["name^3", "website"],
                        "type": "best_fields",
                        "fuzziness": "AUTO",
                    }}
                ],
                "filter": [
                    {"term": {"organization_id": organization_id}},
                    {"term": {"is_deleted": False}},
                ],
            }
        },
        "from": (page - 1) * size,
        "size": size,
        "highlight": {"fields": {"name": {}}},
    }
    return await client.search(index="companies", body=body)
```

### Aggregations

```python
body = {
    "query": {"bool": {"filter": [{"term": {"organization_id": org_id}}]}},
    "aggs": {
        "by_status": {"terms": {"field": "status.keyword"}},
        "created_over_time": {"date_histogram": {"field": "created_at", "calendar_interval": "month"}},
    },
    "size": 0,
}
```

## Bulk Indexing

```python
from opensearchpy.helpers import async_bulk

async def bulk_index_companies(client: AsyncOpenSearch, companies: list[Company]) -> None:
    actions = [
        {
            "_index": "companies",
            "_id": str(company.id),
            "_source": {
                "name": company.name,
                "website": company.website,
                "organization_id": str(company.organization_id),
                "created_at": company.created_at.isoformat(),
                "is_deleted": company.is_deleted,
            },
        }
        for company in companies
    ]
    await async_bulk(client, actions, raise_on_error=True)
```

## Key Rules

1. **Always filter by organization_id** - Multi-tenancy must be enforced at query level
2. **Use keyword fields for exact match** - `status.keyword` not `status` for term queries
3. **Sanitize input** - Escape special characters in user search terms
4. **Bulk operations for indexing** - Never index one document at a time in loops
5. **Alias-based reindexing** - Use aliases to swap indices without downtime
6. **Monitor index health** - Check cluster status before bulk operations

## ISO 27001 Compliance

This skill touches security-sensitive areas (A.8.12). Consult the `security-iso27001` skill for applicable controls on data indexing and access control on search results.
