"""Tests for account session management API endpoints.

Tests the GET/DELETE /users/me/sessions endpoints:
- GET /users/me/sessions: list sessions, mark current
- DELETE /users/me/sessions/{id}: revoke single session, prevent self-revoke
- DELETE /users/me/sessions: revoke all, keep_current flag
"""

import pytest
from datetime import datetime, timezone
from unittest.mock import AsyncMock, MagicMock, patch

from fastapi import HTTPException, status

from app.core.keycloak import OIDCUser
from app.core.organization import OrganizationContext


TEST_ORG_ID = "12345678-1234-4234-a234-123456789abc"
TEST_USER_ID = "test-user-session-456"
TEST_USERNAME = "sessionuser"

SESSION_ID_AAA = "aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa"
SESSION_ID_BBB = "bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb"
SESSION_ID_CCC = "cccccccc-cccc-cccc-cccc-cccccccccccc"
SESSION_ID_NONEXISTENT = "99999999-9999-9999-9999-999999999999"

MOCK_SESSIONS = [
    {
        "id": SESSION_ID_AAA,
        "ipAddress": "192.168.1.10",
        "start": 1700000000000,
        "lastAccess": 1700003600000,
        "clients": {"chapsmind-frontend": "ChapsMind Frontend"},
    },
    {
        "id": SESSION_ID_BBB,
        "ipAddress": "10.0.0.5",
        "start": 1699990000000,
        "lastAccess": 1700002000000,
        "clients": {"chapsmind-frontend": "ChapsMind Frontend"},
    },
]


@pytest.fixture
def test_user():
    """Create a test user with organization context."""
    now = int(datetime.now(timezone.utc).timestamp())
    return OIDCUser(
        sub=TEST_USER_ID,
        preferred_username=TEST_USERNAME,
        email="session@example.com",
        email_verified=True,
        iat=now,
        exp=now + 3600,
        organization=["Test Org", {"Test Org": {"id": TEST_ORG_ID}}],
        enabled_modules=["screen"],
    )


@pytest.fixture
def org_context():
    """Create an organization context for dependency override."""
    return OrganizationContext(
        organization_id=TEST_ORG_ID,
        organization_name="Test Org",
        user_id=TEST_USER_ID,
        username=TEST_USERNAME,
        enabled_modules=["screen"],
    )


@pytest.fixture
def mock_kc_admin():
    """Create a mock KeycloakAdminService."""
    return MagicMock()


@pytest.fixture
async def authed_client(client, test_user, org_context, mock_kc_admin):
    """Create a test client with authentication and keycloak admin overrides."""
    from tests.conftest import _mock_idp
    from app.core.organization import get_user_organization
    from app.core.dependencies import get_keycloak_admin

    def get_test_user():
        return test_user

    client.app.dependency_overrides[_mock_idp.get_current_user.return_value] = (
        get_test_user
    )
    client.app.dependency_overrides[get_user_organization] = lambda: org_context
    client.app.dependency_overrides[get_keycloak_admin] = lambda: mock_kc_admin

    yield client


# --- GET /users/me/sessions ---


async def test_get_sessions_success(authed_client, mock_kc_admin):
    """GET returns 200 with session list."""
    mock_kc_admin.get_user_sessions = AsyncMock(return_value=MOCK_SESSIONS)

    response = await authed_client.get("/api/users/me/sessions")

    assert response.status_code == 200
    data = response.json()
    assert len(data["sessions"]) == 2
    assert data["sessions"][0]["id"] == SESSION_ID_AAA
    assert data["sessions"][0]["ip_address"] == "192.168.1.10"
    assert data["sessions"][0]["is_current"] is False
    assert data["sessions"][1]["id"] == SESSION_ID_BBB


async def test_get_sessions_empty(authed_client, mock_kc_admin):
    """GET returns 200 with empty list when no sessions."""
    mock_kc_admin.get_user_sessions = AsyncMock(return_value=[])

    response = await authed_client.get("/api/users/me/sessions")

    assert response.status_code == 200
    data = response.json()
    assert data["sessions"] == []
    assert data["current_session_id"] is None


@patch("app.api.endpoints.account._get_current_session_id", return_value=SESSION_ID_AAA)
async def test_get_sessions_marks_current(mock_sid, authed_client, mock_kc_admin):
    """GET marks the current session with is_current=True."""
    mock_kc_admin.get_user_sessions = AsyncMock(return_value=MOCK_SESSIONS)

    response = await authed_client.get("/api/users/me/sessions")

    assert response.status_code == 200
    data = response.json()
    assert data["current_session_id"] == SESSION_ID_AAA

    current = [s for s in data["sessions"] if s["is_current"]]
    assert len(current) == 1
    assert current[0]["id"] == SESSION_ID_AAA


async def test_get_sessions_keycloak_error(authed_client, mock_kc_admin):
    """GET returns 500 when Keycloak call raises."""
    mock_kc_admin.get_user_sessions = AsyncMock(side_effect=RuntimeError("connection refused"))

    response = await authed_client.get("/api/users/me/sessions")

    assert response.status_code == 500
    assert "Failed to fetch sessions" in response.json()["detail"]


# --- DELETE /users/me/sessions/{session_id} ---


