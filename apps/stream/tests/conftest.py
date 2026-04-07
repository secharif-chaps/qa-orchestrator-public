"""Pytest fixtures for Stream service tests.

Uses SQLite in-memory database with schema stripping for fast tests.
Adapted from global-service's conftest pattern.
"""

import uuid as _uuid
from datetime import UTC, datetime, timedelta
from unittest.mock import patch

import jwt as pyjwt
import pytest
import sqlalchemy as _sa
from fastapi.testclient import TestClient
from sqlalchemy import create_engine
from sqlalchemy import event as _sa_event
from sqlalchemy.orm import sessionmaker
from sqlalchemy.pool import StaticPool

from app.database import Base, get_db

# Auth constants matching app/core/auth.py
_JWT_SECRET = "test-jwt-secret"
_JWT_ALGORITHM = "HS256"
_JWT_ISSUER = "global-gateway"


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


# --- Service fixtures ---


@pytest.fixture
def stream_service(db_session):
    """Create a StreamService instance with test DB session."""
    from app.services.stream_service import StreamService

    return StreamService(db_session)


@pytest.fixture
def event_service(db_session):
    """Create an EventService instance with test DB session."""
    from app.services.event_service import EventService

    return EventService(db_session)


# --- Auth fixtures ---


def _make_internal_token(
    user_id: str = "test-user-123",
    username: str = "testuser",
    org_id: str = "test-org-123",
    org_name: str = "Test Org",
    roles: list[str] | None = None,
    expired: bool = False,
) -> str:
    """Create a signed Internal JWT for testing."""
    now = datetime.now(UTC)
    exp = now - timedelta(minutes=5) if expired else now + timedelta(minutes=5)
    payload = {
        "sub": user_id,
        "username": username,
        "org_id": org_id,
        "org_name": org_name,
        "roles": roles or ["stream.read", "stream.write"],
        "iss": _JWT_ISSUER,
        "iat": int(now.timestamp()),
        "exp": int(exp.timestamp()),
    }
    return pyjwt.encode(payload, _JWT_SECRET, algorithm=_JWT_ALGORITHM)


@pytest.fixture
def internal_token():
    """Return a valid Internal JWT string."""
    return _make_internal_token()


@pytest.fixture
def internal_auth_header(internal_token):
    """Return Authorization header dict with valid Internal JWT."""
    return {"Authorization": f"Internal {internal_token}"}


@pytest.fixture
def read_only_auth_header():
    """Return Authorization header with stream.read only."""
    token = _make_internal_token(roles=["stream.read"])
    return {"Authorization": f"Internal {token}"}


@pytest.fixture
def no_roles_auth_header():
    """Return Authorization header with no stream roles."""
    token = _make_internal_token(roles=["other.role"])
    return {"Authorization": f"Internal {token}"}


@pytest.fixture
def test_client(db_session):
    """Create a FastAPI TestClient with test DB session and JWT secret configured."""
    from app.main import app

    def _override_get_db():
        try:
            yield db_session
        finally:
            pass

    app.dependency_overrides[get_db] = _override_get_db

    with patch("app.core.auth.settings") as mock_settings:
        mock_settings.INTERNAL_JWT_SECRET = _JWT_SECRET
        with TestClient(app) as client:
            yield client

    app.dependency_overrides.clear()
