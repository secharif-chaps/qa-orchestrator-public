"""Tests for account activity events API endpoint.

Tests the GET /users/me/events endpoint:
- Pagination (page, size, has_more)
- Event type filtering (login, security, profile, all)
- Keycloak event transformation to display format
- Error handling
"""

import pytest
from datetime import datetime, timezone
from unittest.mock import AsyncMock, MagicMock

from fastapi import HTTPException, status

from app.core.keycloak import OIDCUser
from app.core.organization import OrganizationContext


TEST_ORG_ID = "12345678-1234-4234-a234-123456789abc"
TEST_USER_ID = "test-user-events-789"
TEST_USERNAME = "eventsuser"

MOCK_EVENTS = [
    {
        "time": 1700003600000,
        "type": "LOGIN",
        "ipAddress": "192.168.1.10",
        "details": {"auth_method": "openid-connect"},
    },
    {
        "time": 1700000000000,
        "type": "LOGOUT",
        "ipAddress": "192.168.1.10",
        "details": {},
    },
    {
        "time": 1699996400000,
        "type": "UPDATE_PASSWORD",
        "ipAddress": "10.0.0.5",
        "details": {},
    },
]


@pytest.fixture
def test_user():
    now = int(datetime.now(timezone.utc).timestamp())
    return OIDCUser(
        sub=TEST_USER_ID,
        preferred_username=TEST_USERNAME,
        email="events@example.com",
        email_verified=True,
        iat=now,
        exp=now + 3600,
        organization=["Test Org", {"Test Org": {"id": TEST_ORG_ID}}],
        enabled_modules=["screen"],
    )


@pytest.fixture
def org_context():
    return OrganizationContext(
        organization_id=TEST_ORG_ID,
        organization_name="Test Org",
        user_id=TEST_USER_ID,
        username=TEST_USERNAME,
        enabled_modules=["screen"],
    )


@pytest.fixture
def mock_kc_admin():
    return MagicMock()


@pytest.fixture
async def authed_client(client, test_user, org_context, mock_kc_admin):
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


# --- GET /users/me/events ---


async def test_get_events_success(authed_client, mock_kc_admin):
    """GET returns 200 with transformed events."""
    mock_kc_admin.get_user_events = AsyncMock(return_value=MOCK_EVENTS)

    response = await authed_client.get("/api/users/me/events")

    assert response.status_code == 200
    data = response.json()
    assert len(data["events"]) == 3
    assert data["page"] == 1
    assert data["size"] == 20
    assert data["has_more"] is False

    # Verify first event (LOGIN) was transformed correctly
    login_event = data["events"][0]
    assert login_event["type"] == "LOGIN"
    assert login_event["display_type"] == "login"
    assert login_event["icon"] == "fas fa-sign-in-alt"
    assert login_event["title"] == "Successful login"
    assert "openid-connect" in login_event["description"]
    assert login_event["ip_address"] == "192.168.1.10"
    assert login_event["id"] == "1700003600000"

    # Verify UPDATE_PASSWORD event
    password_event = data["events"][2]
    assert password_event["type"] == "UPDATE_PASSWORD"
    assert password_event["display_type"] == "security"
    assert password_event["icon"] == "fas fa-key"


async def test_get_events_empty(authed_client, mock_kc_admin):
    """GET returns 200 with empty list when no events."""
    mock_kc_admin.get_user_events = AsyncMock(return_value=[])

    response = await authed_client.get("/api/users/me/events")

    assert response.status_code == 200
    data = response.json()
    assert data["events"] == []
    assert data["has_more"] is False


async def test_get_events_pagination_has_more(authed_client, mock_kc_admin):
    """GET detects has_more when Keycloak returns more events than page size."""
    # Request size=2, but Keycloak returns 3 (size + 1) → has_more=True
    mock_kc_admin.get_user_events = AsyncMock(return_value=MOCK_EVENTS)

    response = await authed_client.get("/api/users/me/events?page=1&size=2")

    assert response.status_code == 200
    data = response.json()
    assert len(data["events"]) == 2
    assert data["has_more"] is True
    assert data["size"] == 2

    # Verify Keycloak was called with size + 1
    mock_kc_admin.get_user_events.assert_awaited_once_with(
        user_id=TEST_USER_ID,
        first=0,
        max_results=3,  # size + 1
        event_types=None,
    )


async def test_get_events_pagination_no_more(authed_client, mock_kc_admin):
    """GET returns has_more=False when fewer events than page size."""
    mock_kc_admin.get_user_events = AsyncMock(return_value=MOCK_EVENTS[:2])

    response = await authed_client.get("/api/users/me/events?page=1&size=5")

    assert response.status_code == 200
    data = response.json()
    assert len(data["events"]) == 2
    assert data["has_more"] is False


async def test_get_events_pagination_offset(authed_client, mock_kc_admin):
    """GET calculates correct offset for page > 1."""
    mock_kc_admin.get_user_events = AsyncMock(return_value=[])

    await authed_client.get("/api/users/me/events?page=3&size=10")

    mock_kc_admin.get_user_events.assert_awaited_once_with(
        user_id=TEST_USER_ID,
        first=20,  # (3 - 1) * 10
        max_results=11,  # size + 1
        event_types=None,
    )