async def test_revoke_session_success(authed_client, mock_kc_admin):
    """DELETE revokes a session and returns 204."""
    mock_kc_admin.get_user_sessions = AsyncMock(return_value=MOCK_SESSIONS)
    mock_kc_admin.revoke_session = AsyncMock(return_value=True)

    response = await authed_client.delete(f"/api/users/me/sessions/{SESSION_ID_BBB}")

    assert response.status_code == 204
    mock_kc_admin.revoke_session.assert_awaited_once_with(SESSION_ID_BBB)


@patch("app.api.endpoints.account._get_current_session_id", return_value=SESSION_ID_AAA)
async def test_revoke_current_session_rejected(mock_sid, authed_client, mock_kc_admin):
    """DELETE returns 400 when trying to revoke the current session."""
    response = await authed_client.delete(f"/api/users/me/sessions/{SESSION_ID_AAA}")

    assert response.status_code == 400
    assert "Cannot revoke current session" in response.json()["detail"]


async def test_revoke_session_not_found(authed_client, mock_kc_admin):
    """DELETE returns 404 when session doesn't belong to user."""
    mock_kc_admin.get_user_sessions = AsyncMock(return_value=MOCK_SESSIONS)

    response = await authed_client.delete(f"/api/users/me/sessions/{SESSION_ID_NONEXISTENT}")

    assert response.status_code == 404
    assert "Session not found" in response.json()["detail"]


async def test_revoke_session_invalid_uuid_format(authed_client, mock_kc_admin):
    """DELETE returns 422 when session_id is not a valid UUID."""
    response = await authed_client.delete("/api/users/me/sessions/not-a-valid-uuid")

    assert response.status_code == 422


async def test_revoke_session_keycloak_failure(authed_client, mock_kc_admin):
    """DELETE returns 502 when Keycloak returns unexpected status."""
    mock_kc_admin.get_user_sessions = AsyncMock(return_value=MOCK_SESSIONS)
    mock_kc_admin.revoke_session = AsyncMock(
        side_effect=HTTPException(
            status_code=status.HTTP_502_BAD_GATEWAY,
            detail="Unexpected response from identity provider",
        )
    )

    response = await authed_client.delete(f"/api/users/me/sessions/{SESSION_ID_BBB}")

    assert response.status_code == 502
    assert "identity provider" in response.json()["detail"]


# --- DELETE /users/me/sessions (revoke all) ---


@patch("app.api.endpoints.account._get_current_session_id", return_value=SESSION_ID_AAA)
async def test_revoke_all_keep_current(mock_sid, authed_client, mock_kc_admin):
    """DELETE with keep_current=True revokes all except current (204)."""
    mock_kc_admin.get_user_sessions = AsyncMock(return_value=MOCK_SESSIONS)
    mock_kc_admin.revoke_session = AsyncMock(return_value=True)

    response = await authed_client.delete("/api/users/me/sessions?keep_current=true")

    assert response.status_code == 204
    # Should only revoke session-bbb (not session-aaa which is current)
    mock_kc_admin.revoke_session.assert_awaited_once_with(SESSION_ID_BBB)


async def test_revoke_all_including_current(authed_client, mock_kc_admin):
    """DELETE with keep_current=false revokes all sessions (204)."""
    mock_kc_admin.revoke_all_user_sessions = AsyncMock(return_value=True)

    response = await authed_client.delete("/api/users/me/sessions?keep_current=false")

    assert response.status_code == 204
    mock_kc_admin.revoke_all_user_sessions.assert_awaited_once_with(TEST_USER_ID)


async def test_revoke_all_keycloak_failure(authed_client, mock_kc_admin):
    """DELETE returns 502 when Keycloak returns unexpected status."""
    mock_kc_admin.revoke_all_user_sessions = AsyncMock(
        side_effect=HTTPException(
            status_code=status.HTTP_502_BAD_GATEWAY,
            detail="Unexpected response from identity provider",
        )
    )

    response = await authed_client.delete("/api/users/me/sessions?keep_current=false")

    assert response.status_code == 502
    assert "identity provider" in response.json()["detail"]


@patch("app.api.endpoints.account._get_current_session_id", return_value=SESSION_ID_AAA)
async def test_revoke_all_keep_current_partial_failure(mock_sid, authed_client, mock_kc_admin):
    """DELETE with keep_current=True still returns 204 even if one revoke fails."""
    three_sessions = MOCK_SESSIONS + [
        {
            "id": SESSION_ID_CCC,
            "ipAddress": "172.16.0.1",
            "start": 1699980000000,
            "lastAccess": 1700001000000,
            "clients": {},
        },
    ]
    mock_kc_admin.get_user_sessions = AsyncMock(return_value=three_sessions)
    # First revoke succeeds, second raises
    mock_kc_admin.revoke_session = AsyncMock(
        side_effect=[True, RuntimeError("Keycloak error")]
    )

    response = await authed_client.delete("/api/users/me/sessions?keep_current=true")

    # Partial failure is not a 500 - we still return 204
    assert response.status_code == 204


# --- Response schema ---


async def test_session_response_schema(authed_client, mock_kc_admin):
    """GET response has expected fields."""
    mock_kc_admin.get_user_sessions = AsyncMock(return_value=MOCK_SESSIONS)

    response = await authed_client.get("/api/users/me/sessions")

    assert response.status_code == 200
    data = response.json()
    session = data["sessions"][0]
    expected_fields = {"id", "ip_address", "started_at", "last_access", "clients", "is_current"}
    assert set(session.keys()) == expected_fields
    assert set(data.keys()) == {"sessions", "current_session_id"}
