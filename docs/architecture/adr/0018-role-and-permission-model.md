# ADR-0018: Role and Permission Model

## Status

**Status:** Proposed

**Date:** 2026-04-21

**Decision Makers:** ChapsMind Engineering Team

**Tags:** security, keycloak, rbac, permissions, multi-tenancy

---

## Context

A permissions audit revealed three structural problems in the current model:

1. **Inconsistent atomic model** — Existing Keycloak roles mix three levels of granularity: by feature (`company.create`, `company.delete`), by scope (`organization.read/write/manage`), by admin domain (`admin.organizations`, `admin.tasks`, `admin.costs`). The module/resource/action boundary does not exist. Result: ambiguity (does `organization.write` mean "write in the org" or "manage the org"?), coverage gaps (no explicit roles for `target.*`, `admin.workflows`), and legacy debt (`company.view` vs `organization.read`).

2. **No canonical composite role** — There is only one composite `admin` that aggregates all roles. Intermediate personas (viewer, editor, manager) must be manually re-composed by the administrator, without consistency guarantees. The "Quick Roles" modal on the frontend (`useRoles.ts`) simulates composite roles but they are not reflected on Keycloak.

3. **Desynchronization between `realm-chapsmind.json` and code** — The realm references `admin.tasks` but not `admin.workflows`; `stream.read/write` exist but `screen.create` and `target.create` appear in frontend code without Keycloak definition. There is no single source of truth.

**Constraints**:

- Keycloak must remain the single source of truth for authentication **and** authorization (ADR-0003, ADR-0005).
- No production data exists yet → no prod migration necessary. Priority: **plug-and-play developer experience** via `task init` and `setup-keycloak.sh`.
- Roles must be discoverable and expressed in the admin UI (permission delegation by an organization manager).
- The cross-tenant admin role must be reserved for ChapsVision collaborators.

---

## Decision

Adopt a **two-tier permission model**: granular atomic permissions and canonical composite roles.

### 1. Atomic permissions — format `<module>.<resource>.<action>`