async def test_get_events_filter_login(authed_client, mock_kc_admin):
    """GET with event_type=login passes correct Keycloak types."""
    mock_kc_admin.get_user_events = AsyncMock(return_value=[])

    await authed_client.get("/api/users/me/events?event_type=login")

    mock_kc_admin.get_user_events.assert_awaited_once_with(
        user_id=TEST_USER_ID,
        first=0,
        max_results=21,
        event_types=["LOGIN", "LOGOUT", "LOGIN_ERROR", "CODE_TO_TOKEN"],
    )


async def test_get_events_filter_security(authed_client, mock_kc_admin):
    """GET with event_type=security passes correct Keycloak types."""
    mock_kc_admin.get_user_events = AsyncMock(return_value=[])

    await authed_client.get("/api/users/me/events?event_type=security")

    mock_kc_admin.get_user_events.assert_awaited_once_with(
        user_id=TEST_USER_ID,
        first=0,
        max_results=21,
        event_types=["LOGIN_ERROR", "UPDATE_PASSWORD", "UPDATE_TOTP", "REMOVE_TOTP"],
    )


async def test_get_events_filter_profile(authed_client, mock_kc_admin):
    """GET with event_type=profile passes correct Keycloak types."""
    mock_kc_admin.get_user_events = AsyncMock(return_value=[])

    await authed_client.get("/api/users/me/events?event_type=profile")

    mock_kc_admin.get_user_events.assert_awaited_once_with(
        user_id=TEST_USER_ID,
        first=0,
        max_results=21,
        event_types=["UPDATE_PROFILE", "UPDATE_EMAIL"],
    )


async def test_get_events_filter_all(authed_client, mock_kc_admin):
    """GET with event_type=all passes no filter (all events)."""
    mock_kc_admin.get_user_events = AsyncMock(return_value=[])

    await authed_client.get("/api/users/me/events?event_type=all")

    mock_kc_admin.get_user_events.assert_awaited_once_with(
        user_id=TEST_USER_ID,
        first=0,
        max_results=21,
        event_types=None,
    )


async def test_get_events_keycloak_http_error(authed_client, mock_kc_admin):
    """GET returns 502 when Keycloak returns unexpected status."""
    mock_kc_admin.get_user_events = AsyncMock(
        side_effect=HTTPException(
            status_code=status.HTTP_502_BAD_GATEWAY,
            detail="Unexpected response from identity provider",
        )
    )

    response = await authed_client.get("/api/users/me/events")

    assert response.status_code == 502
    assert "identity provider" in response.json()["detail"]


async def test_get_events_keycloak_unavailable(authed_client, mock_kc_admin):
    """GET returns 500 when Keycloak is completely unreachable."""
    mock_kc_admin.get_user_events = AsyncMock(
        side_effect=RuntimeError("connection refused")
    )

    response = await authed_client.get("/api/users/me/events")

    assert response.status_code == 500
    assert "Failed to fetch activity events" in response.json()["detail"]


async def test_get_events_display_info_known_types(authed_client, mock_kc_admin):
    """All 10 known event types produce correct display info."""
    known_events = [
        {"time": 1700000000000 + i * 1000, "type": t, "ipAddress": "1.2.3.4", "details": {}}
        for i, t in enumerate([
            "LOGIN", "LOGIN_ERROR", "LOGOUT", "UPDATE_PROFILE",
            "UPDATE_PASSWORD", "UPDATE_EMAIL", "UPDATE_TOTP",
            "REMOVE_TOTP", "REFRESH_TOKEN", "CODE_TO_TOKEN",
        ])
    ]
    mock_kc_admin.get_user_events = AsyncMock(return_value=known_events)

    response = await authed_client.get("/api/users/me/events?size=100")

    assert response.status_code == 200
    events = response.json()["events"]
    assert len(events) == 10

    # Every known type should have a proper icon (not the fallback)
    for event in events:
        assert event["icon"].startswith("fas fa-")
        assert event["display_type"] in ("login", "security", "update")
        assert event["title"] != ""
        assert "Event:" not in event["description"]  # Not the fallback


async def test_get_events_unknown_event_type(authed_client, mock_kc_admin):
    """Unknown event types get a generic fallback display."""
    mock_kc_admin.get_user_events = AsyncMock(return_value=[
        {"time": 1700000000000, "type": "CUSTOM_ACTION", "ipAddress": "1.2.3.4", "details": {}},
    ])

    response = await authed_client.get("/api/users/me/events")

    assert response.status_code == 200
    event = response.json()["events"][0]
    assert event["type"] == "CUSTOM_ACTION"
    assert event["display_type"] == "update"
    assert event["icon"] == "fas fa-info-circle"
    assert event["title"] == "Custom Action"
    assert "Event: CUSTOM_ACTION" in event["description"]


# --- Response schema ---


async def test_events_response_schema(authed_client, mock_kc_admin):
    """GET response has expected fields."""
    mock_kc_admin.get_user_events = AsyncMock(return_value=MOCK_EVENTS)

    response = await authed_client.get("/api/users/me/events")

    assert response.status_code == 200
    data = response.json()
    assert set(data.keys()) == {"events", "page", "size", "has_more"}

    event = data["events"][0]
    expected_fields = {
        "id", "type", "display_type", "icon", "title",
        "description", "ip_address", "timestamp",
    }
    assert set(event.keys()) == expected_fields
