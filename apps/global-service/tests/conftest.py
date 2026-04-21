"""
Pytest configuration and fixtures for global-service tests.

IMPORTANT: Patches are applied at MODULE LEVEL to prevent Keycloak initialization
during test collection phase (before fixtures run).

This follows the same pattern as backend tests - set up test environment
BEFORE any app modules are imported.
"""

import uuid as _uuid
from datetime import UTC, datetime
from unittest.mock import MagicMock, patch

import pytest
import sqlalchemy as _sa
from sqlalchemy import event as _sa_event

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
now = int(datetime.now(UTC).timestamp())
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
import os

from sqlalchemy.ext.asyncio import AsyncSession, async_sessionmaker, create_async_engine
from sqlalchemy.pool import StaticPool

from app.database import GLOBAL_SCHEMA, GlobalBase

# Only switch to Postgres when TEST_DATABASE_URL is explicitly set (CI
# integration job). Regular DATABASE_URL is ignored — unit tests must always
# use SQLite in-memory so they stay fast and isolated from the dev database.
_RAW_TEST_DB_URL = os.environ.get("TEST_DATABASE_URL")

if _RAW_TEST_DB_URL and "postgres" in _RAW_TEST_DB_URL:
    _TEST_DB_URL = _RAW_TEST_DB_URL.replace("postgresql://", "postgresql+asyncpg://", 1)
    _USE_POSTGRES = True
    # NullPool gives each operation a fresh asyncpg connection — avoids
    # "another operation is in progress" when sequential fixtures reuse a
    # pooled connection that still has pending protocol state.
    from sqlalchemy.pool import NullPool

    _test_engine = create_async_engine(_TEST_DB_URL, future=True, poolclass=NullPool)
else:
    _TEST_DB_URL = "sqlite+aiosqlite:///:memory:"
    _USE_POSTGRES = False
    _test_engine = create_async_engine(
        _TEST_DB_URL,
        connect_args={"check_same_thread": False},
        poolclass=StaticPool,
    )


# Register gen_random_uuid() as a custom SQLite function (still useful for raw SQL)
# and provide Python-side UUID generation for ORM operations. No-op on Postgres
# which already has gen_random_uuid() built in.
if not _USE_POSTGRES:

    @_sa_event.listens_for(_test_engine.sync_engine, "connect")
    def _register_sqlite_functions(dbapi_connection, connection_record):
        dbapi_connection.create_function("gen_random_uuid", 0, lambda: str(_uuid.uuid4()))


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
    from sqlalchemy import JSON
    from sqlalchemy import Enum as SAEnum
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

            # Replace gen_random_uuid() server_default with Python-side default
            # SQLite can't return server-generated UUIDs via RETURNING, so we
            # generate them in Python before INSERT instead
            if column.server_default is not None:
                try:
                    sd = str(column.server_default.arg)
                    if "gen_random_uuid()" in sd:
                        column.server_default = None
                        if column.default is None:
                            column.default = _sa.ColumnDefault(_uuid.uuid4)
                except (AttributeError, TypeError):
                    pass


@pytest.fixture(scope="function")
async def setup_test_db():
    """Set up test database tables for tests.

    This fixture only sets up/tears down tables, doesn't provide a session.
    Use this for tests that need to create their own sessions.
    """
    if _USE_POSTGRES:
        # The "global_schema" schema is expected to already exist (created by
        # CI's before_script via psycopg2, or by the developer running tests
        # locally). We just manage tables inside it for isolation between tests.
        async with _test_engine.begin() as conn:
            await conn.run_sync(GlobalBase.metadata.create_all)

        yield

        async with _test_engine.begin() as conn:
            await conn.run_sync(GlobalBase.metadata.drop_all)
    else:
        # SQLite: strip schema from metadata and adapt types
        _strip_schema_from_metadata(GlobalBase)

        async with _test_engine.begin() as conn:
            await conn.run_sync(GlobalBase.metadata.create_all)

        yield

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
    from httpx import ASGITransport, AsyncClient

    from app.database import get_global_db
    from app.main import app

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
