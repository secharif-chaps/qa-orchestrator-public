"""Pytest configuration for integration tests.

This conftest.py provides fixtures for model and service layer tests
that require PostgreSQL (ARRAY, UUID, materialized views, etc.).
"""

import os

import pytest
from sqlalchemy import create_engine, text
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

TEST_DATABASE_URL = os.environ.get("TEST_DATABASE_URL", "postgresql://postgres:postgres@db:5432/chapsmind_db_test")


@pytest.fixture(scope="function")
def db_session():
    """Create a fresh database session for each test.

    Uses PostgreSQL test database because the models use PostgreSQL-specific
    features (ARRAY, UUID, etc.) that are not supported in SQLite.
    """
    engine = create_engine(TEST_DATABASE_URL)

    # Create schema before tables (PostgreSQL-specific)
    with engine.connect() as conn:
        conn.execute(text("CREATE SCHEMA IF NOT EXISTS screen_schema"))
        conn.commit()

    # Ensure tables exist (idempotent — alembic may have already created them)
    Base.metadata.create_all(bind=engine)

    # Use a connection-level transaction that rolls back after each test
    # This avoids DROP/CREATE overhead and doesn't break other tests
    # that rely on alembic-created objects (materialized views, enums)
    connection = engine.connect()
    transaction = connection.begin()
    session = sessionmaker(bind=connection)()

    try:
        yield session
    finally:
        session.close()
        transaction.rollback()
        connection.close()
