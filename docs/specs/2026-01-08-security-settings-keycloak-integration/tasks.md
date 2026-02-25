# Task Breakdown: Security Settings Keycloak Integration

## Overview
Total Tasks: 4 Task Groups (approximately 25 sub-tasks)

This spec integrates the security settings page with real Keycloak data for session management and activity logging, while removing the unimplemented Two-Factor Authentication and Account Recovery sections.

## Task List

### Backend Layer

#### Task Group 1: Keycloak Admin Service Extensions
**Dependencies:** None

- [ ] 1.0 Complete Keycloak admin service session and event methods
  - [ ] 1.1 Write 4-6 focused tests for new Keycloak service methods
    - Test `get_user_sessions()` returns session list
    - Test `revoke_session()` calls correct endpoint
    - Test `revoke_all_user_sessions()` calls correct endpoint
    - Test `get_user_events()` returns events with pagination
    - Mock Keycloak Admin API responses
  - [ ] 1.2 Add `get_user_sessions(user_id: str)` method
    - Call `GET /admin/realms/{realm}/users/{userId}/sessions`
    - Follow existing `_make_admin_request()` pattern
    - Return list of session dicts with: id, ipAddress, start, lastAccess, clients
    - Handle 404 for unknown user
  - [ ] 1.3 Add `revoke_session(session_id: str)` method
    - Call `DELETE /admin/realms/{realm}/sessions/{sessionId}`
    - Return True on 204 success
    - Handle 404 for unknown session
  - [ ] 1.4 Add `revoke_all_user_sessions(user_id: str)` method
    - Call `DELETE /admin/realms/{realm}/users/{userId}/sessions`
    - Return True on 204 success
    - Handle 404 for unknown user
  - [ ] 1.5 Add `get_user_events(user_id: str, first: int, max: int, types: list | None)` method
    - Call `GET /admin/realms/{realm}/events?user={userId}&first={first}&max={max}`
    - If types provided, add `&type={type}` for each type
    - Return list of event dicts with: time, type, ipAddress, details
    - Handle pagination parameters
  - [ ] 1.6 Ensure Keycloak service tests pass
    - Run ONLY the 4-6 tests written in 1.1
    - Verify all methods handle errors appropriately

**Acceptance Criteria:**
- All 4 new methods added to `keycloak_admin_service`
- Methods follow existing patterns (logging, error handling, type hints)
- Tests pass with mocked Keycloak responses

---

#### Task Group 2: Account API Endpoints
**Dependencies:** Task Group 1

- [ ] 2.0 Complete account API endpoints for sessions and events
  - [ ] 2.1 Write 4-6 focused tests for account API endpoints
    - Test `GET /api/users/me/sessions` returns sessions with current session identified
    - Test `DELETE /api/users/me/sessions/{session_id}` revokes specific session
    - Test `DELETE /api/users/me/sessions` revokes all sessions
    - Test `GET /api/users/me/events` returns paginated events
    - Mock authenticated user with `user.sub` as user ID
  - [ ] 2.2 Create `back/app/api/endpoints/account.py` router
    - Create router with prefix `/users/me` and tags `["account"]`
    - Add router to main API (check `back/app/api/router.py` or `main.py`)
    - Follow existing endpoint patterns from `users.py`
  - [ ] 2.3 Implement `GET /api/users/me/sessions` endpoint
    - Extract user ID from `user.sub` (JWT claim)
    - Call `keycloak_admin_service.get_user_sessions(user_id)`
    - Identify current session by comparing session ID (from JWT or request context)
    - Return sessions with `is_current: bool` flag
    - Include: id, ipAddress, start, lastAccess, clients
  - [ ] 2.4 Implement `DELETE /api/users/me/sessions/{session_id}` endpoint
    - Extract user ID from `user.sub`
    - Validate session belongs to user before revoking
    - Call `keycloak_admin_service.revoke_session(session_id)`
    - Return 204 on success, 404 if not found
  - [ ] 2.5 Implement `DELETE /api/users/me/sessions` endpoint
    - Support `keep_current: bool` query parameter (default: True)
    - If `keep_current=True`, get current session ID and skip it
    - Call `keycloak_admin_service.revoke_all_user_sessions(user_id)` or revoke individually
    - Return 204 on success
  - [ ] 2.6 Implement `GET /api/users/me/events` endpoint
    - Support `page`, `size`, and `type` query parameters
    - Calculate `first` from page/size for Keycloak API
    - Map `type` to Keycloak event types (login -> LOGIN, LOGIN_ERROR; logout -> LOGOUT; etc.)
    - Call `keycloak_admin_service.get_user_events()`
    - Return paginated response with events sorted by timestamp desc
  - [ ] 2.7 Create Pydantic schemas for responses
    - `SessionResponse`: id, ip_address, started_at, last_access, clients, is_current
    - `SessionListResponse`: sessions list
    - `ActivityEventResponse`: id, type, display_type, ip_address, timestamp, description
    - `ActivityEventsResponse`: events list, pagination info
  - [ ] 2.8 Ensure account API tests pass
    - Run ONLY the 4-6 tests written in 2.1
    - Verify all endpoints handle auth and errors correctly