- **`<module>`** ∈ `{screen, target, stream, organization, admin}`.
- **`<resource>`** = business entity (e.g., `company`, `folder`, `watchfile`, `member`, `task`).
- **`<action>`** ∈ `{read, write, manage}` where:
  - **`read`** = view / list / read access.
  - **`write`** = **create + update + delete** (by default, a user able to write is able to modify and delete).
  - **`manage`** = **exceptional case**, reserved for organizational administration of the resource (invite members, modify organization settings, manage other users' access rights). Does **not** apply to standard business resources (there will be no `screen.company.manage`).

### 2. Composite roles — 4 canonical roles

Each role is a **set of atomic permissions** explicitly listed in `realm-chapsmind.json` (no composite chaining → composite on Keycloak side, each role lists its own atomics). **Functionally**, capabilities increase: permissions of `viewer` are included in `editor`, which are included in `manager`, which are included in `admin`. In other words, `admin` is a **super-set** that covers all capabilities of lower roles.

| Role          | Scope        | Positioning                                                                                                                                                                                |
| ------------- | ------------ | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| **`viewer`**  | Organization | Read-only access to business resources and organization configuration                                                                                                                      |
| **`editor`**  | Organization | Read + write access to business resources (no organization administration)                                                                                                                 |
| **`manager`** | Organization | `editor` capabilities + organization administration (members, settings, tokens)                                                                                                            |
| **`admin`**   | Cross-tenant | `manager` capabilities **in all organizations** + `admin.*` permissions (monitoring, global orgs/users CRUD, tasks, workflows, costs). **Reserved for ChapsVision collaborators** (see §5) |

**Assignment rules**:

1. **One role per user (per organization)**: a user has exactly **one** org role among `viewer` / `editor` / `manager` in the organizations they belong to.
2. **`admin` makes org roles unnecessary**: a ChapsVision admin already has all the capabilities of org roles in each organization they visit. They are therefore never additionally assigned to `viewer`/`editor`/`manager`. On Keycloak, users with `admin` do not have explicit membership in client organizations — they access them via their global role.
3. **No arbitrary atomic combinations** are supported. Atomic permissions exist for the capability matrix but their direct assignment (outside composite) is forbidden by convention — administration always goes through the 4 composites.

### 3. Complete atomic permissions matrix

A structuring principle: **inheritance applies only to resources contained in a folder** (see §9). Organization and admin permissions maintain fine granularity per resource (member / settings / token / organization / user / task / workflow / cost). Only business resources that live in a folder (companies, watchfiles, actors, sources, documents, chats, streams, events, deliveries) lose their dedicated permissions in favor of `folder.*`.

**Module `folder`** (cross-cutting resource, carried by `global-service`):

- `folder.read` — read a folder and all its inherited resources
- `folder.write` — create / modify / delete the folder and all its inherited resources
- `folder.manage` — `manage` exception: manage the folder ACL (sharing), archive / restore, permanently delete (see §8)

**Module `screen`** (resources outside folder — therefore no inheritance):

- `screen.conversation.read`, `screen.conversation.write` — Chapse Assist conversations, user-scoped (not attached to a folder)

**Module `organization`** (organizational administration, fine granularity preserved):

- `organization.member.read`, `organization.member.write` — invite / remove / update a member
- `organization.settings.read`, `organization.settings.write` — organization settings
- `organization.token.read`, `organization.token.write` — consultation and top-up of AI credits

**Module `admin`** (cross-tenant, ChapsVision-only, fine granularity preserved):

- `admin.organization.read`, `admin.organization.write` — global organizations CRUD
- `admin.user.read`, `admin.user.write` — global users CRUD
- `admin.task.read`, `admin.task.write` — monitoring / retry Celery & background tasks
- `admin.workflow.read`, `admin.workflow.write` — Dify / n8n workflows
- `admin.cost.read` — cost analysis (read-only, no `write`)

**What no longer exists** (absorbed by `folder.*` via inheritance, see §9): `screen.company.*`, `screen.folder.*` (module-prefixed), `target.watchfile.*`, `target.actor.*`, `target.source.*`, `target.document.*`, `target.chat.*`, `stream.stream.*`, `stream.delivery.*`.

Resources that are strictly internal and not exposed to end users (outbox, translation_job, encryption, url, etc.) are not subject to atomic permissions; they are protected by the Internal JWT or are technical implementation details.

### 4. Legacy → new mapping

This mapping covers **all** roles currently referenced (Keycloak realm + application code + tests + documentation). It is intended for developers to understand what changes, why, and where to fix code.

#### 4.1 Roles declared in `realm-chapsmind.json`

| Legacy                | Current description                                                      | New                                                                                      | Target composite | Nature of change                                                                                                                                                                   |
| --------------------- | ------------------------------------------------------------------------ | ---------------------------------------------------------------------------------------- | ---------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `company.view`        | View companies in own organization                                       | `folder.read` (inherited)                                                                | `viewer`         | `company` is a folder-scoped resource: permission is absorbed by `folder.read` via inheritance (§9). Folder ACL determines which folders (and thus which companies) the user sees. |
| `company.create`      | Add company screens                                                      | `folder.write` (inherited)                                                               | `editor`         | Same: creating a company in a folder requires `folder.write` on that folder. No dedicated permission.                                                                              |
| `company.delete`      | Soft-delete and restore companies                                        | `folder.write` (inherited)                                                               | `editor`         | Deleting a company = writing to its parent folder. Merged into `folder.write` via inheritance.                                                                                     |
| `organization.read`   | Read folders and companies shared with user                              | composite `viewer`                                                                       | —                | **Does not survive** as atomic permission: becomes composite `viewer` role. Effective ability to read shared folders is managed by folder ACL + composite role.                    |
| `organization.write`  | Create folders, create items in folders, edit/share/delete owned folders | composite `editor`                                                                       | —                | **Does not survive**: becomes composite `editor` role. Business capabilities are managed by `folder.write` + folder ACL.                                                           |
| `organization.manage` | Manage organization members and their permissions                        | `organization.member.write` + `organization.settings.write` + `organization.token.write` | `manager`        | **Disaggregated** into three distinct atomics (fine granularity preserved for organization administration).                                                                        |
| `admin.organizations` | Global admin access to all organizations                                 | composite `admin`                                                                        | —                | **Does not survive** as single atomic: becomes composite `admin` role that aggregates all `admin.*`. Organizations CRUD capability is carried by `admin.organization.read/write`.  |
| `admin.tasks`         | Admin access to manage and view all tasks                                | `admin.task.read` + `admin.task.write`                                                   | `admin`          | Renamed to singular + read/write separation.                                                                                                                                       |
| `admin.costs`         | Admin access to AI usage cost analysis                                   | `admin.cost.read`                                                                        | `admin`          | Renamed to singular. **No `write`**: read-only.                                                                                                                                    |
| `stream.read`         | Can view streams and deliveries                                          | `folder.read` (inherited)                                                                | `viewer`         | A `stream` is folder-scoped (column `folder_id`): permission absorbed.                                                                                                             |
| `stream.write`        | Can create, edit, delete streams                                         | `folder.write` (inherited)                                                               | `editor`         | A stream is folder-scoped: permission absorbed.                                                                                                                                    |
| `admin` (composite)   | Full administrative access                                               | composite `admin` redefined                                                              | —                | Composite **redefined** with the new matrix (cf. §2).                                                                                                                              |

**Note**: `realm-chapsmind.json` contains a duplicate `company.delete` (lines 312-321). To be cleaned up during refactoring.

#### 4.2 Phantom roles (used in code, never declared in Keycloak)

These permissions are referenced by application code but do not exist in the realm — they are therefore **never present** in a real JWT. Checks using them silently pass as `false`, masking potential bugs.

| Phantom           | Where referenced                                                                                                                                                                 | New                                            | Target composite | Action                                    |
| ----------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ---------------------------------------------- | ---------------- | ----------------------------------------- |
| `screen.create`   | `apps/front/src/types/role.ts:97` (`LEGACY_ROLES` constant), `apps/front/src/composables/usePermissionBasedHelp.ts:32`, `apps/front/src/pages/companies/create.vue` (route meta) | `folder.write` (inherited)                     | `editor`         | Replace in code, remove legacy constant.  |
| `target.create`   | `apps/front/src/types/role.ts:97`, `apps/front/src/composables/usePermissionBasedHelp.ts:33`, `apps/front/src/components/admin/RolePermissionsModal.vue`                         | `folder.write` (inherited)                     | `editor`         | Replace in code, remove from admin modal. |
| `admin.workflows` | `apps/screen/tests/integration/test_admin_auth_integration.py` (mock auth patches)                                                                                               | `admin.workflow.read` + `admin.workflow.write` | `admin`          | Declare in realm, update tests.           |

#### 4.3 Entirely new atomic permissions

These permissions have **no** direct legacy equivalent:

**Module `folder`** (cross-cutting, new):

- `folder.read`, `folder.write`, `folder.manage` — collectively replace all fine-grained permissions `screen.company.*`, `screen.folder.*`, `target.watchfile.*`, `target.actor.*`, `target.source.*`, `target.document.*`, `target.chat.*`, `stream.stream.*`, `stream.delivery.*` via the inheritance mechanism (§9).

**Module `screen`** (resources outside folder):

- `screen.conversation.read`, `screen.conversation.write` — Chapse Assist, user-scoped, no folder parent. Previously: no enforcement.

**Module `organization`** (fine granularity preserved):

- `organization.settings.read`, `organization.settings.write` — previously implicit in `organization.manage` (disaggregated).
- `organization.token.read`, `organization.token.write` — same.

**Module `admin`** (fine granularity preserved):

- `admin.organization.read`, `admin.organization.write` — disaggregation of `admin.organizations`.
- `admin.user.read`, `admin.user.write` — previously implicit in `admin.organizations`.
- `admin.task.read`, `admin.task.write` — replace `admin.tasks`.
- `admin.workflow.read`, `admin.workflow.write` — replace phantom `admin.workflows`.
- `admin.cost.read` — replace `admin.costs` (read-only).

#### 4.4 Summary

| Metric                                   | Quantity                                                                              |
| ---------------------------------------- | ------------------------------------------------------------------------------------- |
| Legacy roles in Keycloak to remap        | 12                                                                                    |
| Phantom roles to clean up in code        | 3                                                                                     |
| Atomic permissions in new model          | 20 (folder: 3; screen.conversation: 2; organization: 6; admin: 9)                     |
| Composites to (re)define                 | 4 (`viewer`, `editor`, `manager`, `admin`)                                            |
| Code files impacted (order of magnitude) | ~50 (backends + frontend + tests + realm + Keycloak scripts + new internal endpoints) |
| Production data migration                | 0 (no production data)                                                                |

### 5. ChapsVision status detection (for `admin`)

The composite role `admin` is **cross-tenant** and therefore can only be carried by internal ChapsVision collaborators. Guard implemented in `global-service` at the time of external JWT validation, **before** emission of the Internal JWT.

#### 5.1 Detection rules

Verification order:

1. **Priority 1 — Keycloak claim `is_chapsvision_staff: true`**. A custom protocol mapper to be declared on clients `chapsmind-front` and `chapsmind-global-service-back` exposes this user attribute in the access token.
2. **Priority 2 — authorized email domain**. The JWT's `email` claim is compared to a whitelist of domains configurable per environment via the `CHAPSVISION_STAFF_DOMAINS` variable:
   - **Dev / test**: `chapsvision.com,chapsmind.local` (realm test users use `@chapsmind.local`)
   - **Staging**: `chapsvision.com`
   - **Production**: `chapsvision.com`

#### 5.2 Failure behavior

If a JWT carries the `admin` role **and** neither the claim nor the domain authorizes the bearer:

- **The request is immediately rejected** with `403 Forbidden` and message `"admin role requires ChapsVision staff identity"`.
- **No Internal JWT is issued**. The user cannot use the application until the configuration is fixed (removal of admin role or validation of ChapsVision membership).
- **Structured security log**: `event=admin_role_denied`, `sub`, `email`, `is_staff_claim`, `request_path`, `client_ip`.
- **Alerting**: the event triggers a security alert (Grafana/Prometheus) as it signals either a Keycloak configuration error or an attempted privilege escalation.

The choice of **blocking 403** (rather than silent role filtering) is deliberate: a user with the `admin` role in their token is implicitly a strong signal. Allowing them to work as a simple member suggests the Keycloak config is correct when it is not. Better to have a visible and immediate error.

#### 5.3 Implementation (gateway)

Pseudo-code on `global-service` side:

```python
# apps/global-service/app/core/admin_guard.py (new)
from typing import Final
from .config import settings

CHAPSVISION_STAFF_DOMAINS: Final[set[str]] = {
    d.strip().lower()
    for d in settings.CHAPSVISION_STAFF_DOMAINS.split(",")
    if d.strip()
}

def is_chapsvision_staff(user: OIDCUser) -> bool:
    if user.extra_fields.get("is_chapsvision_staff") is True:
        return True
    email = (user.email or "").lower()
    if "@" not in email:
        return False
    domain = email.rsplit("@", 1)[1]
    return domain in CHAPSVISION_STAFF_DOMAINS

def enforce_admin_guard(user: OIDCUser) -> None:
    roles = user.extra_fields.get("realm_access", {}).get("roles", [])
    if "admin" not in roles:
        return
    if not is_chapsvision_staff(user):
        logger.error(
            "admin_role_denied",
            extra={"sub": user.sub, "email": user.email, "client_ip": request.client.host},
        )
        raise HTTPException(
            status_code=403,
            detail="admin role requires ChapsVision staff identity",
        )
```

Enforcement executes **before** `mint_internal_jwt()`. Internal backends never see an `admin` role associated with a non-ChapsVision user.

### 6. Keycloak = single source of truth

- All atomic and composite roles are declared in `infra/files/realm-chapsmind.json`, imported at realm bootstrap.
- The 4 composites are Keycloak **composite roles** pointing explicitly to the **complete list** of their atomics (no chained inheritance, cf. §2).
- Realm test users are assigned to **a single composite role** (not a flat list of atomics) — this is what makes administration understandable by the organization admin.
- The initialization script `infra/scripts/setup-keycloak.sh` imports the realm, adds users to the ChapsMind Dev Organization, and configures the `is_chapsvision_staff` protocol mapper. **Executable in dev, test, staging and prod** (cf. §7).
- No permission definition is invented on the code side: frontend and backends **consume** JWT roles without redefining them.

### 7. Frontend impact

The shift to 4 composites significantly modifies frontend code. Associated decisions:

#### 7.1 Exposing the permissions matrix in the Vue bundle

The router guard (`apps/front/src/router/index.ts`) reads `meta.permissions` declared in each page (`<route lang="yaml">` blocks). These declarations are **statically embedded in the JS bundle**: a curious user can inspect the client code and discover the entire permissions model.

**Decision: we maintain this behavior.** Permissions are not secrets — they describe public business rules. The real protection is on the backend (server enforcement). Hiding the matrix on the client provides no security gain and degrades UX (redirects to `/403` before API calls, hiding unavailable buttons, contextual help). Single rule: **never trust the frontend for authorization**. The backend is always the last line of defense.

#### 7.2 Retrieving user roles

User roles are already available in the Keycloak JWT (`realm_access.roles`), extracted by `oidc-client-ts` and stored in the `auth` store. **No additional backend call** is necessary: since a Keycloak composite is resolved at token emission, the JWT directly contains the complete list of the user's atomics.

**Consequence**: no `/api/me/permissions` route to create, no additional layer. Silent refresh is sufficient to update permissions after a Keycloak-side modification.

#### 7.3 Refactoring composables

- **`useRoles.ts`**: greatly simplified. No longer maps permissions to "virtual" roles — it directly reads one of the 4 JWT roles and exposes it via `currentRole: Ref<'viewer' | 'editor' | 'manager' | 'admin' | null>`.
- **`useCompanyPermissions.ts`**, **`useFolderPermissions.ts`**, **`useTeamPermissions.ts`**: retained but aligned to new atomic names (`screen.company.*`, `screen.folder.*`, `organization.member.*`).
- **`usePermissionBasedHelp.ts`**: updated with new names, removal of legacy fallbacks.
- **`useFolderPermissions.ts`** retains its resource-level ACL logic (`folder.share_role`, `folder.owner`) — orthogonal to RBAC.

#### 7.4 Refactoring admin permissions management module

The `RolePermissionsModal.vue` modal is refactored around **a single selector** for composite roles — the "Custom Permissions" tab with atomic toggles is removed. Two distinct cases:

**Case 1 — Organization manager (non-admin)**
A manager delegating a role to an organization member sees only 3 choices: `viewer`, `editor`, `manager`. The composite `admin` **does not appear** in the selector — it is visually absent, not just disabled. The rule is applied by a `canAssignAdmin = authStore.hasRole('admin')` check upstream of rendering.

**Case 2 — ChapsVision admin delegating / elevating a user to `admin`**
The action is considered critical: the `admin` role grants cross-tenant access to **all** organizations, all business resources, and all admin endpoints (`admin.*`). An assignment error is hard to recover from and can expose client data to an unauthorized collaborator.

The mandatory flow is as follows:

1. **Selection of `admin` in the selector** → display of a **blocking confirmation modal** (`ConfirmAdminElevationModal.vue`, new component).
2. **Modal content**:
   - Title: "Elevate <username> to ChapsVision Admin role".
   - Warning block visually distinctive (color `error`) recalling implications:
     - **Cross-tenant access**: the user will be able to view and modify data of **all** client organizations.
     - Access to `admin.*` endpoints: managing orgs, users, tasks, workflows, costs.
     - ChapsVision guard (§5) applies: if the target's email is not in `CHAPSVISION_STAFF_DOMAINS` or does not have the `is_chapsvision_staff` claim, the user **will no longer be able to log in** (systematic 403).
     - This elevation is traced in the audit log (`event=admin_role_granted`, `actor`, `target`, `timestamp`).
   - Summary of target information (username, email, current organization).
3. **Mandatory type-to-confirm field**: a text input where the admin must enter **exactly** the string `I GRANT ADMIN TO <username>` (where `<username>` is the target's username). As long as the input does not match character-for-character (case-sensitive), the "Confirm elevation" button remains disabled. The username is injected to prevent an admin from memorizing the phrase and clicking too quickly on the wrong user.
4. **Double commitment action**: "Cancel" button in `neutral` default focus, "Confirm elevation" button in `error` (red), never primary — forcing deliberate clicking.
5. **Post-confirmation**: API call to `POST /api/organizations/{orgId}/members/{userId}/elevate-admin` (new endpoint on `global-service` side). Backend enforces the ChapsVision guard **at assignment** (fail fast, not just at login):
   - Verifies that the caller is themselves `admin` (guard §5).
   - **Verifies that the target is a ChapsVision collaborator** via the same rule as §5: claim `is_chapsvision_staff: true` in Keycloak profile **or** email whose domain is in `CHAPSVISION_STAFF_DOMAINS`. If the target is not eligible, returns `422 Unprocessable Entity` with `detail: "target user is not ChapsVision staff; cannot assign admin role"`. **No** modification is made in Keycloak.
   - If eligible: assigns the composite role `admin` via Keycloak Admin API and writes an entry to the audit log (`event=admin_role_granted`, `actor`, `target`, `timestamp`).

This backend check at assignment is the **true enforcement point** — the frontend modal is ergonomic security, not cryptographic security. The runtime guard §5 remains in place as a last resort (case of an admin assigned outside the UI, directly via Admin API or import script).

#### 7.4bis Preventive frontend validation (proposal)

To prevent the admin from reaching the type-to-confirm modal only to receive a `422` from the backend, the frontend can **gray out and annotate** the `admin` option in the selector when the target's email does not appear eligible:

- The `auth` store (or a new composable `useChapsvisionPolicy`) exposes the list `chapsvisionStaffDomains` — retrieved from a lightweight endpoint `GET /api/config/chapsvision-policy` that returns `{ "staff_domains": ["chapsvision.com", "chapsmind.local"] }`. No need to expose logic or custom claim — just the domain list, non-sensitive information.
- In `RolePermissionsModal.vue`, if the target's email does not have a domain in the list, the `admin` option appears disabled with a tooltip: "This user is not a ChapsVision collaborator (email: @client.com). Contact your ChapsMind contact to modify their profile."
- The option remains visually present (unlike Case 1 for a non-admin manager where it is hidden): a ChapsVision admin must understand **why** they cannot proceed, not see it disappear without explanation.

This frontend check is **advisory only**: even if the frontend is bypassed (JS modification, direct API call), the backend refuses at assignment, and as a last resort the runtime guard §5 blocks any request.

**Downgrading from `admin`**: the inverse modal (`ConfirmAdminRevocationModal`) is simpler — standard confirmation without type-to-confirm, as downgrading does not create immediate security risk (it reduces it). The audit log is preserved.

**Reusable component**: the type-to-confirm pattern is extracted into a shared component `ConfirmCriticalActionModal.vue` (props: `title`, `warnings[]`, `confirmPhrase`, `targetLabel`) to be reused on other critical actions in the future (organization deletion, GDPR purge, etc.).

#### 7.5 Organization role elevation notifications

Any **elevation** of a user to a role **higher than `editor`** (i.e., to `manager` or `admin`) within an organization triggers a notification. No notification for simple promotion `viewer` → `editor` (operational routine with no governance impact). No notifications for demotions (covered by audit log, no need to broadcast).

**Recipients**:

- **All `manager` of the concerned organization** — transparency among co-administrators, allows detection of suspicious elevation quickly.
- **The person targeted by the elevation** — explicit confirmation of their new role (includes a summary of new capabilities).

**Channels**: email (mandatory, traceable). The email channel cannot be disabled by the user for this category — it is a security notification.

**Structured content**: `{ target_user, target_user_email, organization, old_role, new_role, actor_user, actor_email, timestamp }`. The text explicitly mentions new capabilities opened (e.g., "this role allows you to manage organization members and modify its settings").

**Special case: ChapsVision `admin`**: elevation to the cross-tenant composite role `admin` triggers, in addition to the above notification:

- Sending to **all ChapsVision admins** (not just managers of the target org — this is a cross-tenant scope change).
- Entry in the structured audit log (cf. §7.4 — `event=admin_role_granted`, `actor`, `target`, `timestamp`).

### 8. Articulation of Keycloak RBAC × Folder ACL (assignment rules and cardinality)

Folder sharing introduces a **resource-level ACL** (role `owner` / `editor` / `viewer` **on a given folder**) that adds to Keycloak RBAC. The mechanism for effective resolution of rights is detailed in §9 (voter + internal endpoints). The conceptual access rule is:

| User's composite role | Folder access                                                                                               |
| --------------------- | ----------------------------------------------------------------------------------------------------------- |
| `viewer` (org)        | Folders where they are **explicitly shared** (ACL role `viewer` at minimum)                                 |
| `editor` (org)        | Folders where they are **explicitly shared** (ACL role `viewer`, `editor` or `owner`)                       |
| `manager` (org)       | **All folders in their organization**, no explicit sharing needed. Full rights as `owner` (manage included) |
| `admin` (ChapsVision) | **All folders in all organizations**, no explicit sharing needed. Full rights as `owner`                    |

The subsections below (§8.1 to §8.4) therefore concern only **`viewer`** or **`editor`** users: they alone need explicit ACL sharing to access a given folder. A manager or admin is never listed in a folder's ACL.

#### 8.1 Organization role → folder ACL role compatibility

The ACL role assignable on a folder shared with a user is **capped** by the user's organization role. This rule therefore applies only to `viewer` and `editor` (managers and admins are not shared):

| Target user's org role | Assignable folder ACL roles                      |
| ---------------------- | ------------------------------------------------ |
| `viewer` (org)         | `viewer` folder only                             |
| `editor` (org)         | `viewer` folder, `editor` folder                 |
| `manager` (org)        | _Not shared_ — default access to all org folders |
| `admin` (ChapsVision)  | _Not shared_ — default cross-tenant access       |

**Why**: an organizational `viewer` must not be able to modify content, even if a manager tried to designate them as `editor` on a folder — the ACL cannot exceed RBAC. This rule is **double-enforced**:

- **Frontend**: in the sharing modal, the folder role selector is filtered based on the target's org role. Unauthorized options are hidden (not just disabled) as they are not applicable. If the target's org role changes later (e.g., demotion), incompatible existing assignments are automatically brought back to the allowed cap (see §8.3).
- **Backend**: the sharing endpoint refuses (`422 Unprocessable Entity`) any assignment where the requested ACL role exceeds the target's current org role. Explicit message: `"cannot assign folder role '{acl_role}' to user with organization role '{org_role}'"`.

**Note on terminology**: in this document "owner (folder)" designates the ACL role; "owner" does **not** exist as an organization role — the org role that allows being designated as owner of a folder is `manager`.

#### 8.2 Folder owner cardinality

A folder must have **at least one owner at all times**. Multiple owners are allowed and even encouraged (continuity in case of absence, co-responsible teams). Derived rules:

1. **Creation**: the user who creates the folder automatically becomes its first owner. Its subsequent deletion follows the rules below.
2. **Removing an owner** (revoking sharing, downgrading `owner` → `editor`/`viewer`, removing the user from the organization): the operation is authorized **only if** at least one other active owner remains after the operation.
3. **If the operation would leave the folder without an owner**: two strategies depending on context:
   - **Interactive (UI)**: the action is refused and a second modal forces the admin to **designate a new owner** among current folder editors/viewers **or** among organization members (respecting rule §8.1). The initial operation is validated only after successful designation.
   - **Non-interactive (batch user deletion, Keycloak webhook)**: the operation is **refused** and returns an explicit error; the admin must resolve manually. No automatic fallback (e.g., "designate a ChapsVision admin by default") as this would create silent orphans.
4. **Folder with no users**: impossible to remove all users from a folder — the last owner remains mandatory. To "archive" a folder, use the dedicated action (if it exists) that preserves ownership while marking it inactive.

Rule enforced on the backend (transactionally, to avoid race condition between concurrent owner removals) and on the frontend (disabling the "Remove" or "Demote" button on the last owner with explicit tooltip).

#### 8.3 Effect of organization role change on folder ACL

When a manager demotes a user (e.g., `editor` → `viewer`), their existing folder assignments may become incompatible with §8.1. Policy:

- **Demotion `editor` → `viewer` (org)**: all their `editor` or `owner` assignments on folders are **brought back to `viewer`**. If this removes the last owner of a folder, apply §8.2.3 (error, force designation).
- **Demotion `manager` → `editor` (org)**: all their `owner` assignments on folders are **brought back to `editor`**. Same rule §8.2.3 if last owner.
- **Promotion** (e.g., `viewer` → `editor`): no automatic folder ACL changes; assignments remain `viewer` unless explicitly modified.
- **Removal from organization** (user deleted): all their folder ACL entries are deleted. If they were the last owner of a folder, §8.2.3 applies.

These cascades are applied in a transaction on the backend, with audit log structured per affected folder.

#### 8.4 Actions reserved to folder owners

Certain actions on a folder can only be executed by its **owners** (in the sense of folder ACL). It is not enough to be `editor` ACL + `editor` org to perform them — management of sharing and folder lifecycle is reserved for those responsible.

| Action                                                       | Min ACL role       | Min org role                               | Notes                                  |
| ------------------------------------------------------------ | ------------------ | ------------------------------------------ | -------------------------------------- |
| View folder and its items                                    | `viewer` folder    | `viewer` org                               | Basic reading                          |
| Add/modify/delete items in folder                            | `editor` folder    | `editor` org                               | Business content                       |
| **Manage sharing** (add/remove users, change their ACL role) | **`owner` folder** | `manager` org (implicit via §8.1)          | Reserved to owners                     |
| **Archive / restore folder**                                 | **`owner` folder** | `manager` org (implicit)                   | Soft-delete                            |
| **Permanently delete folder**                                | **`owner` folder** | `manager` org (implicit) + type-to-confirm | Critical action, reuses component §7.4 |

**Admin case** — per §2, a user with the composite role `admin` functionally inherits the capabilities of `manager` in all organizations. They can therefore create, view, modify, share, archive and delete any folder **without being explicitly listed in the folder's ACL**. They may also be (someone can voluntarily share it on a folder), but this is not necessary since they access the folder via their global role. An admin's access and actions on client organization data remain **traced** in the audit log for support traceability.

**Enforcement**:

- **Frontend**: "Share", "Archive", "Delete" buttons are hidden for an `editor`/`viewer` folder (not just disabled — they have no reason to be visible). A tooltip on the sharing list indicates "Only folder owners can modify sharing". For a `manager` user (in their org) or `admin` (cross-tenant), all controls are accessible without restriction.
- **Backend**: each sharing/archiving/deletion endpoint verifies that the caller has a `manage` right resolved by the voter (cf. §9.2). Refusal → `403 Forbidden` with `detail: "only folder owners can manage sharing"` / `"only folder owners can archive this folder"`.

#### 8.5 Folder sharing notifications

Any modification of a folder's ACL (adding a user, modifying their ACL role, removing) triggers a notification.

**Recipients**:

- **All current owners of the folder** — co-responsibility of the folder, transparency on who accesses it.
- **The person targeted by the sharing** — onboarding (knows they have access and to what) or exit (knows they lost access).

Organization managers and ChapsVision admins are **not** notified by default, unless they have access as implicit owners of the folder (§8 intro) — notifications concern explicit ACL modifications, not role bypasses.

**Channels**: email. For this category, the user can disable email (it is operational transactional, not critical security).

**Structured content**:

- On add: `{ folder, organization, target_user, role_assigned, actor }` + direct link to the folder.
- On modify: `{ folder, target_user, old_role, new_role, actor }`.
- On remove: `{ folder, target_user, actor, reason: "unshare" | "org_role_downgrade" | "org_removal" }`.

**Cascade §8.3** (org role change triggering ACL adjustment): each affected folder triggers an individual notification. A summary digest is possible to avoid spam when the cascade affects >5 folders (to be specified in implementation).

### 9. Folder → resources inheritance, voter pattern and internal endpoints

The model relies on three technical building blocks: the **inheritance mechanism** (rules), the **centralized Python voter** (decision logic), and **internal service-to-service endpoints** exposed by `global-service` so that `screen`, `stream` and `target` can delegate checks.

#### 9.1 Inheritance principle

A resource belonging to a folder **has no rights of its own**. Its access right is **strictly derived** from the user's right on the parent folder.

- `folder.read` on a folder ⇒ read all its resources (companies, watchfiles, actors, sources, documents, chats, streams, events, deliveries).
- `folder.write` on a folder ⇒ create/modify/delete its resources.
- `folder.manage` on a folder ⇒ manage sharing, archive, delete the folder itself (cf. §8.4).

If a resource belongs to multiple folders (case theoretically supported by the `folder_items` table), access is granted if **at least one** of the parent folders grants the right (OR policy). Today, a resource is attached to only one folder at a time; the OR rule is a guarantee of future extensibility.

#### 9.2 Centralized decision matrix (voter)

The decision is made in `global-service` — sole source of truth for folders, their organization and ACL. Decision matrix:

| User condition                                    | `read` | `write` | `manage` |
| ------------------------------------------------- | ------ | ------- | -------- |
| Composite role `admin`                            | ✓      | ✓       | ✓        |
| Composite role `manager` + folder.org == user.org | ✓      | ✓       | ✓        |
| Folder ACL = `owner`                              | ✓      | ✓       | ✓        |
| Folder ACL = `editor`                             | ✓      | ✓       | ✗        |
| Folder ACL = `viewer`                             | ✓      | ✗       | ✗        |
| None of the above                                 | ✗      | ✗       | ✗        |

The `manager + same org` condition is verified _after_ the admin rule to avoid unnecessary DB round-trip for admins.

#### 9.3 Python voter pattern (on `global-service` side)

The voter is implemented as an injectable service, not a FastAPI decorator. Internal endpoints and gateway local endpoints call it explicitly. Proposed pattern:

```python
# apps/global-service/app/services/folder_voter.py (new)
from dataclasses import dataclass
from enum import Enum

class FolderAction(str, Enum):
    READ = "read"
    WRITE = "write"
    MANAGE = "manage"

class FolderDecisionReason(str, Enum):
    ADMIN_BYPASS = "admin_bypass"
    MANAGER_ORG_BYPASS = "manager_org_bypass"
    ACL_OWNER = "acl_owner"
    ACL_EDITOR = "acl_editor"
    ACL_VIEWER = "acl_viewer"
    NOT_SHARED = "not_shared"
    WRONG_ORG = "wrong_org"
    FOLDER_NOT_FOUND = "folder_not_found"

@dataclass(frozen=True)
class FolderDecision:
    allowed: bool
    reason: FolderDecisionReason
    role: str | None  # "owner" | "editor" | "viewer" | None

class FolderVoter:
    def __init__(self, db: Session): ...

    def authorize(self, user: UserContext, folder_id: str, action: FolderAction) -> FolderDecision:
        # 1. admin.* → ADMIN_BYPASS
        # 2. folder not found → FOLDER_NOT_FOUND
        # 3. manager + folder.org == user.org → MANAGER_ORG_BYPASS
        # 4. lookup ACL (owner/editor/viewer)
        # 5. map ACL × action → decision
        ...

    def authorize_batch(self, user: UserContext, folder_ids: list[str], action: FolderAction) -> list[FolderDecision]:
        # Single SQL query, returns decisions in same order
        ...

    def list_accessible_folders(self, user: UserContext, action: FolderAction, item_type: str | None = None) -> list[str]:
        # Used for list endpoints pagination
        ...
```

The voter is **pure** (no network I/O, only DB), easy to unit test.

#### 9.4 Internal `global-service` → backends endpoints

Internal backends (`screen`, `stream`, `target`) call these endpoints via their `Authorization: Internal <JWT>`. The user_id is read from the JWT (claim `sub`), not from the body — avoids impersonation risks.

**A — Authorize on a single folder** (hot path point-lookup)

```http
POST /api/internal/folders/{folder_id}/authorize
Authorization: Internal <JWT>
Content-Type: application/json

{"action": "read" | "write" | "manage"}

→ 200 OK
{"allowed": true, "reason": "manager_org_bypass", "role": null, "organization_id": "<uuid>"}

→ 403 Forbidden
{"allowed": false, "reason": "not_shared"}
```

**B — Authorize batch** (list endpoints)

```http
POST /api/internal/folders/authorize-batch
{"action": "read", "folder_ids": ["<uuid>", ...]}   // max 500 per call

→ 200 OK
{"allowed_folder_ids": ["<uuid>", ...], "bypass": "admin" | "manager" | null}
```

**C — Resolve folder parent of an item** (indirectly folder-scoped resources: Task, StreamDelivery, CompanyEnrichment, etc.)

```http
POST /api/internal/folders/resolve-for-items
{"items": [{"type": "company", "id": "1234"}, {"type": "watchfile", "id": "<uuid>"}]}

→ 200 OK
{"items": [{"type": "company", "id": "1234", "folder_ids": ["<uuid>"], "organization_id": "<uuid>"}, ...]}
```

**D — List accessible folders** (efficient pagination on backends)

```http
GET /api/internal/folders/accessible?action=read&item_type=company

→ 200 OK
{"folder_ids": ["<uuid>"], "bypass": "admin" | "manager" | null, "organization_id": "<uuid>"}
```

#### 9.5 Performance / caching / fault tolerance strategy

| Dimension                    | Choice                                                                                                                                                                                                                                                        |
| ---------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Endpoint A latency target    | P99 ≤ 15 ms (same Docker/k8s network, DB index already present)                                                                                                                                                                                               |
| Endpoint B batch size        | 500 folder_ids max (stays under 16 KB body)                                                                                                                                                                                                                   |
| Local backend cache          | LRU in-process per (`user_id`, `folder_id`, `action`), TTL 30s (does not exceed Internal JWT TTL 60s)                                                                                                                                                         |
| Cache invalidation           | Redis Pub/Sub `folder_acl_changed` published by `global-service` on ACL / org role changes. Backends subscribe and flush                                                                                                                                      |
| Degraded mode (gateway down) | **Writes**: fail-closed (`503 Service Unavailable`). **Reads**: fail-closed too — silent fail-open on reads has historically allowed cross-tenant leaks (cf. audit permissions §3.6). Backends can serve stale cache ≤ 30s if available, beyond that it's 503 |
| Observability                | Each decision emits Prometheus counter `folder_authz_decisions_total{action,reason,module}` to alert on abnormal denial rate                                                                                                                                  |

#### 9.6 Evaluated alternative: embed folder rights in Internal JWT

Rather than HTTP round-trips, the gateway could embed in the Internal JWT a pre-resolved claim `accessible_folders: [...]`. Evaluation:

- **JWT size**: one folder UUID ≈ 40 bytes, ~170 folders fit in 8 KB Authorization header. Acceptable for nominal case (users with dozens of folders).
- **Staleness**: Internal JWT TTL 60s bounds freshness. Sharing revocation effective in ≤ 60s — acceptable for `read`, borderline for `write`.
- **`admin` / `manager` case**: too many folders to embed (cross-tenant). Fallback necessary.

**Decision**: hybrid approach — embed a flag `folder_bypass: "admin" | "manager" | null` to cover bypasses without lookup, but keep endpoints A/B/C/D for explicit ACL case. This eliminates 80% of round-trips in practice (majority of manager / admin users only do bypass).

#### 9.7 Centralization of folder management on `global-service`

The folder models (`Folder`, `FolderItem`, `FolderShare`) **already exist** in `apps/global-service/app/models/folder.py`. There is no data migration to do. The refactoring consists of:

1. **Expose internal endpoints A/B/C/D** on the `global-service` side.
2. **Remove folder public endpoints** still present on `screen` (duplication). The gateway proxies sharing / archiving / folder creation operations directly to `global-service`.
3. **Migrate enforcement**: each folder-scoped endpoint of `screen`, `stream`, `target` adds a `@require_folder_access(action=...)` (FastAPI decorator / Symfony subscriber) that calls A or B as appropriate before executing business logic. On the OpenAPI side, an `x-folder-action` is added to the endpoint's `openapi_extra` so the gateway can pre-check before even proxying (fail-fast on denied without touching the backend).
4. **Rename `FolderShare.role` enum** from `reader`/`writer` to `viewer`/`editor` — Alembic migration renaming Postgres enum labels. Direct switchover.
5. **Drop legacy replaced endpoints**: `/accessible-company-ids` (used by screen in `company.py`, `company service`, `global_service_client`, `organization.py` and by global-service in `organization.py`, `internal.py`, `folder service` + tests) is **removed** in favor of generic endpoints C (`resolve-for-items`) and D (`accessible`). All call sites migrate in the same PR.
6. **No dual-run**: the switchover is direct. With no production data and with the `viewer`/`editor` flag re-introduced via plug-and-play Keycloak init script (§6), the dev environment comes clean with each `task init`. Staging and prod environments follow the same migration in a maintenance window.

#### 9.8 Enacted technical decisions

| #   | Question                                                          | Decision                                                                                                                                                                          |
| --- | ----------------------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| 1   | Generic endpoints (A/B/C/D) vs resource-specialized               | **Generic** — single contract for screen, stream, target.                                                                                                                         |
| 2   | Tolerance for 60s staleness on ACL revocations (Internal JWT TTL) | **Accepted** — perf/freshness tradeoff retained, no sync invalidation required.                                                                                                   |
| 3   | Partial embedding in Internal JWT (hybrid §9.6)                   | **Yes** — `folder_bypass: "admin" \| "manager" \| null` claim embedded.                                                                                                           |
| 4   | Rename enum `reader/writer` → `viewer/editor`                     | **Yes, direct switchover**, no dual-run. Simple Alembic migration.                                                                                                                |
| 5   | Enforcement in proxy vs decentralized in each backend             | **Decentralized** via `@require_folder_access` decorator, with gateway pre-check on `x-folder-action` in `openapi_extra` for fail-fast of blocked requests without backend touch. |
| 6   | Keep `/accessible-company-ids`                                    | **Direct drop** in same PR as migration, no staggered deprecation.                                                                                                                |

### 10. Security context propagation in asynchronous processing

A structuring security principle: **no asynchronous task executes "as system"** without user context. Each message/job explicitly carries the identity and organizational context of the actor who triggered the action. A job that loses its context and acts as root is a gaping hole (cf. audit permissions §3.8).

**Scope**: ChapsMind's async processing today includes:

- **Symfony Messenger** (target) — dispatch of business messages (`DocumentSummaryTriggerAgent`, `EventExtractionTriggerAgent`, etc.).
- **FastAPI `BackgroundTasks`** (screen, stream) — post-response tasks.
- **Outbox relay** (cf. `outbox_relay.py` screen + §4.5 ADR-0019) — publication of accumulated events.
- **Workers internal-JWT callers** — e.g. services calling internal endpoints §9.4 outside external HTTP cycle.

(Note: **no Celery currently** in the monorepo. If introduced later, same rules apply.)

#### 10.1 Rule — mandatory security payload

Each message or task queue contains the following fields, **non-optional**:

```json
{
  "authz_context": {
    "actor_id": "<keycloak sub>",
    "actor_username": "<preferred_username>",
    "actor_roles_snapshot": ["editor"],
    "organization_id": "<org uuid>",
    "correlation_id": "<X-Correlation-ID propagated>",
    "triggered_at": "2026-04-23T10:00:00Z",
    "origin": "http_request" | "scheduled" | "event_relay"
  },
  "payload": { ... }    // business data
}
```

- **`actor_roles_snapshot`** is a snapshot at dispatch time. The worker does **not** re-query Keycloak — it uses this snapshot for authz decisions. Rationale: avoid in-flight role changes that can produce inconsistent behavior for a job already queued (mitigated by short TTL via §11 point 1).
- **`origin=scheduled`** covers system jobs (cron, heartbeat). In this case, `actor_id=null`, but the `actor_system_name` field is mandatory (e.g. `"scheduler:outbox-relay"`) for traceability.

#### 10.2 Framework enforcement

**Python (FastAPI + BackgroundTasks)** — a single wrapper `enqueue_task(fn, *args, **kwargs)` automatically positions `authz_context` from the current `Request`. Direct use of `.add_task(fn, ...)` is **forbidden** (CI AST scan). If no context available (system job), the caller must explicitly provide `AuthzContext(origin="scheduled", actor_system_name=...)`.

**PHP (target, Symfony Messenger)** — a decorated `MessageBusInterface` (`AuthzAwareMessageBus`) intercepts each `dispatch()` and attaches an `AuthzContextStamp` (Symfony Messenger Stamp) carrying the security payload. An `AuthzContextMiddleware` Messenger middleware repositions the context in a service equivalent to `TenantContext` before the handler. Handlers access the context via injection, not via global lookup.

#### 10.3 Voter in workers

When a worker consumes a message and will manipulate a folder-scoped resource, it calls the voter §9 via internal endpoints A/B exactly like a synchronous HTTP endpoint. **No bypass "I'm a worker, I skip the check"**. The voter receives context from the message's `authz_context`.

**Tricky case — worker acting on behalf of a disconnected user**: if the target user is no longer active (account disabled, org removed) when the worker consumes the message, the voter will return `403`. Policy: the message is marked `failed_authz`, no retry, and emits an audit log `security.async_authz_denied`. A ChapsVision admin can manually re-process after analysis.

#### 10.4 `correlation_id` propagation

The `correlation_id` of the HTTP request that triggered the job is propagated to the child job(s). A cron that initiates a chain generates its own `correlation_id` UUIDv4 and propagates it. Allows tracing all effects of an original action through async layers — necessary for the audit trail (cf. ADR-0019).

#### 10.5 Tests

Each message handler / background task must have:

- A positive test verifying that the handler properly respects `authz_context` (does not manipulate another org's data by mistake).
- A negative test verifying that without valid `authz_context`, the handler fails (does not execute as root).

CI blocks PRs that introduce a handler without these two tests (same AST scan as §4.3 ADR-0019 for `@audited`).

### 11. Areas for improvement and future evolution

Several structural security improvements have been identified but are deliberately **out of scope V1** of this ADR. They are listed here for traceability and to guide the roadmap.

| #   | Topic                                                                                                                                                                                                                                                                                                                                                                                                                                 | Status                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                    | Target                             |
| --- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ---------------------------------- | -------------------------------------- |
| 1   | **Active JWT revocation** — a revoked access token must stop working in seconds, not ~1h (current TTL). Options: online token introspection (RFC 7662) at each gateway request, or JTI blacklist on gateway with Redis Pub/Sub invalidation on role change.                                                                                                                                                                           | To be decided                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                             | Dedicated evolution ADR            |
| 2   | **Postgres Row-Level Security** — enforce `organization_id` filter at DB level via RLS policies + GUC `app.current_organization_id` set by middleware. Makes isolation structurally unavoidable, even if an endpoint forgets the application filter.                                                                                                                                                                                  | To be done later                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                          | Alembic migration + endpoint audit |
| 3   | **Asymmetric keys for Internal JWT** — replace shared HS256 secret with Ed25519 keys (gateway signs, backends verify via JWKS). Limits lateral propagation if an internal backend is compromised (cf. audit §3.2 — Dify webhook). Risk today **reduced** by network isolation (Docker internal in dev, NetworkPolicy k8s prod) but not zero: backend compromise via third-party webhook allows forging Internal JWT for all others.   | Improvement axis                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                          | Dedicated crypto ADR               |
| 4   | **Contextual conditions in voter** — add context predicates to voter (§9.2): `require_fresh_mfa` (MFA done in last N minutes) for critical actions (admin elevation, impersonation, hard delete), `require_ip_in_range` for certain cross-tenant admin actions. **Fresh MFA** is the first recommended increment.                                                                                                                     | Improvement axis                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                          | ADR MFA + contexts                 |
| 5   | **Optional TTL on org roles** — a manager can assign an `editor` role to a user with an expiration date (`expires_at`). After that, the user automatically reverts to `viewer`. Useful for temporary missions, internships, contractors. Automatic quarterly review for `manager` / `admin` roles (notification to ChapsVision admins). Partially covers JML (Joiners / Movers / Leavers) but requires HR/team process to complement. | Improvement axis                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                          | ADR JML                            |
| 6   | **Uniform error policy** — currently `403 Forbidden` with explicit messages ("target user is not ChapsVision staff", "only folder owners can manage sharing") that leak information (existence vs right). Future policy to draft: return generic `404` when existence/right distinction leaks, reserve detailed messages for audit log.                                                                                               | To be defined in global security policy                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                   | Security policy document           |
| 7   | **Mandatory negative authz tests** — extend CI policy AST beyond `@audited` decorator: for each sensitive endpoint, a test `should_reject_when_user_has_role_X` is required. Allows detection of authorization regressions at CI level rather than production.                                                                                                                                                                        | To be defined in global security policy                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                   | Security policy document           |
| 8   | **JIT impersonation support** — explicit flow for a ChapsVision admin to act on behalf of a user (debug, onboarding support). Target user consent possible, 1h max session, dedicated audit log `admin_cross_tenant.impersonation_*`. Currently the `impersonator` claim exists on target side but no UI flow.                                                                                                                        | Out of scope V1                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                           | Dedicated impersonation ADR        |
| 9   | **`admin` super-user risk — assumed**                                                                                                                                                                                                                                                                                                                                                                                                 | The composite role `admin` retains full bypass on all organizations in V1 (cf. §2 and §8.4). It is a recognized anti-pattern: compromise of a ChapsVision admin session exposes **all** client data. **Deliberate choice** for V1 given the UX impact of stricter redesign. Mitigations in place: guard §5 (double check claim + email domain), elevation with type-to-confirm §7.4, cross-tenant notifications §7.5, critical audit trail §ADR-0019. Future mitigations: fresh MFA (point 4), JIT impersonation (point 8) — which will eventually render bypass unnecessary and allow switching `admin` to a targeted cross-tenant role. | Risk assumed                       | Re-evaluate once points 4 + 8 in place |

**Note**: these areas are ranked by approximate order of security benefit / implementation effort. Points 1, 2, 4 (fresh MFA) have the highest impact, in that order. Point 9 documents a risk **deliberately assumed** in V1 — to be re-challenged when context allows.

---

## Options Considered

### Option 1: Pure atomic model, without composites (improved status quo)

**Description:** Keep flat atomic roles in `<module>.<resource>.<action>` format, without Keycloak composite roles. The "roles" viewer/editor/manager remain a frontend abstraction.

**Pros:**

- Minimal migration from existing.
- Maximum flexibility (any combination of atomic permissions).

**Cons:**

- Organization admin must manually check 20+ permissions for each user.
- Inconsistency risk (a user with `write` without corresponding `read`).
- Frontend UX abstraction (`useRoles.ts`) diverges from Keycloak reality.
- No single source of truth: two definitions of composite roles (frontend vs Keycloak).

### Option 2: Pure composite roles, without exposed atomic permissions (chosen)

**Description:** Push exclusively the 4 composite roles as user interface, while keeping atomic permissions as internal Keycloak mechanics.

**Pros:**

- Simple UX for administrator (4 choices instead of 20+).
- Consistency guaranteed by Keycloak (composites point to atomics).
- Single source of truth (`realm-chapsmind.json`).
- "Custom Permissions" UI can be removed from frontend.

**Cons:**

- Loss of fine-grained flexibility (a user needing `target.watchfile.write` without `screen.company.write` is not expressible without adding a custom composite role).
- Future evolution (new modules, new resources) requires modifying the 4 composites plus atomics.

### Option 3: Hybrid two-tier model (retained as combination of 2 + optional atomic exposure)

**Description:** Canonical composite roles exposed by default, atomic permissions exposed in an "advanced" view for ChapsVision admins only.

**Pros:**

- Simple UX for standard case, flexibility for rare cases.
- Allows fine adjustments without modifying composites.

**Cons:**

- Risk of re-creating current "Quick Roles / Custom Permissions" confusion.
- Two configuration paths to maintain.

**Decision**: **Option 2** with gradual opening toward Option 3 only if real client need emerges. For now, delegation UI exposes only the 4 composites; atomic permissions remain manageable via Keycloak admin console for exceptional cases.

---

## Consequences

### Positive

- **Single source of truth**: Keycloak + `realm-chapsmind.json`. Frontend and backends consume roles, do not redefine them.
- **Simplified admin UX**: 4 choices instead of 20+ atomic permissions to check.
- **Guaranteed consistency**: impossible to have `write` without corresponding `read` thanks to composites.
- **Exhaustive coverage**: each business module has explicit inventory (no more `screen.create` phantom).
- **Guard security `admin`**: impossible to elevate external user to admin by Keycloak mistake, thanks to double check claim + domain.
- **Plug-and-play DX**: `task init` bootstraps complete realm, roles exist, test users assigned — no manual configuration.
- **ADR-0005 alignment**: strengthens principle "Keycloak = single source of truth for org membership and permissions".
- **Extensibility**: add future module = new prefix `<module>.*`, composites extend, backends consume automatically.

### Negative

- **All legacy roles disappear**: clients who already have Keycloak tokens with `organization.read` / `company.create` become invalid. Not blocking as no active production, but requires clear team communication for existing dev environments.
- **Frontend refactor required**: `useRoles.ts`, `useCompanyPermissions.ts`, `useFolderPermissions.ts`, `useTeamPermissions.ts`, `usePermissionBasedHelp.ts` must be updated to consume new roles.
- **Backend refactor required**: all `required_roles=["organization.write"]` / `required_roles=["company.create"]` on screen / stream / global-service must be replaced (see §Implementation Notes).
- **Greater Keycloak dependency**: adding new role requires modifying `realm-chapsmind.json` and re-importing realm. Mitigated by init script.
- **Double admin verification**: slight overhead on each admin request (claim lookup + domain regex), but included in existing JWT validation.

### Neutral

- **Fine-grained resource permissions (folder sharing with owner/editor/viewer roles per folder)**: handled in separate coming ADR, complementary but out of scope here. These ACL-like permissions add to Keycloak RBAC permissions (user must have composite `viewer` **and** be shared on folder to access).
- **List of resources is subject to evolution**: resources listed in §3 reflect current code state. Governance rule to validate: does every new business entity automatically become a permission-scoped resource, or only by architecture decision?
- **Token lifetime and refresh unchanged** compared to ADR-0003.

---

## Implementation Notes

### Bootstrap Keycloak

`infra/files/realm-chapsmind.json` declares:

- The **30+ atomic permissions** (non-composite realm roles).
- The **4 composite roles** (`viewer`, `editor`, `manager`, `admin`) with their `composites.realm` pointing to corresponding atomics.
- Test users assigned to a single composite:
  - `admin` → composite role `admin` (so ChapsVision guard applies, email `admin@chapsvision.com` in dev fixtures).
  - `company_manager` → `manager`.
  - `company_viewer` → `viewer`.
  - `no_access` → no role.
- A custom mapper on clients `chapsmind-front` and `chapsmind-global-service-back` to expose `is_chapsvision_staff` in tokens.

### ChapsVision detection — gateway implementation

```python
# apps/global-service/app/core/admin_guard.py (new)
CHAPSVISION_EMAIL_DOMAIN = "chapsvision.com"

def is_chapsvision_staff(user: OIDCUser) -> bool:
    if user.extra_fields.get("is_chapsvision_staff") is True:
        return True
    email = (user.email or "").lower()
    return email.endswith(f"@{CHAPSVISION_EMAIL_DOMAIN}")

def filter_admin_roles(roles: list[str], user: OIDCUser) -> list[str]:
    if is_chapsvision_staff(user):
        return roles
    filtered = [r for r in roles if not r.startswith("admin.") and r != "admin"]
    if "admin" in roles:
        logger.warning("Non-ChapsVision user attempted admin role", extra={"sub": user.sub, "email": user.email})
    return filtered
```

Filtering applies **before** Internal JWT emission.

### Application-side refactor

- **Backends**: replace all `required_roles=[...]` with new atomic permission names. Prefer atomics at endpoint (not composites), as composites are derived — FastAPI-keycloak and Symfony voter receive "expanded" roles in JWT.
- **Frontend**: simplify composables to directly consume Keycloak roles. `useRoles.ts` no longer calculates, it reads `userRoles` and exposes `currentRole ∈ {viewer, editor, manager, admin}`.
- **Permissions management modal**: replace double tab "Quick Roles / Custom Permissions" with single selector for 4 composites.

### Contract tests

Add automated test that verifies:

- Each atomic permission referenced in code (`required_roles`, `v-if` composable) exists in `realm-chapsmind.json`.
- Each composite contains the expected atomics.
- No legacy role (`company.*`, `organization.read/write/manage` plain, etc.) is used on code side once migration is done.

### Developer experience

```bash
task init          # bootstrap realm + org dev + test users
task up            # start services
# Login with admin / manager123 / viewer123 → consistent permissions
```

No manual steps post-init. The imported realm contains **the entire** model.

---

## References

- [ADR-0003: Keycloak Authentication](./0003-keycloak-authentication.md)
- [ADR-0005: Multi-Tenancy with Keycloak Organizations](./0005-multi-tenancy-keycloak-organizations.md)
- [ADR-0007: User/Org Identification](./0007-keycloak-user-org-identification.md)
- [Keycloak Composite Roles](https://www.keycloak.org/docs/latest/server_admin/#_composite-roles)
- [Keycloak Protocol Mappers](https://www.keycloak.org/docs/latest/server_admin/#_protocol-mappers)
