"""Pytest configuration and fixtures for integration tests.

This conftest provides fixtures for tests that need the full FastAPI app.
For unit tests that don't need the app, use tests/unit/conftest.py instead.
"""

import os

import pytest
from sqlalchemy import create_engine
from sqlalchemy.orm import sessionmaker

# Skip Keycloak initialization in CI where Keycloak is not available
# Set this BEFORE any app imports to prevent connection attempts
os.environ.setdefault("SKIP_KEYCLOAK_INIT", "true")

# Override Keycloak settings for local testing
# These must be set before any app imports
os.environ["KEYCLOAK_SERVER_URL"] = os.environ.get("KEYCLOAK_SERVER_URL", "http://keycloak:8080")
os.environ["KEYCLOAK_REALM"] = os.environ.get("KEYCLOAK_REALM", "chapsmind")
os.environ["KEYCLOAK_CLIENT_ID"] = os.environ.get("KEYCLOAK_CLIENT_ID", "chapsmind-screen-back")
os.environ["KEYCLOAK_CLIENT_SECRET"] = os.environ.get("KEYCLOAK_CLIENT_SECRET", "chapsmind-screen-back-secret")
os.environ["KEYCLOAK_ADMIN_CLIENT_ID"] = os.environ.get("KEYCLOAK_ADMIN_CLIENT_ID", "chapsmind-admin")
os.environ["KEYCLOAK_ADMIN_CLIENT_SECRET"] = os.environ.get("KEYCLOAK_ADMIN_CLIENT_SECRET", "chapsmind-admin-secret")
os.environ["ENCRYPTION_KEY"] = "WIxh6MTz5Zx3tRvLWBFJuzm4VFMe9kxecYjFZF23FRM="

from app.database import Base
from app.schemas.user import TokenData

# Test database URL - use in-memory SQLite for fast tests
TEST_DATABASE_URL = "sqlite:///:memory:"


@pytest.fixture(scope="function")
def db_session():
    """Create a fresh database session for each test."""
    engine = create_engine(TEST_DATABASE_URL, connect_args={"check_same_thread": False})
    Base.metadata.create_all(bind=engine)

    TestingSessionLocal = sessionmaker(autocommit=False, autoflush=False, bind=engine)
    session = TestingSessionLocal()

    try:
        yield session
    finally:
        session.close()
        Base.metadata.drop_all(bind=engine)


@pytest.fixture
def client():
    """Create a FastAPI test client.

    This fixture imports the app lazily to avoid Keycloak connection
    issues when running unit tests that don't need the client.
    """
    from fastapi.testclient import TestClient

    from app.main import app

    return TestClient(app)


@pytest.fixture
def admin_user() -> TokenData:
    """Create admin user token data."""
    return TokenData(username="admin", sub="admin-uuid-1234", roles=["admin", "admin.organizations"])


@pytest.fixture
def regular_user() -> TokenData:
    """Create regular user token data."""
    return TokenData(username="user", sub="user-uuid-5678", roles=["company.view"])


@pytest.fixture
def no_permission_user() -> TokenData:
    """Create user with no permissions."""
    return TokenData(username="noperm", sub="noperm-uuid-9999", roles=[])


@pytest.fixture
def organization_admin_user() -> TokenData:
    """Create organization admin user."""
    return TokenData(
        username="organization_admin", sub="orgadmin-uuid-4321", roles=["admin.organizations", "organization.write"]
    )
