"""Unit tests for UserPreferencesService.

Tests the service layer for AI preferences CRUD operations:
- Getting preferences (found, not found, empty JSONB)
- Setting preferences (create, update, upsert)
- Checking preferences existence
- JSONB mutation detection (flag_modified)
"""

import pytest

from app.models.user_preferences import UserPreferences
from app.services.user_preferences import AI_CATEGORY, UserPreferencesService


TEST_USER_ID = "test-user-uuid-123"

TEST_AI_DATA = {
    "role": "Sales Manager",
    "goals_text": "Find new prospects in the tech industry",
    "desired_output_text": "Concise summaries with contact info",
    "documentation_text": "Internal CRM guidelines",
}

UPDATED_AI_DATA = {
    "role": "Marketing Director",
    "goals_text": "Track competitor activity and market trends",
    "desired_output_text": "Detailed reports with charts",
    "documentation_text": None,
}


@pytest.fixture
def service(global_db_session):
    """Create a UserPreferencesService with test DB session."""
    return UserPreferencesService(db=global_db_session)


@pytest.fixture
async def existing_preferences(global_db_session):
    """Create a user preferences record in the test DB."""
    user_prefs = UserPreferences(
        user_id=TEST_USER_ID,
        preferences={AI_CATEGORY: TEST_AI_DATA},
    )
    global_db_session.add(user_prefs)
    await global_db_session.commit()
    await global_db_session.refresh(user_prefs)
    return user_prefs


# --- get_ai_preferences ---


@pytest.mark.asyncio
async def test_get_ai_preferences_not_found(service):
    """Returns None when user has no preferences."""
    result = await service.get_ai_preferences("nonexistent-user")
    assert result is None


@pytest.mark.asyncio
async def test_get_ai_preferences_success(service, existing_preferences):
    """Returns AI preferences dict when found."""
    result = await service.get_ai_preferences(TEST_USER_ID)

    assert result is not None
    assert result["role"] == TEST_AI_DATA["role"]
    assert result["goals_text"] == TEST_AI_DATA["goals_text"]
    assert result["desired_output_text"] == TEST_AI_DATA["desired_output_text"]
    assert result["documentation_text"] == TEST_AI_DATA["documentation_text"]


@pytest.mark.asyncio
async def test_get_ai_preferences_empty_jsonb(global_db_session):
    """Returns None when preferences JSONB exists but has no 'ai' key."""
    user_prefs = UserPreferences(
        user_id="user-with-empty-prefs",
        preferences={},
    )
    global_db_session.add(user_prefs)
    await global_db_session.commit()

    service = UserPreferencesService(db=global_db_session)
    result = await service.get_ai_preferences("user-with-empty-prefs")
    assert result is None


@pytest.mark.asyncio
async def test_get_ai_preferences_other_category_only(global_db_session):
    """Returns None when preferences exist but only for other categories."""
    user_prefs = UserPreferences(
        user_id="user-with-other-prefs",
        preferences={"notifications": {"email": True}},
    )
    global_db_session.add(user_prefs)
    await global_db_session.commit()

    service = UserPreferencesService(db=global_db_session)
    result = await service.get_ai_preferences("user-with-other-prefs")
    assert result is None


# --- set_ai_preferences ---


@pytest.mark.asyncio
async def test_set_ai_preferences_create(service):
    """Creates new preferences record when none exists."""
    result = await service.set_ai_preferences(TEST_USER_ID, TEST_AI_DATA)

    assert result is not None
    assert result["role"] == TEST_AI_DATA["role"]
    assert result["goals_text"] == TEST_AI_DATA["goals_text"]

    # Verify persisted to DB
    stored = await service.get_ai_preferences(TEST_USER_ID)
    assert stored == result


@pytest.mark.asyncio
async def test_set_ai_preferences_update(service, existing_preferences):
    """Updates existing preferences when they already exist."""
    result = await service.set_ai_preferences(TEST_USER_ID, UPDATED_AI_DATA)

    assert result is not None
    assert result["role"] == UPDATED_AI_DATA["role"]
    assert result["goals_text"] == UPDATED_AI_DATA["goals_text"]
    assert result["desired_output_text"] == UPDATED_AI_DATA["desired_output_text"]

    # Verify persisted to DB
    stored = await service.get_ai_preferences(TEST_USER_ID)
    assert stored["role"] == UPDATED_AI_DATA["role"]


@pytest.mark.asyncio
async def test_set_ai_preferences_preserves_other_categories(global_db_session):
    """Updating AI preferences doesn't affect other categories."""
    # Create record with multiple categories
    user_prefs = UserPreferences(
        user_id="multi-category-user",
        preferences={
            "notifications": {"email": True, "push": False},
            AI_CATEGORY: TEST_AI_DATA,
        },
    )
    global_db_session.add(user_prefs)
    await global_db_session.commit()

    service = UserPreferencesService(db=global_db_session)
    await service.set_ai_preferences("multi-category-user", UPDATED_AI_DATA)

    # Verify notifications category is untouched
    prefs_record = await service._get_user_preferences("multi-category-user")
    assert prefs_record.preferences["notifications"] == {"email": True, "push": False}
    assert prefs_record.preferences[AI_CATEGORY]["role"] == UPDATED_AI_DATA["role"]


@pytest.mark.asyncio
async def test_set_ai_preferences_upsert_idempotent(service):
    """Setting the same preferences twice returns the same result."""
    first = await service.set_ai_preferences(TEST_USER_ID, TEST_AI_DATA)
    second = await service.set_ai_preferences(TEST_USER_ID, TEST_AI_DATA)

    assert first == second


# --- has_ai_preferences ---


@pytest.mark.asyncio
async def test_has_ai_preferences_true(service, existing_preferences):
    """Returns True when AI preferences exist."""
    result = await service.has_ai_preferences(TEST_USER_ID)
    assert result is True


@pytest.mark.asyncio
async def test_has_ai_preferences_false(service):
    """Returns False when no preferences exist."""
    result = await service.has_ai_preferences("nonexistent-user")
    assert result is False


# --- Model helper methods ---


def test_model_get_ai_preferences():
    """Model convenience method returns AI category."""
    prefs = UserPreferences(
        user_id="test",
        preferences={AI_CATEGORY: TEST_AI_DATA},
    )
    assert prefs.get_ai_preferences() == TEST_AI_DATA


def test_model_set_ai_preferences():
    """Model convenience method sets AI category."""
    prefs = UserPreferences(user_id="test", preferences={})
    prefs.set_ai_preferences(TEST_AI_DATA)
    assert prefs.preferences[AI_CATEGORY] == TEST_AI_DATA


def test_model_get_preference_none():
    """Model returns None for missing category."""
    prefs = UserPreferences(user_id="test", preferences={})
    assert prefs.get_preference("nonexistent") is None


def test_model_get_preference_empty_preferences():
    """Model returns None when preferences is empty/falsy."""
    prefs = UserPreferences(user_id="test", preferences=None)
    assert prefs.get_preference("ai") is None


def test_model_set_preference_initializes():
    """Model initializes preferences dict if None."""
    prefs = UserPreferences(user_id="test", preferences=None)
    prefs.set_preference("ai", TEST_AI_DATA)
    assert prefs.preferences["ai"] == TEST_AI_DATA


def test_model_repr():
    """Model repr includes user_id."""
    prefs = UserPreferences(user_id="abc-123", preferences={})
    assert "abc-123" in repr(prefs)
