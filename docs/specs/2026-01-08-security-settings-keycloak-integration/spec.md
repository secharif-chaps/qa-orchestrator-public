# Specification: Security Settings Keycloak Integration

## Goal
Integrate the security settings page with real Keycloak data for session management and activity logging, while removing the unimplemented Two-Factor Authentication and Account Recovery sections.

## User Stories
- As a user, I want to see all my active sessions so that I can monitor where my account is being used
- As a user, I want to revoke sessions from other devices so that I can protect my account if a device is lost or compromised
- As a user, I want to view my recent security activity so that I can detect any suspicious behavior on my account

## Specific Requirements

**Backend Session Endpoints**
- Create `GET /api/users/me/sessions` endpoint to list current user's Keycloak sessions
- Create `DELETE /api/users/me/sessions/{session_id}` endpoint to revoke a specific session
- Create `DELETE /api/users/me/sessions` endpoint to revoke all sessions (with `keep_current` query param option)
- Use `keycloak_admin_service` to proxy requests to Keycloak Admin API
- Extract user ID from authenticated user's JWT token (`user.sub`)
- Return 401 if user is not authenticated, 404 if session not found

**Backend Activity Events Endpoint**
- Create `GET /api/users/me/events` endpoint to fetch user's activity events from Keycloak
- Support pagination via `page` and `size` query parameters
- Support filtering by event type via `type` query parameter (login, logout, profile, password)
- Map Keycloak event types to user-friendly categories
- Return events sorted by timestamp descending (most recent first)

**Keycloak Admin Service Extensions**
- Add `get_user_sessions(user_id: str)` method to fetch sessions via `GET /admin/realms/{realm}/users/{userId}/sessions`
- Add `revoke_session(session_id: str)` method via `DELETE /admin/realms/{realm}/sessions/{sessionId}`
- Add `revoke_all_user_sessions(user_id: str)` method via `DELETE /admin/realms/{realm}/users/{userId}/sessions`
- Add `get_user_events(user_id: str, first: int, max: int, types: list)` method via `GET /admin/realms/{realm}/events?user={userId}`

**Frontend API Layer**
- Create `front/src/api/account.ts` with functions: `getSessions()`, `revokeSession(sessionId)`, `revokeAllSessions(keepCurrent)`, `getActivityEvents(params)`
- Define TypeScript interfaces for Session and ActivityEvent matching Keycloak's response structure
- Use `apiClient` from existing client module
- Note: `account.ts` is for self-service account management, distinct from `user.ts` which handles org user admin

**Frontend Query and Mutation Hooks**
- Create `front/src/queries/account.ts` with `sessionsQuery` and `activityEventsQuery` using Pinia Colada `defineQueryOptions`
- Create `front/src/mutations/account.ts` with `useRevokeSession` and `useRevokeAllSessions` mutations
- Invalidate sessions query after successful revocation mutations

**Session Management Component Updates**
- Update `SessionManagementSection.vue` to fetch real session data via `sessionsQuery`
- Identify current session by comparing session ID with the session ID from auth store
- Display session info: IP address, start time, last access time, and client names
- Show loading and error states appropriately
- Emit events for revoke actions that trigger mutations in parent

**Activity Log Component Updates**
- Update `ActivityLogSection.vue` to fetch real events via `activityEventsQuery`
- Map Keycloak event types (LOGIN, LOGIN_ERROR, LOGOUT, UPDATE_PROFILE, UPDATE_PASSWORD) to display-friendly icons and titles
- Support pagination with "Load More" button or infinite scroll
- Show IP address instead of location (geolocation is out of scope)

**Remove Unused Sections**
- Remove `TwoFactorSection` import and usage from `security.vue` page
- Remove `AccountRecoverySection` import and usage from `security.vue` page
- Keep component files in codebase for potential future use

## Existing Code to Leverage

**Keycloak Admin Service (`back/app/services/keycloak_admin.py`)**
- Use `_make_admin_request()` method pattern for new Keycloak API calls
- Use `_get_admin_token()` for authentication with caching and retry logic
- Follow existing error handling and logging patterns

**Frontend API Client (`front/src/api/client.ts`)**
- Use `apiClient.get()`, `apiClient.delete()` methods for API calls
- Follows existing patterns from `companies.ts`, `team.ts`

**Query/Mutation Patterns (`front/src/queries/`, `front/src/mutations/`)**
- Follow `QUERY_KEYS` pattern for cache management (e.g., `COMPANY_QUERY_KEYS`)
- Use `defineQueryOptions` for queries and `defineMutation` with `useMutation` for mutations
- Use `useQueryCache` to invalidate related queries after mutations

**Existing Security Components**
- `SessionManagementSection.vue` and `ActivityLogSection.vue` have established UI structure and styling
- Use existing i18n keys from `settings.security.*` namespace
- Follow existing component prop/emit patterns

**Auth Store (`front/src/stores/auth.ts`)**
- Access current session information for identifying the active session
- Use `useAuthStore()` to get user context

## Out of Scope
- Two-factor authentication setup (requires Keycloak Account Console redirect or custom extension)
- Account recovery/backup codes management (requires Keycloak Account Console redirect)
- IP geolocation service integration (display raw IP addresses instead)
- Device fingerprinting or user-agent parsing beyond basic display
- Real-time session updates via WebSocket
- Session timeout warnings or automatic refresh
- Export activity log functionality
- Custom date range filtering for activity events
- Keycloak events configuration (events must be pre-enabled in Keycloak realm settings)
- Browser/device type detection from user-agent strings
