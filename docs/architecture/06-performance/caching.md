# Caching

This document describes ChapsMind's caching strategies and query optimization approaches.

## Current State: No Server-Side Cache

**Important**: ChapsMind does **not** currently use Redis or any server-side caching layer.

| Layer | Caching | Technology |
|-------|---------|------------|
| **Frontend** | Query caching | Pinia Colada |
| **Backend** | No caching | N/A |
| **Database** | PostgreSQL buffer cache | Built-in |

## Frontend Query Caching (Pinia Colada)

### Overview

The frontend uses **Pinia Colada** for query caching and state management. This provides:

- **Automatic request deduplication**: Same queries are not duplicated
- **Stale-while-revalidate**: Show cached data while fetching fresh data
- **Cache invalidation**: Granular control over cache invalidation

### Query Key Pattern

```typescript
// Example: Company queries with hierarchical keys
export const COMPANY_QUERY_KEYS = {
  root: ['companies'] as const,
  byId: (id: string) => [...COMPANY_QUERY_KEYS.root, id] as const,
  withFilters: (filters: { page: number; size: number }) =>
    [...COMPANY_QUERY_KEYS.root, { filters }] as const,
}
```

### Cache Behavior

```typescript
// Query definition with Pinia Colada
export const companyByIdQuery = defineQueryOptions(({ id }: { id: string }) => ({
  key: COMPANY_QUERY_KEYS.byId(id),
  query: () => getCompanyById(id),
  staleTime: 1000 * 60 * 5,  // Consider fresh for 5 minutes
}))
```

### Cache Invalidation

```typescript
// Invalidate after mutation
const { mutate } = useMutation({
  mutation: (data: CompanyUpdate) => updateCompany(id, data),
  onSuccess: () => {
    // Invalidate related queries
    queryClient.invalidateQueries({ key: COMPANY_QUERY_KEYS.root })
  },
})
```

## Database Query Optimization

Without a dedicated cache layer, database query optimization is critical.

### Indexing Strategy

Key indexes for performance:

```sql
-- Company queries by organization
CREATE INDEX idx_companies_organization_id ON companies(organization_id);

-- Tasks by company and status
CREATE INDEX idx_tasks_company_status ON tasks(company_id, status);

-- Folders by owner
CREATE INDEX idx_folders_owner_id ON folders(owner_id);
```

### Query Patterns to Avoid

| Pattern | Issue | Solution |
|---------|-------|----------|
| **N+1 Queries** | Multiple DB calls | Use `selectinload`/`joinedload` |
| **SELECT *** | Fetch unnecessary data | Select only needed columns |
| **Missing indexes** | Full table scans | Add appropriate indexes |

### SQLAlchemy Optimization

```python
# Good: Eager loading relationships
from sqlalchemy.orm import selectinload

query = select(Company).options(
    selectinload(Company.tasks)
).where(Company.organization_id == org_id)

# Avoid: N+1 query pattern
# for company in companies:
#     tasks = company.tasks  # Lazy load triggers N queries
```

## Performance Recommendations

### Current Optimizations

1. **Use Pinia Colada effectively**
   - Define appropriate `staleTime` for queries
   - Implement proper cache invalidation on mutations
   - Use query key hierarchy for granular invalidation

2. **Optimize database queries**
   - Add indexes for frequently filtered columns
   - Use eager loading for related entities
   - Avoid SELECT * in performance-critical paths

3. **Reduce API calls**
   - Batch related requests where possible
   - Use pagination effectively
   - Leverage frontend caching for repeated data

### Monitoring Cache Effectiveness

Monitor:

- Database query times (PostgreSQL logs)
- API response times (application logs)
- Celery Flower for task queue metrics

## Related Documentation

- [Scalability](scalability.md): Scaling strategies
- [Monitoring](monitoring.md): Performance monitoring
- [API Contracts](../03-development/api-contracts.md): Frontend data fetching patterns
