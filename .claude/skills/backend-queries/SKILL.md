---
name: backend-queries
description: Doctrine QueryBuilder and database query best practices for PHP/Symfony. Use when writing queries in Gateway implementations, using parameterized queries to prevent SQL injection, eager loading with `leftJoin()` to avoid N+1 problems, selecting only needed columns, or wrapping related operations in transactions. Activates when working on `*DoctrineGateway.php` files, optimizing query performance with indexes, implementing caching for expensive queries, or setting query timeouts.
allowed-tools: Read, Write, Edit, Glob, Grep, Bash
metadata:
  author: chaps-e
  version: "1.0"
---

## When to use this skill

- When writing Doctrine QueryBuilder queries in `*DoctrineGateway.php` files
- When using parameterized queries with `setParameter()` to prevent SQL injection
- When eager loading with `leftJoin()` and `addSelect()` to avoid N+1 queries
- When selecting only needed columns instead of fetching entire entities
- When indexing columns used in WHERE, JOIN, and ORDER BY clauses
- When wrapping related database operations in transactions
- When implementing caching for complex or frequently-run queries
- When setting query timeouts for long-running operations
- When using `getSingleScalarResult()`, `getOneOrNullResult()`, `getSingleColumnResult()`
- When optimizing query performance with EXPLAIN analysis
- When choosing between Doctrine (relational queries) and Elasticsearch (full-text search / faceted search)

# Backend Queries

## Doctrine vs Elasticsearch

Basil uses **two query backends** depending on the use case:

| Use case | Backend |
|---|---|
| Fetching by ID / foreign key / status | Doctrine QueryBuilder (`*DoctrineGateway.php`) |
| Full-text search, faceted filters, relevance ranking | Elasticsearch (`*ElasticsearchGateway.php`) |

Entities like `Document` have **both** gateways. The domain interface declares the contract; the infrastructure layer provides two implementations:

```
api/src/Infrastructure/Document/
├── DocumentDoctrineGateway.php        # Relational queries
└── DocumentElasticsearchGateway.php   # Full-text search
```

When a gateway method involves free-text search or complex faceted filtering, implement it in the Elasticsearch gateway — not in Doctrine.

## Rules

- **Prevent SQL Injection**: Always use parameterized queries with `setParameter()`; never interpolate user input into SQL strings
- **Avoid N+1 Queries**: Use eager loading with `leftJoin()` and `addSelect()` to fetch related data in a single query
- **Select Only Needed Data**: Request only the columns you need rather than fetching entire entities
- **Index Strategic Columns**: Index columns used in WHERE, JOIN, and ORDER BY clauses
- **Use Transactions**: Wrap related database operations in transactions to maintain data consistency
- **Set Query Timeouts**: Implement timeouts to prevent runaway queries from impacting system performance
- **Cache Expensive Queries**: Cache results of complex or frequently-run queries when appropriate
- **Choose the Right Backend**: Use Doctrine for relational/id-based queries; use Elasticsearch for full-text search and faceted filters
