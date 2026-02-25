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
    "app.core.keycloak._initialize_keycloak_with_retry", return_value=_mock_idp
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
    organization=["Test Org", {"Test Org": {"id": "test-org-123"}}],
    enabled_modules=["Screen", "Target"],
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
        "integration: marks tests as integration tests (require running services)",
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


# Test database fixtures

# Create testing session factory at module level for use in concurrency tests
from sqlalchemy.ext.asyncio import create_async_engine, async_sessionmaker, AsyncSession
from sqlalchemy.pool import StaticPool
from app.database import GlobalBase

# Create a shared test engine
_test_engine = create_async_engine(
    "sqlite+aiosqlite:///:memory:",
    connect_args={"check_same_thread": False},
    poolclass=StaticPool,
)

# Export this for use in concurrency tests
TestingSessionLocal = async_sessionmaker(
    _test_engine,
    class_=AsyncSession,
    expire_on_commit=False,
    autocommit=False,
    autoflush=False,
)


def _strip_schema_from_metadata(base):
    """Strip schema and adapt PostgreSQL types for SQLite compatibility."""
    from sqlalchemy import JSON, Enum as SAEnum
    from sqlalchemy.dialects.postgresql import ARRAY

    for table in base.metadata.tables.values():
        table.schema = None
        # Also update table_args if it has schema
        if hasattr(table, '__table_args__'):
            if isinstance(table.__table_args__, dict):
                table.__table_args__.pop('schema', None)

        # Adapt PostgreSQL-specific column types for SQLite
        for column in table.columns:
            # Replace ARRAY with JSON (SQLite has no array type)
            if isinstance(column.type, ARRAY):
                column.type = JSON()
                # Fix PostgreSQL-specific server_default for arrays
                if column.server_default is not None:
                    sd = str(column.server_default.arg)
                    if "::text[]" in sd or "::varchar[]" in sd:
                        column.server_default = None

            # Fix Enum types with schema (strip schema, allow creation)
            if isinstance(column.type, SAEnum):
                column.type.schema = None
                column.type.create_type = True


@pytest.fixture(scope="function")
async def setup_test_db():
    """Set up test database tables for tests.

    This fixture only sets up/tears down tables, doesn't provide a session.
    Use this for tests that need to create their own sessions.
    """
    # Strip schema before creating tables (SQLite doesn't support schemas)
    _strip_schema_from_metadata(GlobalBase)

    # Create all tables before test
    async with _test_engine.begin() as conn:
        await conn.run_sync(GlobalBase.metadata.create_all)

    yield

    # Drop all tables after test
    async with _test_engine.begin() as conn:
        await conn.run_sync(GlobalBase.metadata.drop_all)


@pytest.fixture(scope="function")
async def global_db_session(setup_test_db):
    """Create an async test database session for global_schema.

    Creates a fresh async database session for each test with all tables.
    Uses SQLite in-memory database for fast tests with async support.

    Note: SQLite doesn't support PostgreSQL-style schemas, so we strip
    the schema from table metadata before creating tables.
    """
    async with TestingSessionLocal() as session:
        try:
            yield session
        finally:
            await session.close()


@pytest.fixture
async def client(global_db_session):
    """Create an async test client with database override.

    Uses httpx.AsyncClient with ASGITransport so async fixtures
    (like global_db_session) work correctly with pytest-asyncio.
    """
    from httpx import AsyncClient, ASGITransport
    from app.main import app
    from app.database import get_global_db

    # Override database dependency with async fixture
    async def override_get_db():
        yield global_db_session

    app.dependency_overrides[get_global_db] = override_get_db

    transport = ASGITransport(app=app)
    async with AsyncClient(transport=transport, base_url="http://test") as ac:
        ac.app = app  # Expose app for dependency_overrides access in tests
        yield ac

    # Clean up overrides
    app.dependency_overrides.clear()
