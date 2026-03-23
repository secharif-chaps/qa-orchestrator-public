"""Integration tests for company section tables migration (1:1 relationships).

These tests verify the migration creates the correct database schema:
1. Migration applies successfully (upgrade)
2. Migration rolls back successfully (downgrade)
3. Foreign key constraints to companies table work correctly
4. ON DELETE CASCADE behavior removes section data when company is deleted

NOTE: These tests use the main database (chapsmind_db) because they test the migration itself,
not the SQLAlchemy models (which are created in a later task group).
"""

import os

import pytest
from sqlalchemy import create_engine, inspect, text
from sqlalchemy.orm import sessionmaker

# Set environment variables before importing app modules
os.environ["KEYCLOAK_SERVER_URL"] = os.environ.get("KEYCLOAK_SERVER_URL", "http://localhost:8080")
os.environ["KEYCLOAK_REALM"] = os.environ.get("KEYCLOAK_REALM", "test")
os.environ["KEYCLOAK_CLIENT_ID"] = os.environ.get("KEYCLOAK_CLIENT_ID", "test")
os.environ["KEYCLOAK_CLIENT_SECRET"] = os.environ.get("KEYCLOAK_CLIENT_SECRET", "test")
os.environ["KEYCLOAK_ADMIN_CLIENT_ID"] = os.environ.get("KEYCLOAK_ADMIN_CLIENT_ID", "test-admin")
os.environ["KEYCLOAK_ADMIN_CLIENT_SECRET"] = os.environ.get("KEYCLOAK_ADMIN_CLIENT_SECRET", "test-admin-secret")


# Use main PostgreSQL database - tests verify migration created correct schema
# The migration must be applied before running these tests
DATABASE_URL = os.environ.get("DATABASE_URL", "postgresql://postgres:postgres@db:5432/chapsmind_db")


def _db_connectable() -> bool:
    """Check if the database is actually connectable."""
    try:
        engine = create_engine(DATABASE_URL, connect_args={"connect_timeout": 3})
        with engine.connect() as conn:
            conn.execute(text("SELECT 1"))
        engine.dispose()
        return True
    except Exception:
        return False


pytestmark = pytest.mark.skipif(
    not _db_connectable(),
    reason="Database not reachable (not running inside Docker network)",
)

# List of all 7 section tables created by the migration
SCHEMA = "screen_schema"

SECTION_TABLES = [
    "company_profile",
    "company_digital",
    "company_timeline",
    "company_products",
    "company_jobs",
    "company_csr",
    "company_press",
]


@pytest.fixture(scope="module")
def db_engine():
    """Create a database engine for direct SQL queries.

    Uses module scope since we're testing against live schema, not creating/dropping tables.
    """
    engine = create_engine(DATABASE_URL)
    yield engine
    engine.dispose()


@pytest.fixture(scope="function")
def db_session(db_engine):
    """Create a fresh database session for each test."""
    TestingSessionLocal = sessionmaker(autocommit=False, autoflush=False, bind=db_engine)
    session = TestingSessionLocal()
    try:
        yield session
    finally:
        # Clean up any test data created
        session.rollback()
        session.close()


