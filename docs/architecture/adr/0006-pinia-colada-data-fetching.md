# ADR-0006: Pinia Colada for Data Fetching

## Status

**Status:** Accepted

**Date:** 2024-01-15

**Decision Makers:** ChapsMind Engineering Team

**Tags:** frontend, state-management, data-fetching

---

## Context

ChapsMind's frontend needs a robust data fetching solution that:

- Provides caching to avoid redundant API calls
- Manages loading and error states automatically
- Supports cache invalidation after mutations
- Integrates well with Vue 3 Composition API
- Handles optimistic updates for better UX
- Provides background refetching for fresh data

The frontend already uses Pinia for global state management (auth, preferences). We need a complementary solution specifically for server state (data from the API).

Server state has different characteristics than client state:

- Owned by the server, not the client
- Can become stale
- Needs synchronization with backend
- Should be cached and deduplicated

---

## Decision

We will use **Pinia Colada** as the data fetching library for all API interactions. Pinia Colada is a Vue-native solution built on top of Pinia, providing query and mutation primitives with caching.

Key implementation decisions:

- **Pinia Colada for server state**: All API data fetching uses Pinia Colada queries
- **Pinia for client state**: Auth, preferences, and UI state remain in Pinia stores
- **Hierarchical query keys**: Enable targeted cache invalidation
- **Mutations with cache updates**: Automatic cache invalidation after mutations
- **Three-layer architecture**: Components -> Queries/Mutations -> API functions

---

## Options Considered

### Option 1: Pinia Colada (Chosen)

**Description:** Vue-native data fetching library built on Pinia, providing queries and mutations with caching.

**Pros:**

- Vue-native (built for Vue, not ported from React)
- Built on Pinia (consistent with existing state management)
- Composition API first design
- Excellent TypeScript support
- Simpler API than TanStack Query
- Active development by Vue core team member (Eduardo)
- Automatic deduplication of requests
- Background refetching
- Cache invalidation patterns

**Cons:**

- Smaller community than TanStack Query
- Fewer features than TanStack Query
- Less battle-tested
- Some advanced features still in development

### Option 2: TanStack Query (Vue Query)

**Description:** The Vue port of React Query, part of the TanStack ecosystem.

**Pros:**

- Large community and ecosystem
- Battle-tested (years of React Query usage)
- Comprehensive feature set
- Excellent documentation
- Devtools available

**Cons:**

- Not Vue-native (ported from React)
- Different paradigms than Vue ecosystem
- Heavier bundle size
- Some React-isms in API design
- Requires additional Pinia store integration

### Option 3: Custom Solution with Pinia

**Description:** Build custom caching and fetching logic in Pinia stores.

**Pros:**

- Full control over implementation
- No additional dependencies
- Tailored to exact needs

**Cons:**

- Significant development effort
- Must implement caching from scratch
- No community support
- Reinventing the wheel
- Error-prone

### Option 4: SWR for Vue

**Description:** SWR-style hooks for Vue.

**Pros:**

- Simple mental model
- Lightweight

**Cons:**

- Less mature for Vue
- Fewer features than alternatives
- Limited TypeScript support

---

## Consequences

### Positive

- **Vue-Native Experience**: API feels natural in Vue components
- **Pinia Consistency**: Same underlying state library for all state
- **Automatic Caching**: Reduces API calls and improves performance
- **Loading/Error States**: Handled automatically, less boilerplate
- **TypeScript Excellence**: Full type inference throughout
- **Cache Invalidation**: Easy to invalidate related data after mutations
- **Developer Experience**: Clear separation of concerns

### Negative

- **Learning Curve**: Team must learn Pinia Colada patterns
- **Smaller Ecosystem**: Fewer examples and community resources
- **Feature Gaps**: Some advanced features may be missing vs TanStack Query
- **Dependency Risk**: Newer library, potential for breaking changes

### Neutral

- Must maintain query key conventions across codebase
- Some patterns require documentation for consistency
- Team becomes early adopter of Vue-native solution

---

## Implementation Notes

### Query Keys Pattern

```typescript
// queries/companies.ts
export const COMPANY_QUERY_KEYS = {
  root: ['companies'] as const,
  byId: (id: string) => [...COMPANY_QUERY_KEYS.root, id] as const,
  list: (filters: Filters) => [...COMPANY_QUERY_KEYS.root, 'list', { filters }] as const,
}
```

### Query Definition

```typescript
import { defineQueryOptions } from '@pinia/colada'
import { getCompanyById } from '@/api/companies'

export const companyByIdQuery = defineQueryOptions(({ id }: { id: string }) => ({
  key: COMPANY_QUERY_KEYS.byId(id),
  query: () => getCompanyById(id),
}))
```

### Component Usage

```vue
<script setup lang="ts">
import { useQuery } from '@pinia/colada'
import { companyByIdQuery } from '@/queries/companies'

const props = defineProps<{ id: string }>()
const { data, isLoading, error } = useQuery(companyByIdQuery, () => ({ id: props.id }))
</script>
```

### Mutation with Cache Invalidation

```typescript
import { defineMutation, useMutation, useQueryCache } from '@pinia/colada'

export const useCreateCompany = defineMutation(() => {
  const queryCache = useQueryCache()

  const { mutate, ...mutation } = useMutation({
    mutation: (data: CompanyCreate) => createCompany(data),
    onSettled: () => {
      queryCache.invalidateQueries({ key: COMPANY_QUERY_KEYS.root })
    },
  })

  return { ...mutation, createCompany: mutate }
})
```

---

## References

- [Pinia Colada Documentation](https://pinia-colada.esm.dev/)
- [Pinia Colada GitHub](https://github.com/posva/pinia-colada)
- [Frontend Architecture](../03-development/frontend-architecture.md)
- [API Contracts](../03-development/api-contracts.md)
- [ADR-0001: Vue.js 3](./0001-vue3-composition-api.md)
