# Coding Style Standards

## Core Principles

### Keep It Simple
- Implement code in the fewest lines possible
- Avoid over-engineering solutions
- Choose straightforward approaches over clever ones
- Use established patterns

### Optimize for Readability
- Prioritize code clarity over micro-optimizations
- Write self-documenting code with clear variable names
- Add comments for "why" not "what"
- Use TypeScript (frontend) and type hints (backend) for documentation

### DRY (Don't Repeat Yourself)
- Extract repeated business logic to services (backend) or composables (frontend)
- Extract repeated UI markup to reusable components
- Create utility functions for common operations
- Use shared patterns for queries and mutations

---

## Naming Conventions

### Variables and Functions
- **Python**: Use `snake_case` for variables and functions
- **TypeScript/Vue**: Use `camelCase` for variables and functions

```python
# Python
user_name = "John Doe"
def get_user_profile(user_id: int) -> dict:
    pass
```

```typescript
// TypeScript
const userName = "John Doe"
function getUserProfile(userId: number): User {
  // ...
}
```

### Classes and Components
- **Python**: Use `PascalCase` for classes
- **Vue**: Use `PascalCase` for component files and names

```python
# Python
class UserProfile:
    pass

class CompanyService:
    pass
```

```vue
<!-- Vue: UserProfile.vue, CompanyCard.vue -->
<script setup lang="ts">
// Component logic
</script>
```

### Constants
Use `UPPER_SNAKE_CASE` for constants:

```python
# Python
MAX_RETRY_COUNT = 3
DEFAULT_PAGE_SIZE = 10
```

```typescript
// TypeScript
const MAX_RETRY_COUNT = 3
const DEFAULT_PAGE_SIZE = 10
```

### File Naming
- **Python**: `snake_case.py`
- **Vue components**: `PascalCase.vue`
- **TypeScript/JavaScript**: `kebab-case.ts` or `camelCase.ts`
- **Tests**: alongside files: `Button.vue` + `Button.spec.ts`

---

## Formatting

### Indentation
- **Python**: 4 spaces
- **TypeScript/Vue**: 2 spaces
- **Never** use tabs

### Line Length
- **Python**: 88 characters (Ruff default)
- **TypeScript**: 100 characters (Prettier default)

### Import Organization

**Python** (3 groups):
```python
# 1. Standard library
import logging
from datetime import datetime

# 2. Third-party packages
from fastapi import Depends
from pydantic import BaseModel

# 3. Local application imports
from app.models import Company
from app.services import CompanyService
```

**TypeScript/Vue** (3 groups):
```typescript
// 1. Vue and external libraries
import { ref, computed } from 'vue'
import { useRoute } from 'vue-router'

// 2. UI components
import { Button, Input, Alert } from '@owlint/feathers-vue'

// 3. Local imports
import { useAuthStore } from '@/stores/auth'
import type { Company } from '@/types'
```

---

## Functions

### Small, Focused Functions
- Keep functions under 50 lines
- Single responsibility per function
- Extract helpers for complex logic

### Arrow Functions (Vue)
**ALWAYS** use arrow functions for all functions and methods:

```typescript
// ✅ Arrow function for methods
const handleSubmit = () => {
  emit('submit', data)
}

// ✅ Arrow function for callbacks
items.filter((item) => item.active)
```

### Early Returns
Reduce nesting with early returns:

```python
# ✅ CORRECT - Early returns
def get_company(company_id: int, user: User) -> Company | None:
    company = db.query(Company).filter(Company.id == company_id).first()

    if not company:
        return None

    if company.is_deleted:
        return None

    if not user.has_permission("company.view"):
        raise AuthorizationError("Permission denied")

    return company
```

---

## Code Comments

### Self-Documenting Code
Write code that explains itself through clear structure and naming

### Minimal, Helpful Comments
- Add concise comments to explain large sections of code logic
- Explain the "why" not the "what"

### Don't Comment Changes
Do not leave comments about recent or temporary changes. Comments should be evergreen.

```python
# ✅ CORRECT - Explains why
# Use JWT-only check to avoid race condition with DB permission sync
if not user.has_permission("company.create"):
    raise AuthorizationError()

# ❌ INCORRECT - States the obvious
# Check permission
if not user.has_permission("company.create"):
    raise AuthorizationError()

# ❌ INCORRECT - Temporary comment
# TODO: Remove after v2.0 migration
# FIXME: Temporary workaround for bug #123
```

---

## Best Practices

### Consistent Naming Conventions
Establish and follow naming conventions across the codebase

### Automated Formatting
- **Python**: Use Ruff (`ruff format .`)
- **TypeScript**: Use Prettier (`prettier --write .`)

### Remove Dead Code
Delete unused code, commented-out blocks, and imports rather than leaving them

### Backward Compatibility
Unless specifically instructed otherwise, assume you do not need to write additional code logic to handle backward compatibility

### No Over-Engineering
- Don't add features beyond what was asked
- A bug fix doesn't need surrounding code cleaned up
- A simple feature doesn't need extra configurability
- Don't add docstrings/comments/type annotations to unchanged code

### Avoid Premature Abstraction
- Don't create helpers for one-time operations
- Don't design for hypothetical future requirements
- Three similar lines of code is better than a premature abstraction