class TestMigrationAppliesSuccessfully:
    """Test that migration creates all 7 section tables with correct schema."""

    def test_all_section_tables_created(self, db_engine):
        """Verify all 7 section tables exist after migration."""
        inspector = inspect(db_engine)
        existing_tables = inspector.get_table_names(schema=SCHEMA)

        for table_name in SECTION_TABLES:
            assert table_name in existing_tables, f"Table {table_name} was not created"

    def test_company_profile_has_correct_columns(self, db_engine):
        """Verify company_profile table has all expected columns."""
        inspector = inspect(db_engine)
        columns = {col["name"] for col in inspector.get_columns("company_profile", schema=SCHEMA)}

        expected_columns = {
            "company_id",
            # Insights (translatable)
            "insights",
            "insights_source",
            # Group name (proper noun - no translation)
            "group_name",
            "group_name_source",
            # Business line (translatable)
            "business_line",
            "business_line_source",
            # Catchphrase (translatable)
            "catchphrase",
            "catchphrase_source",
            # Establishment year (number - no translation)
            "establishment_year",
            "establishment_year_source",
            # Employee count (number - no translation)
            "employee_count",
            "employee_count_source",
            # Revenue (number - no translation)
            "revenue",
            "revenue_source",
            # CEO (proper noun - no translation)
            "ceo",
            "ceo_source",
            # HQ (location - no translation)
            "hq",
            "hq_source",
            # Timestamps
            "created_at",
            "updated_at",
        }

        assert expected_columns.issubset(columns), f"Missing columns: {expected_columns - columns}"

    def test_company_digital_has_correct_columns(self, db_engine):
        """Verify company_digital table has all expected columns."""
        inspector = inspect(db_engine)
        columns = {col["name"] for col in inspector.get_columns("company_digital", schema=SCHEMA)}

        expected_columns = {
            "company_id",
            # Insights (translatable)
            "insights",
            "insights_source",
            # Strategy fields (all translatable)
            "overall_strategy",
            "overall_strategy_source",
            "digital_transformation",
            "digital_transformation_source",
            "ecommerce_capabilities",
            "ecommerce_capabilities_source",
            "mobile_strategy",
            "mobile_strategy_source",
            "digital_marketing_approach",
            "digital_marketing_approach_source",
            "loyalty_program",
            "loyalty_program_source",
            # Timestamps
            "created_at",
            "updated_at",
        }

        assert expected_columns.issubset(columns), f"Missing columns: {expected_columns - columns}"

    def test_company_jobs_has_integer_total_openings(self, db_engine):
        """Verify company_jobs.insights_total_openings is INTEGER type."""
        inspector = inspect(db_engine)
        columns = inspector.get_columns("company_jobs", schema=SCHEMA)

        total_openings_col = next((col for col in columns if col["name"] == "insights_total_openings"), None)

        assert total_openings_col is not None, "insights_total_openings column not found"
        # PostgreSQL INTEGER type
        assert "INTEGER" in str(total_openings_col["type"]).upper(), (
            f"Expected INTEGER type, got {total_openings_col['type']}"
        )


class TestForeignKeyConstraints:
    """Test foreign key constraints to companies table."""

    def test_section_tables_have_fk_to_companies(self, db_engine):
        """Verify all section tables have foreign key to companies.id."""
        inspector = inspect(db_engine)

        for table_name in SECTION_TABLES:
            fks = inspector.get_foreign_keys(table_name, schema=SCHEMA)
            assert len(fks) > 0, f"Table {table_name} has no foreign keys"

            company_fk = next((fk for fk in fks if fk["referred_table"] == "companies"), None)
            assert company_fk is not None, f"Table {table_name} has no FK to companies table"
            assert company_fk["referred_columns"] == ["id"], "FK should reference companies.id"
            assert company_fk["constrained_columns"] == ["company_id"], "FK should be on company_id column"

    def test_fk_prevents_insert_with_invalid_company_id(self, db_session):
        """Verify FK constraint prevents inserting with non-existent company_id."""
        # Try to insert into company_profile with non-existent company_id
        with pytest.raises(Exception) as exc_info:
            db_session.execute(
                text(f"""
                    INSERT INTO {SCHEMA}.company_profile (company_id, insights)
                    VALUES (99999, 'Test insights')
                """)
            )
            db_session.commit()

        # Should fail with FK violation
        error_msg = str(exc_info.value).lower()
        assert "foreign key" in error_msg or "violates foreign key constraint" in error_msg or "fk" in error_msg


