"""
Pytest configuration and fixtures for global-service tests.

IMPORTANT: Patches are applied at MODULE LEVEL to prevent Keycloak initialization
during test collection phase (before fixtures run).

This follows the same pattern as backend tests - set up test environment
BEFORE any app modules are imported.
"""

import pytest
from datetime import datetime, timezone
from unittest.mock import MagicMock, patch
from app.core.keycloak import OIDCUser

# Apply patches at module level BEFORE any app modules are imported
# This prevents Keycloak initialization during pytest collection phase
_mock_idp = MagicMock()

# Patch Keycloak at the module level
_patch_get_idp = patch("app.core.keycloak.get_idp", return_value=_mock_idp)
_patch_init = patch(
    "app.core.keycloak._initialize_keycloak_with_retry",
    return_value=_mock_idp
)
_patch_idp = patch("app.core.keycloak.idp", _mock_idp)

_patch_get_idp.start()
_patch_init.start()
_patch_idp.start()


# Create a REAL OIDCUser instance for tests (not MagicMock)
# This follows the backend pattern - use real classes, just in test config
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

# Make get_current_user() return a callable that returns the real OIDCUser
mock_dependency = MagicMock(return_value=mock_user)
_mock_idp.get_current_user.return_value = mock_dependency


@pytest.fixture(autouse=True)
def mock_keycloak_initialization():
    """
    Fixture that ensures Keycloak mocks are active during test execution.

    The actual patches are applied at module level to prevent Keycloak
    initialization during test collection. This fixture just provides
    a reference to the mock for tests that need it.
    """
    yield _mock_idp


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
        "integration: marks tests as integration tests (require running "
        "services)",
    )


def pytest_unconfigure(config):
    """Clean up patches when pytest exits."""
    _patch_get_idp.stop()
    _patch_init.stop()
    _patch_idp.stop()


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
