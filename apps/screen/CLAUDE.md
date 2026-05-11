# Screen Backend (apps/screen/)

FastAPI backend at `apps/screen/app/`. See `screen-domain`, `python-fastapi`, `keycloak`, and `global-error-handling` skills for full development standards.

---

## Internal JWT Pattern (Screen-Specific)

Screen never validates Keycloak JWTs directly. The `global-service` gateway validates the Keycloak JWT and forwards requests with a signed internal token:

```
Authorization: Internal {token}
```

Screen only verifies these Internal JWTs — no Keycloak SDK needed in screen.

---

## Auth Helpers (correct import paths)

```python
from app.core.auth import AuthenticatedUser, get_current_user, verify_role_access, verify_any_role_access
from app.core.organization_context import get_user_organization, OrganizationContext
from app.core.security import verify_company_organization_access

@router.post("/companies")
async def create_company(
    data: CompanyCreate,
    user: AuthenticatedUser = Depends(get_current_user(required_roles=["company.create"])),
    org_context: OrganizationContext = Depends(get_user_organization),
    db: Session = Depends(get_db),
):
    """Create a company.

    Requires company.create role for access.
    """
    # org_context.organization_id — Keycloak org UUID
    # user.sub — Keycloak user UUID
    # user.preferred_username — username for display
    ...
```

**Available helpers**:

- `get_current_user(required_roles=[...])` — route-level role check, raises 403 automatically
- `verify_role_access(user, role)` — inline single-role check, raises 403 if missing
- `verify_any_role_access(user, roles)` — inline OR-logic check
- `verify_company_organization_access(company, org_context)` — resource ownership check

**Docstring convention**: always include `Requires <permission> role for access.` in endpoint docstrings.

---

## Task Runner Commands

```bash
task screen:shell    # Enter container
task screen:test     # Run tests
task screen:lint     # Lint
task screen:format   # Format
task migrate         # Apply DB migrations (must run inside container via screen:shell)
```
