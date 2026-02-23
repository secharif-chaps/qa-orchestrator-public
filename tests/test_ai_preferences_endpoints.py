"""Tests for AI preferences API endpoints.

Tests the GET/POST /ai-preferences endpoints:
- GET: 404 when no preferences, 200 when found
- POST: creates new, updates existing (upsert)
- Validation (required fields, max lengths)
- Response schema correctness
"""

import pytest
from datetime import datetime, timezone

from app.core.keycloak import OIDCUser
from app.core.organization import OrganizationContext
from app.models.user_preferences import UserPreferences
from app.services.user_preferences import AI_CATEGORY


TEST_ORG_ID = "12345678-1234-4234-a234-123456789abc"
TEST_USER_ID = "test-user-endpoint-123"
TEST_USERNAME = "testuser"


@pytest.fixture
def test_user():
    """Create a test user with organization context."""
    now = int(datetime.now(timezone.utc).timestamp())
    return OIDCUser(
        sub=TEST_USER_ID,
        preferred_username=TEST_USERNAME,
        email="test@example.com",
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
def authed_client(client, test_user, org_context):
    """Create a test client with authentication overrides."""
    from tests.conftest import _mock_idp
    from app.core.organization import get_user_organization

    def get_test_user():
        return test_user

    client.app.dependency_overrides[_mock_idp.get_current_user.return_value] = (
        get_test_user
    )
    client.app.dependency_overrides[get_user_organization] = lambda: org_context

    yield client


@pytest.fixture
async def existing_preferences(global_db_session):
    """Create existing preferences in the test DB."""
    user_prefs = UserPreferences(
        user_id=TEST_USER_ID,
        preferences={
            AI_CATEGORY: {
                "role": "Sales Manager",
                "goals_text": "Find prospects",
                "desired_output_text": "Summaries",
                "documentation_text": "CRM docs",
            }
        },
    )
    global_db_session.add(user_prefs)
    await global_db_session.commit()
    return user_prefs


VALID_PAYLOAD = {
    "role": "Marketing Director",
    "goals_text": "Track competitors",
    "desired_output_text": "Detailed reports",
    "documentation_text": "Brand guidelines",
}


# --- GET /ai-preferences ---


def test_get_preferences_not_found(authed_client):
    """GET returns 404 when user has no preferences."""
    response = authed_client.get("/api/ai-preferences")

    assert response.status_code == 404
    assert "not found" in response.json()["detail"].lower()


def test_get_preferences_success(authed_client, existing_preferences):
    """GET returns 200 with preferences when they exist."""
    response = authed_client.get("/api/ai-preferences")

    assert response.status_code == 200
    data = response.json()
    assert data["role"] == "Sales Manager"
    assert data["goals_text"] == "Find prospects"
    assert data["desired_output_text"] == "Summaries"
    assert data["documentation_text"] == "CRM docs"


def test_get_preferences_response_schema(authed_client, existing_preferences):
    """GET response contains exactly the expected fields."""
    response = authed_client.get("/api/ai-preferences")

    assert response.status_code == 200
    data = response.json()
    expected_fields = {"role", "goals_text", "desired_output_text", "documentation_text"}
    assert set(data.keys()) == expected_fields


# --- POST /ai-preferences ---


def test_create_preferences(authed_client):
    """POST creates new preferences when none exist."""
    response = authed_client.post("/api/ai-preferences", json=VALID_PAYLOAD)

    assert response.status_code == 200
    data = response.json()
    assert data["role"] == VALID_PAYLOAD["role"]
    assert data["goals_text"] == VALID_PAYLOAD["goals_text"]
    assert data["desired_output_text"] == VALID_PAYLOAD["desired_output_text"]
    assert data["documentation_text"] == VALID_PAYLOAD["documentation_text"]


def test_update_preferences(authed_client, existing_preferences):
    """POST updates existing preferences (upsert)."""
    response = authed_client.post("/api/ai-preferences", json=VALID_PAYLOAD)

    assert response.status_code == 200
    data = response.json()
    assert data["role"] == VALID_PAYLOAD["role"]

    # Verify GET returns updated data
    get_response = authed_client.get("/api/ai-preferences")
    assert get_response.status_code == 200
    assert get_response.json()["role"] == VALID_PAYLOAD["role"]


def test_create_then_get(authed_client):
    """POST then GET returns consistent data."""
    post_response = authed_client.post("/api/ai-preferences", json=VALID_PAYLOAD)
    assert post_response.status_code == 200

    get_response = authed_client.get("/api/ai-preferences")
    assert get_response.status_code == 200

    assert post_response.json() == get_response.json()


def test_create_without_optional_field(authed_client):
    """POST works with documentation_text omitted."""
    payload = {
        "role": "Analyst",
        "goals_text": "Research companies",
        "desired_output_text": "Brief summaries",
    }
    response = authed_client.post("/api/ai-preferences", json=payload)

    assert response.status_code == 200
    data = response.json()
    assert data["role"] == "Analyst"
    assert data["documentation_text"] is None


def test_create_with_null_optional_field(authed_client):
    """POST works with documentation_text explicitly null."""
    payload = {
        "role": "Analyst",
        "goals_text": "Research companies",
        "desired_output_text": "Brief summaries",
        "documentation_text": None,
    }
    response = authed_client.post("/api/ai-preferences", json=payload)

    assert response.status_code == 200
    assert response.json()["documentation_text"] is None


# --- Validation ---


def test_validation_missing_required_field(authed_client):
    """POST returns 422 when required field is missing."""
    payload = {
        "role": "Analyst",
        # missing goals_text and desired_output_text
    }
    response = authed_client.post("/api/ai-preferences", json=payload)
    assert response.status_code == 422


def test_validation_empty_role(authed_client):
    """POST returns 422 when role is empty string."""
    payload = {
        "role": "",
        "goals_text": "Some goals",
        "desired_output_text": "Some output",
    }
    response = authed_client.post("/api/ai-preferences", json=payload)
    assert response.status_code == 422


def test_validation_role_too_long(authed_client):
    """POST returns 422 when role exceeds max length."""
    payload = {
        "role": "x" * 256,
        "goals_text": "Some goals",
        "desired_output_text": "Some output",
    }
    response = authed_client.post("/api/ai-preferences", json=payload)
    assert response.status_code == 422


def test_validation_goals_too_long(authed_client):
    """POST returns 422 when goals_text exceeds max length."""
    payload = {
        "role": "Analyst",
        "goals_text": "x" * 2001,
        "desired_output_text": "Some output",
    }
    response = authed_client.post("/api/ai-preferences", json=payload)
    assert response.status_code == 422


def test_validation_documentation_too_long(authed_client):
    """POST returns 422 when documentation_text exceeds max length."""
    payload = {
        "role": "Analyst",
        "goals_text": "Some goals",
        "desired_output_text": "Some output",
        "documentation_text": "x" * 5001,
    }
    response = authed_client.post("/api/ai-preferences", json=payload)
    assert response.status_code == 422
