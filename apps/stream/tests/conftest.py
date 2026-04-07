"""Pytest fixtures for Stream service tests.

Uses SQLite in-memory database with schema stripping for fast tests.
Adapted from global-service's conftest pattern.
"""

import uuid as _uuid

import pytest
import sqlalchemy as _sa
from sqlalchemy import create_engine
from sqlalchemy import event as _sa_event
from sqlalchemy.orm import sessionmaker
from sqlalchemy.pool import StaticPool

from app.database import Base


def _strip_schema_from_metadata(base):
    """Strip schema and adapt PostgreSQL types for SQLite compatibility."""
    from sqlalchemy import JSON
    from sqlalchemy import Enum as SAEnum
    from sqlalchemy.dialects.postgresql import JSONB

    for table in base.metadata.tables.values():
        table.schema = None

        # Adapt PostgreSQL-specific column types for SQLite
        for column in table.columns:
            # Replace JSONB with JSON (SQLite has no JSONB)
            if isinstance(column.type, JSONB):
                column.type = JSON()

            # Fix Enum types with schema
            if isinstance(column.type, SAEnum):
                column.type.schema = None
                column.type.create_type = True

            # Replace gen_random_uuid() with Python-side UUID
            if column.server_default is not None:
                try:
                    sd = str(column.server_default.arg)
                    if "gen_random_uuid()" in sd:
                        column.server_default = None
                        if column.default is None:
                            column.default = _sa.ColumnDefault(_uuid.uuid4)
                except (AttributeError, TypeError):
                    pass


# Import models to register them with Base.metadata
import app.models  # noqa: F401, E402

# Create shared test engine (sync, SQLite in-memory)
_test_engine = create_engine(
    "sqlite:///:memory:",
    connect_args={"check_same_thread": False},
    poolclass=StaticPool,
)


@_sa_event.listens_for(_test_engine, "connect")
def _register_sqlite_functions(dbapi_connection, connection_record):
    dbapi_connection.create_function("gen_random_uuid", 0, lambda: str(_uuid.uuid4()))


TestingSessionLocal = sessionmaker(
    bind=_test_engine,
    autocommit=False,
    autoflush=False,
)


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
    """Skip integration tests unless --run-integration is passed."""
    if config.getoption("--run-integration"):
        return

    skip_integration = pytest.mark.skip(
        reason="need --run-integration option to run integration tests"
    )
    for item in items:
        if "integration" in item.keywords:
            item.add_marker(skip_integration)


@pytest.fixture(scope="function")
def setup_test_db():
    """Set up test database tables."""
    _strip_schema_from_metadata(Base)

    Base.metadata.create_all(bind=_test_engine)
    yield
    Base.metadata.drop_all(bind=_test_engine)


@pytest.fixture(scope="function")
def db_session(setup_test_db):
    """Create a test database session.

    Creates a fresh sync session for each test with all tables.
    Uses SQLite in-memory database for fast tests.
    """
    session = TestingSessionLocal()
    try:
        yield session
    finally:
        session.close()


@pytest.fixture
def mock_user():
    """Return a mock user context for tests."""
    return {
        "sub": "test-user-123",
        "preferred_username": "testuser",
        "organization_id": "test-org-123",
    }
