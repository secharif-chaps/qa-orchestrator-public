# Coding Standards

This document provides an index of coding standards and conventions for ChapsMind development. Following the DRY principle, detailed standards are maintained in the `agent-os/standards/` directory and referenced here.

## Standards Repository

All detailed coding standards are maintained in:

```
agent-os/standards/
├── backend/           # Backend-specific standards
├── frontend/          # Frontend-specific standards
├── global/            # Cross-cutting standards
└── testing/           # Testing standards
```

## Quick Reference

### Global Standards

| Document | Description |
|----------|-------------|
| [Tech Stack](../../../agent-os/standards/global/tech-stack.md) | Technology versions and architecture patterns |
| [Coding Style](../../../agent-os/standards/global/coding-style.md) | General coding style guidelines |
| [Conventions](../../../agent-os/standards/global/conventions.md) | Naming and structural conventions |
| [Error Handling](../../../agent-os/standards/global/error-handling.md) | Error handling patterns |
| [Validation](../../../agent-os/standards/global/validation.md) | Input validation approaches |
| [Commenting](../../../agent-os/standards/global/commenting.md) | Code documentation standards |

### Backend Standards

| Document | Description |
|----------|-------------|
| [API](../../../agent-os/standards/backend/api.md) | RESTful API endpoint conventions |
| [Models](../../../agent-os/standards/backend/models.md) | SQLAlchemy model patterns |
| [Queries](../../../agent-os/standards/backend/queries.md) | Database query patterns |
| [Migrations](../../../agent-os/standards/backend/migrations.md) | Alembic migration guidelines |
| [Python](../../../agent-os/standards/backend/python.md) | Python coding standards |

### Frontend Standards

| Document | Description |
|----------|-------------|
| [Components](../../../agent-os/standards/frontend/components.md) | Vue component patterns |
| [CSS](../../../agent-os/standards/frontend/css.md) | Tailwind CSS conventions |
| [Accessibility](../../../agent-os/standards/frontend/accessibility.md) | WCAG compliance guidelines |
| [Responsive](../../../agent-os/standards/frontend/responsive.md) | Responsive design patterns |

### Testing Standards

| Document | Description |
|----------|-------------|
| [Test Writing](../../../agent-os/standards/testing/test-writing.md) | Test coverage and practices |

## Development Guides (CLAUDE.md Files)

Detailed development guides are embedded in the codebase:

| Document | Description |
|----------|-------------|
| [Workspace CLAUDE.md](../../../CLAUDE.md) | Main development guide with patterns and examples |
| [Backend CLAUDE.md](../../../back/CLAUDE.md) | Python/FastAPI development guide |
| [Components CLAUDE.md](../../../front/src/components/CLAUDE.md) | Component patterns and UI guidelines |
| [Pages CLAUDE.md](../../../front/src/pages/CLAUDE.md) | File-based routing patterns |

## Key Conventions Summary

### TypeScript/Vue

- **Always** use Composition API with `<script setup lang="ts">`
- **Always** use TypeScript with strict mode
- **Prefer** `interface` over `type` for object shapes
- **Always** use arrow functions for all functions and methods
- **Always** prefer named exports over default exports

### UI Components (Vuellar)

- **Always** use Vuellar components from `@owlint/feathers-vue` first
- **Never** create custom UI components when a Vuellar component exists
- **Always** compose Vuellar components for complex UI needs
- **Never** create custom components that could be reused in other Chapsvision projects (contribute to Vuellar instead)
- **Requires Lead Tech Approval** to create any new custom UI component in `src/components/ui/`

### Python/FastAPI

- **Always** use type hints for all functions
- **Always** use Google-style docstrings for public functions
- **Always** use custom exceptions from `app/core/exceptions.py`
- **Always** use `get_logger(__name__)` instead of `print()`
- **Always** keep endpoints thin, delegate to services

### CSS/Styling

- **Always** use Tailwind CSS classes
- **Always** use semantic color tokens (never raw colors)
- **Always** use flexbox with gap for spacing (not margins between siblings)
- **Never** hard-code colors directly

### API Design

- **Always** use RESTful conventions
- **Always** use plural nouns for resource endpoints
- **Always** return appropriate HTTP status codes
- **Always** validate with Pydantic schemas

### Git Workflow

- **Always** use feature branches (never commit directly to main)
- **Always** use gitmoji in commit messages
- **Always** include descriptive commit messages

## Permission Model

Permissions follow the `resource.action` format:

| Permission | Description |
|------------|-------------|
| `company.view` | View company details |
| `company.create` | Create new companies |
| `company.delete` | Delete companies |
| `organization.read` | View organization content |
| `organization.write` | Modify organization, manage team |
| `admin.organizations` | Global organization administration |

See [Authorization](../05-security/authorization.md) for detailed permission documentation.

## Code Review Checklist

Before submitting code:

### Backend

- [ ] All functions have type hints
- [ ] Public functions have docstrings
- [ ] Using custom exceptions (not bare `Exception`)
- [ ] Using logger (not `print()`)
- [ ] Ruff linting passes
- [ ] No sensitive data in logs

### Frontend

- [ ] Using Composition API with `<script setup lang="ts">`
- [ ] Using semantic color tokens
- [ ] Using gap for sibling spacing (not margins)
- [ ] Props are typed with TypeScript
- [ ] Loading and error states handled

## Related Documentation

- [Frontend Architecture](./frontend-architecture.md) - Frontend patterns
- [Backend Architecture](./backend-architecture.md) - Backend patterns
- [Testing Strategy](./testing-strategy.md) - Testing approach
