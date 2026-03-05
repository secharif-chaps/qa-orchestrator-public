"""Pytest configuration for unit tests.

This conftest.py provides fixtures for model and service layer tests
without importing the FastAPI app, which would trigger Keycloak connection.
"""

import os
import pytest
from sqlalchemy import create_engine, event
from sqlalchemy.orm import sessionmaker

# Skip Keycloak initialization in tests - set BEFORE any app imports
os.environ.setdefault("SKIP_KEYCLOAK_INIT", "true")

# Set environment variables before importing app modules
os.environ["KEYCLOAK_SERVER_URL"] = "http://localhost:8080"
os.environ["KEYCLOAK_REALM"] = "test"
os.environ["KEYCLOAK_CLIENT_ID"] = "test"
os.environ["KEYCLOAK_CLIENT_SECRET"] = "test"
os.environ["KEYCLOAK_ADMIN_CLIENT_ID"] = "test-admin"
os.environ["KEYCLOAK_ADMIN_CLIENT_SECRET"] = "test-admin-secret"

from app.database import Base


# Use PostgreSQL test database - the 'db' service in Docker network
# This is necessary because the models use PostgreSQL-specific features like ARRAY
TEST_DATABASE_URL = os.environ.get(
    "TEST_DATABASE_URL",
    "postgresql://postgres:postgres@db:5432/mint_db_test"
)


@pytest.fixture(scope="function")
def db_session():
    """Create a fresh database session for each test.

    Uses PostgreSQL test database because the models use PostgreSQL-specific
    features (ARRAY, UUID, etc.) that are not supported in SQLite.
    """
    engine = create_engine(TEST_DATABASE_URL)

    # Create all tables fresh for each test
    Base.metadata.create_all(bind=engine)

    TestingSessionLocal = sessionmaker(autocommit=False, autoflush=False, bind=engine)
    session = TestingSessionLocal()

    try:
        yield session
    finally:
        session.close()
        # Drop all tables after each test for isolation
        Base.metadata.drop_all(bind=engine)