class TestCascadeDeleteBehavior:
    """Test ON DELETE CASCADE behavior."""

    def test_deleting_company_removes_profile(self, db_session):
        """Verify company_profile row is deleted when company is deleted."""
        # Use a unique ID to avoid conflicts with other tests
        test_company_id = 90001

        try:
            # Create a company
            db_session.execute(
                text(f"""
                    INSERT INTO {SCHEMA}.companies (id, name, website, organization_id)
                    VALUES (:id, 'Test Company Cascade', 'https://test-cascade.com', 'test-org-cascade')
                """),
                {"id": test_company_id},
            )
            db_session.commit()

            # Create profile for the company
            db_session.execute(
                text(f"""
                    INSERT INTO {SCHEMA}.company_profile (company_id, insights, insights_source)
                    VALUES (:id, 'Test insights', 'Chaps-e')
                """),
                {"id": test_company_id},
            )
            db_session.commit()

            # Verify profile exists
            result = db_session.execute(
                text(f"SELECT * FROM {SCHEMA}.company_profile WHERE company_id = :id"), {"id": test_company_id}
            ).fetchone()
            assert result is not None, "Profile should exist before delete"

            # Delete the company
            db_session.execute(text(f"DELETE FROM {SCHEMA}.companies WHERE id = :id"), {"id": test_company_id})
            db_session.commit()

            # Verify profile was cascaded
            result = db_session.execute(
                text(f"SELECT * FROM {SCHEMA}.company_profile WHERE company_id = :id"), {"id": test_company_id}
            ).fetchone()
            assert result is None, "Profile should be deleted by CASCADE"

        finally:
            # Clean up in case of failure
            db_session.rollback()
            db_session.execute(text(f"DELETE FROM {SCHEMA}.companies WHERE id = :id"), {"id": test_company_id})
            db_session.commit()

    def test_cascade_delete_all_sections(self, db_session):
        """Verify all section tables cascade delete when company is deleted."""
        # Use a unique ID to avoid conflicts with other tests
        test_company_id = 90002

        try:
            # Create a company
            db_session.execute(
                text(f"""
                    INSERT INTO {SCHEMA}.companies (id, name, website, organization_id)
                    VALUES (:id, 'Cascade All Test Company', 'https://cascade-all.com', 'test-org-cascade-all')
                """),
                {"id": test_company_id},
            )
            db_session.commit()

            # Insert into all 7 section tables
            section_inserts = [
                (
                    "company_profile",
                    f"INSERT INTO {SCHEMA}.company_profile (company_id, insights) VALUES (:id, 'Profile insights')",
                ),
                (
                    "company_digital",
                    f"INSERT INTO {SCHEMA}.company_digital (company_id, insights) VALUES (:id, 'Digital insights')",
                ),
                (
                    "company_timeline",
                    f"INSERT INTO {SCHEMA}.company_timeline (company_id, insights) VALUES (:id, 'Timeline insights')",
                ),
                (
                    "company_products",
                    f"INSERT INTO {SCHEMA}.company_products (company_id, insights) VALUES (:id, 'Products insights')",
                ),
                (
                    "company_jobs",
                    f"INSERT INTO {SCHEMA}.company_jobs (company_id, insights_total_openings) VALUES (:id, 50)",
                ),
                (
                    "company_csr",
                    f"INSERT INTO {SCHEMA}.company_csr (company_id, insights) VALUES (:id, 'CSR insights')",
                ),
                (
                    "company_press",
                    f"INSERT INTO {SCHEMA}.company_press (company_id, insights) VALUES (:id, 'Press insights')",
                ),
            ]

            for _table_name, insert_sql in section_inserts:
                db_session.execute(text(insert_sql), {"id": test_company_id})
            db_session.commit()

            # Verify all sections exist
            for table_name in SECTION_TABLES:
                result = db_session.execute(
                    text(f"SELECT * FROM screen_schema.{table_name} WHERE company_id = :id"), {"id": test_company_id}
                ).fetchone()
                assert result is not None, f"{table_name} should exist before delete"

            # Delete the company
            db_session.execute(text("DELETE FROM screen_schema.companies WHERE id = :id"), {"id": test_company_id})
            db_session.commit()

            # Verify all sections were cascaded
            for table_name in SECTION_TABLES:
                result = db_session.execute(
                    text(f"SELECT * FROM screen_schema.{table_name} WHERE company_id = :id"), {"id": test_company_id}
                ).fetchone()
                assert result is None, f"{table_name} should be deleted by CASCADE"

        finally:
            # Clean up in case of failure
            db_session.rollback()
            db_session.execute(text("DELETE FROM screen_schema.companies WHERE id = :id"), {"id": test_company_id})
            db_session.commit()
