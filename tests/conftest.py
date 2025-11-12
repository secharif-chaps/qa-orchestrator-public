"""Pytest configuration and fixtures for security tests."""

import os
import pytest
from sqlalchemy import create_engine
from sqlalchemy.orm import sessionmaker
from fastapi.testclient import TestClient

# Override Keycloak settings for local testing
os.environ["KEYCLOAK_SERVER_URL"] = "http://localhost:8080"

from app.main import app
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
    """Create a FastAPI test client."""
    return TestClient(app)


@pytest.fixture
def admin_user() -> TokenData:
    """Create admin user token data."""
    return TokenData(
        username="admin",
        sub="admin-uuid-1234",
        roles=["admin", "admin.workspaces"],
        workspace_id=1,
        workspace_slug="test-workspace"
    )


@pytest.fixture
def regular_user() -> TokenData:
    """Create regular user token data."""
    return TokenData(
        username="user",
        sub="user-uuid-5678",
        roles=["company.view"],
        workspace_id=1,
        workspace_slug="test-workspace"
    )


@pytest.fixture
def no_permission_user() -> TokenData:
    """Create user with no permissions."""
    return TokenData(
        username="noperm",
        sub="noperm-uuid-9999",
        roles=[],
        workspace_id=1,
        workspace_slug="test-workspace"
    )


@pytest.fixture
def workspace_admin_user() -> TokenData:
    """Create workspace admin user."""
    return TokenData(
        username="workspace_admin",
        sub="wsadmin-uuid-4321",
        roles=["admin.workspaces", "workspace.write"],
        workspace_id=1,
        workspace_slug="test-workspace"
    )
