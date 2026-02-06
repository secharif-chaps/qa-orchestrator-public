"""
Pytest configuration and fixtures for global-service tests.
"""

import pytest
from datetime import datetime, timezone
from unittest.mock import MagicMock, patch

# Import at module level to ensure proper mocking before any other imports
from app.core.keycloak import OIDCUser


@pytest.fixture(autouse=True)
def mock_keycloak_initialization():
    """
    Mock Keycloak initialization to prevent connection attempts during tests.

    This fixture does two things:
    1. Mocks the Keycloak initialization functions to prevent connection attempts
    2. Overrides the FastAPI dependency to return a mock OIDCUser

    This allows tests to import modules that use Keycloak dependencies without
    triggering actual Keycloak connections.
    """
    # Create a mock OIDCUser that tests can use
    # Organization format matches Keycloak: [string_name, {name: {id: uuid}}]
    now = int(datetime.now(timezone.utc).timestamp())
    mock_user = OIDCUser(
        sub="test-user-123",
        preferred_username="testuser",
        email="test@example.com",
        email_verified=True,
        iat=now,
        exp=now + 3600,
        organization=[
            "Test Org",
            {"Test Org": {"id": "test-org-123"}}
        ],
        enabled_modules=["Screen", "Target"]
    )

    # Create a mock idp instance
    mock_idp = MagicMock()
    # Make get_current_user() return a callable that returns the mock user
    mock_dependency = MagicMock(return_value=mock_user)
    mock_idp.get_current_user.return_value = mock_dependency

    # Patch the Keycloak initialization functions
    with patch("app.core.keycloak.get_idp", return_value=mock_idp):
        with patch("app.core.keycloak._initialize_keycloak_with_retry", return_value=mock_idp):
            # Also patch the idp instance itself so imports work
            with patch("app.core.keycloak.idp", mock_idp):
                yield mock_idp


def pytest_addoption(parser):
    """Add custom command line options."""
    parser.addoption(
        "--run-integration",
        action="store_true",
        default=False,
        help="Run integration tests (require running services)",
    )


def pytest_configure(config):
    """Configure pytest with custom markers."""
    config.addinivalue_line(
        "markers",
        "integration: marks tests as integration tests (require running services)",
    )


def pytest_collection_modifyitems(config, items):
    """Modify test collection based on command line options."""
    if config.getoption("--run-integration"):
        # --run-integration given: don't skip integration tests
        return

    skip_integration = pytest.mark.skip(
        reason="need --run-integration option to run integration tests"
    )
    for item in items:
        if "integration" in item.keywords:
            item.add_marker(skip_integration)