**Acceptance Criteria:**
- All 4 endpoints created and working
- Endpoints require authentication (any authenticated user can access their own data)
- Proper error handling (401 unauthorized, 404 not found)
- Pydantic schemas validate responses

---

### Frontend Layer

#### Task Group 3: Frontend API, Queries, and Mutations
**Dependencies:** Task Group 2

- [ ] 3.0 Complete frontend data layer for security settings
  - [ ] 3.1 Write 4-6 focused tests for API functions and hooks
    - Test `getSessions()` calls correct endpoint
    - Test `revokeSession()` makes DELETE request
    - Test `revokeAllSessions()` handles keep_current param
    - Test `getActivityEvents()` handles pagination params
    - Mock apiClient responses
  - [ ] 3.2 Create TypeScript types for session and event data
    - Create `front/src/types/account.ts` with interfaces:
    - `Session`: id, ipAddress, startedAt, lastAccess, clients, isCurrent
    - `SessionsResponse`: sessions array
    - `ActivityEvent`: id, type, displayType, icon, title, description, ipAddress, timestamp
    - `ActivityEventsResponse`: events array, pagination
    - `ActivityEventType`: 'login' | 'login_error' | 'logout' | 'profile' | 'password'
  - [ ] 3.3 Create `front/src/api/account.ts` with API functions
    - `getSessions()` -> GET /api/users/me/sessions
    - `revokeSession(sessionId: string)` -> DELETE /api/users/me/sessions/{sessionId}
    - `revokeAllSessions(keepCurrent?: boolean)` -> DELETE /api/users/me/sessions?keep_current=...
    - `getActivityEvents(params: { page?: number, size?: number, type?: string })` -> GET /api/users/me/events
    - Follow patterns from `companies.ts`
  - [ ] 3.4 Create `front/src/queries/account.ts` with query definitions
    - Define `ACCOUNT_QUERY_KEYS` with root, sessions, events patterns
    - Create `sessionsQuery` using `defineQueryOptions`
    - Create `activityEventsQuery` using `defineQueryOptions` with pagination params
    - Follow patterns from `companies.ts`
  - [ ] 3.5 Create `front/src/mutations/account.ts` with mutations
    - Create `useRevokeSession` mutation
      - Invalidate sessions query after success
      - Return revoke function
    - Create `useRevokeAllSessions` mutation
      - Accept `keepCurrent` parameter
      - Invalidate sessions query after success
      - Handle redirect to login if current session revoked
    - Follow patterns from `companies.ts`
  - [ ] 3.6 Ensure frontend data layer tests pass
    - Run ONLY the 4-6 tests written in 3.1
    - Verify API functions and hooks work correctly

**Acceptance Criteria:**
- TypeScript types match backend response structure
- API functions properly typed and call correct endpoints
- Queries and mutations follow existing patterns
- Cache invalidation works correctly

---

#### Task Group 4: Component Updates
**Dependencies:** Task Group 3

