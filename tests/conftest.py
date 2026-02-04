"""
Pytest configuration and fixtures for global-service tests.
"""

import pytest
from unittest.mock import MagicMock, patch


@pytest.fixture(autouse=True)
def mock_keycloak_initialization():
    """
    Mock Keycloak initialization to prevent connection attempts during tests.

    This is needed because FastAPI app startup calls get_idp() which tries to
    connect to Keycloak. In CI, Keycloak is not available.
    """
    mock_idp = MagicMock()
    mock_idp.get_current_user.return_value = MagicMock(
        sub="test-user",
        preferred_username="testuser",
        email="test@example.com",
        organization=None,
        enabled_modules=[],
    )

    with patch("app.core.keycloak.get_idp", return_value=mock_idp):
        with patch("app.core.keycloak._initialize_keycloak_with_retry", return_value=mock_idp):
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