- [ ] 4.0 Complete UI component updates for security page
  - [ ] 4.1 Write 3-5 focused tests for component updates
    - Test SessionManagementSection renders loading state
    - Test SessionManagementSection displays session data
    - Test ActivityLogSection renders events correctly
    - Test security page without removed sections
  - [ ] 4.2 Update `SessionManagementSection.vue` to use real data
    - Accept sessions data as prop (fetched by parent page)
    - Accept loading and error states as props
    - Remove mock session data
    - Identify and style current session using `isCurrent` flag
    - Map session data to display format:
      - IP address from `ipAddress`
      - Start time from `startedAt`
      - Last access from `lastAccess`
      - Client names from `clients` array
    - Emit `revoke-session` and `sign-out-all-devices` events
    - Show loading skeleton while fetching
    - Show error alert if fetch fails
  - [ ] 4.3 Update `ActivityLogSection.vue` to use real data
    - Accept events data as prop (fetched by parent page)
    - Accept loading, error, and hasMore states as props
    - Remove mock activity data
    - Map Keycloak event types to display:
      - LOGIN -> success icon, "Successful login"
      - LOGIN_ERROR -> security icon, "Failed login attempt"
      - LOGOUT -> info icon, "Signed out"
      - UPDATE_PROFILE -> update icon, "Profile updated"
      - UPDATE_PASSWORD -> security icon, "Password changed"
    - Display IP address (not location - geolocation out of scope)
    - Add "Load More" button for pagination
    - Emit `load-more` event for pagination
    - Show loading skeleton while fetching
    - Show error alert if fetch fails
  - [ ] 4.4 Update `security.vue` page
    - Remove `TwoFactorSection` import and usage
    - Remove `AccountRecoverySection` import and usage
    - Keep component files in codebase (do not delete)
    - Import and use `useQuery` with `sessionsQuery` and `activityEventsQuery`
    - Import and use `useRevokeSession` and `useRevokeAllSessions` mutations
    - Handle mutation callbacks (revoke session, sign out all)
    - Pass real data to `SessionManagementSection` and `ActivityLogSection`
    - Handle loading and error states from queries
    - Implement pagination for activity events
    - Remove all mock data (otherSessions, recentActivity refs)
  - [ ] 4.5 Add/update i18n translations if needed
    - Check `settings.security.*` namespace has all needed keys
    - Add any missing translation keys for new states (loading, errors)
  - [ ] 4.6 Ensure component tests pass
    - Run ONLY the 3-5 tests written in 4.1
    - Verify components render correctly with real data patterns

**Acceptance Criteria:**
- SessionManagementSection shows real Keycloak sessions
- Current session is visually identified and cannot be revoked
- ActivityLogSection shows real Keycloak events with proper icons
- Pagination works for activity events
- TwoFactorSection and AccountRecoverySection removed from page
- Loading and error states display correctly

---

## Execution Order

Recommended implementation sequence:

1. **Backend - Keycloak Admin Service (Task Group 1)**
   - Extend keycloak_admin_service with session and event methods
   - No external dependencies, can start immediately

2. **Backend - Account API Endpoints (Task Group 2)**
   - Create /api/users/me/* endpoints
   - Depends on Task Group 1 for service methods

3. **Frontend - Data Layer (Task Group 3)**
   - Create API functions, queries, and mutations
   - Depends on Task Group 2 for working backend endpoints

4. **Frontend - Component Updates (Task Group 4)**
   - Update components to use real data
   - Depends on Task Group 3 for data layer

---

## Implementation Notes

### Current Session Identification
The current session can be identified by:
1. Session ID stored in JWT token (if available in Keycloak token)
2. Comparing session IP with current request IP (less reliable)
3. Using the session that matches the current auth store session

Check `useAuthStore()` and the Keycloak token payload to find session information.

### Keycloak Event Type Mapping
Map these Keycloak event types to user-friendly display:

| Keycloak Type | Display Category | Icon | Title |
|---------------|------------------|------|-------|
| LOGIN | login | fa-sign-in-alt | Successful login |
| LOGIN_ERROR | security | fa-exclamation-triangle | Failed login attempt |
| LOGOUT | login | fa-sign-out-alt | Signed out |
| UPDATE_PROFILE | update | fa-user-edit | Profile updated |
| UPDATE_PASSWORD | security | fa-key | Password changed |
| UPDATE_EMAIL | update | fa-envelope | Email updated |

### Error Handling
- 401: User not authenticated -> redirect to login
- 404: Session not found -> show error toast, refresh sessions list
- 500: Server error -> show error alert in component

### Testing Strategy
- Backend: Mock Keycloak Admin API responses
- Frontend API: Mock apiClient responses
- Components: Test with mock data passed as props
- Focus on happy path and key error scenarios
