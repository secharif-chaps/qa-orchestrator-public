"""Integration tests for company child tables migration (1:N relationships).

These tests verify the migration creates the correct database schema:
1. Indexes created on company_id columns
2. ENUM types created correctly
3. Self-referential FK for team_members (parent_id)
4. ON DELETE behaviors (CASCADE for company, SET NULL for parent)

NOTE: These tests use the main database (mint_db) because they test the migration itself,
not the SQLAlchemy models (which are created in a later task group).
"""

import os
import pytest
from sqlalchemy import create_engine, text, inspect
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
DATABASE_URL = os.environ.get(
    "DATABASE_URL",
    "postgresql://postgres:postgres@db:5432/mint_db"
)

# List of all 10 child tables created by the migration
CHILD_TABLES = [
    'company_online_services',
    'company_social_media_accounts',
    'company_timeline_events',
    'company_product_items',
    'company_product_categories',
    'company_job_offers',
    'company_csr_initiatives',
    'company_press_items',
    'company_team_members',
]

# Expected indexes for each table
EXPECTED_INDEXES = {
    'company_online_services': ['idx_online_services_company_id'],
    'company_social_media_accounts': ['idx_social_media_company_id'],
    'company_timeline_events': ['idx_timeline_events_company_id'],
    'company_product_items': ['idx_product_items_company_id'],
    'company_product_categories': ['idx_product_categories_company_id'],
    'company_job_offers': ['idx_job_offers_company_id'],
    'company_csr_initiatives': ['idx_csr_initiatives_company_id'],
    'company_press_items': ['idx_press_items_company_id'],
    'company_team_members': ['idx_team_members_company_id', 'idx_team_members_parent_id'],
}

# ENUM types and their expected values
EXPECTED_ENUMS = {
    'product_item_type_enum': ['range', 'partner_brand', 'private_label'],
    'csr_initiative_type_enum': ['responsibility', 'charity', 'sustainability', 'community', 'diversity', 'ethics', 'awards'],
    'press_item_type_enum': ['article', 'press_release', 'media_mention', 'award', 'product_launch', 'interview', 'financial', 'partnership'],
}


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


class TestIndexesCreatedOnCompanyIdColumns:
    """Test that indexes are created on company_id columns for all child tables."""

    def test_all_child_tables_created(self, db_engine):
        """Verify all 10 child tables exist after migration."""
        inspector = inspect(db_engine)
        existing_tables = inspector.get_table_names()

        for table_name in CHILD_TABLES:
            assert table_name in existing_tables, f"Table {table_name} was not created"

    def test_indexes_exist_on_company_id(self, db_engine):
        """Verify indexes are created on company_id columns."""
        inspector = inspect(db_engine)

        for table_name, expected_index_names in EXPECTED_INDEXES.items():
            indexes = inspector.get_indexes(table_name)
            index_names = [idx['name'] for idx in indexes]

            for expected_idx in expected_index_names:
                assert expected_idx in index_names, \
                    f"Index {expected_idx} not found on table {table_name}. Found: {index_names}"

    def test_company_id_index_on_correct_column(self, db_engine):
        """Verify company_id indexes are on the company_id column."""
        inspector = inspect(db_engine)

        for table_name in CHILD_TABLES:
            indexes = inspector.get_indexes(table_name)
            company_id_indexes = [
                idx for idx in indexes
                if 'company_id' in idx['name']
            ]

            assert len(company_id_indexes) >= 1, \
                f"No company_id index found on {table_name}"

            for idx in company_id_indexes:
                assert 'company_id' in idx['column_names'], \
                    f"Index {idx['name']} on {table_name} should include company_id column"


class TestEnumTypesCreatedCorrectly:
    """Test that ENUM types are created with correct values."""

    def test_enum_types_exist(self, db_session):
        """Verify all ENUM types exist in the database."""
        for enum_name in EXPECTED_ENUMS.keys():
            result = db_session.execute(
                text("""
                    SELECT typname FROM pg_type
                    WHERE typname = :enum_name
                """),
                {"enum_name": enum_name}
            ).fetchone()

            assert result is not None, f"ENUM type {enum_name} was not created"

    def test_enum_values_correct(self, db_session):
        """Verify ENUM types have correct values."""
        for enum_name, expected_values in EXPECTED_ENUMS.items():
            result = db_session.execute(
                text("""
                    SELECT enumlabel FROM pg_enum
                    JOIN pg_type ON pg_enum.enumtypid = pg_type.oid
                    WHERE pg_type.typname = :enum_name
                    ORDER BY pg_enum.enumsortorder
                """),
                {"enum_name": enum_name}
            ).fetchall()

            actual_values = [row[0] for row in result]
            assert actual_values == expected_values, \
                f"ENUM {enum_name} values mismatch. Expected: {expected_values}, Got: {actual_values}"

    def test_product_item_type_column_uses_enum(self, db_session):
        """Verify company_product_items.type uses product_item_type_enum via PostgreSQL catalog."""
        # Use PostgreSQL system catalog to verify column data type
        result = db_session.execute(
            text("""
                SELECT pg_type.typname
                FROM pg_attribute
                JOIN pg_class ON pg_attribute.attrelid = pg_class.oid
                JOIN pg_type ON pg_attribute.atttypid = pg_type.oid
                WHERE pg_class.relname = 'company_product_items'
                AND pg_attribute.attname = 'type'
            """)
        ).fetchone()

        assert result is not None, "type column not found"
        assert result[0] == 'product_item_type_enum', \
            f"Expected product_item_type_enum, got {result[0]}"

    def test_csr_initiative_type_column_uses_enum(self, db_session):
        """Verify company_csr_initiatives.type uses csr_initiative_type_enum via PostgreSQL catalog."""
        result = db_session.execute(
            text("""
                SELECT pg_type.typname
                FROM pg_attribute
                JOIN pg_class ON pg_attribute.attrelid = pg_class.oid
                JOIN pg_type ON pg_attribute.atttypid = pg_type.oid
                WHERE pg_class.relname = 'company_csr_initiatives'
                AND pg_attribute.attname = 'type'
            """)
        ).fetchone()

        assert result is not None, "type column not found"
        assert result[0] == 'csr_initiative_type_enum', \
            f"Expected csr_initiative_type_enum, got {result[0]}"

    def test_press_item_type_column_uses_enum(self, db_session):
        """Verify company_press_items.type uses press_item_type_enum via PostgreSQL catalog."""
        result = db_session.execute(
            text("""
                SELECT pg_type.typname
                FROM pg_attribute
                JOIN pg_class ON pg_attribute.attrelid = pg_class.oid
                JOIN pg_type ON pg_attribute.atttypid = pg_type.oid
                WHERE pg_class.relname = 'company_press_items'
                AND pg_attribute.attname = 'type'
            """)
        ).fetchone()

        assert result is not None, "type column not found"
        assert result[0] == 'press_item_type_enum', \
            f"Expected press_item_type_enum, got {result[0]}"


class TestSelfReferentialFKForTeamMembers:
    """Test self-referential parent_id FK for team_members hierarchy."""

    def test_parent_id_column_exists(self, db_engine):
        """Verify company_team_members has parent_id column."""
        inspector = inspect(db_engine)
        columns = {col['name'] for col in inspector.get_columns('company_team_members')}

        assert 'parent_id' in columns, "parent_id column not found in company_team_members"

    def test_parent_id_fk_references_same_table(self, db_engine):
        """Verify parent_id FK references company_team_members.id."""
        inspector = inspect(db_engine)
        fks = inspector.get_foreign_keys('company_team_members')

        parent_fk = next(
            (fk for fk in fks if 'parent_id' in fk['constrained_columns']),
            None
        )

        assert parent_fk is not None, "No FK found on parent_id column"
        assert parent_fk['referred_table'] == 'company_team_members', \
            "parent_id FK should reference company_team_members table"
        assert parent_fk['referred_columns'] == ['id'], \
            "parent_id FK should reference id column"

    def test_parent_id_is_nullable(self, db_engine):
        """Verify parent_id is nullable (CEO has no parent)."""
        inspector = inspect(db_engine)
        columns = inspector.get_columns('company_team_members')

        parent_id_col = next(
            (col for col in columns if col['name'] == 'parent_id'),
            None
        )

        assert parent_id_col is not None, "parent_id column not found"
        assert parent_id_col['nullable'] is True, "parent_id should be nullable"

    def test_parent_id_index_exists(self, db_engine):
        """Verify index exists on parent_id for hierarchy queries."""
        inspector = inspect(db_engine)
        indexes = inspector.get_indexes('company_team_members')
        index_names = [idx['name'] for idx in indexes]

        assert 'idx_team_members_parent_id' in index_names, \
            "Index on parent_id not found"


class TestOnDeleteBehaviors:
    """Test ON DELETE behaviors (CASCADE for company, SET NULL for parent)."""

    def test_company_cascade_deletes_child_records(self, db_session):
        """Verify deleting company cascades to all child tables."""
        test_company_id = 91001

        try:
            # Create a company
            db_session.execute(
                text("""
                    INSERT INTO companies (id, name, website, organization_id)
                    VALUES (:id, 'Child Tables Test Company', 'https://child-test.com', 'test-org-child')
                """),
                {"id": test_company_id}
            )
            db_session.commit()

            # Insert into all child tables
            child_inserts = [
                ("company_online_services", "INSERT INTO company_online_services (company_id, name) VALUES (:id, 'Test Service')"),
                ("company_social_media_accounts", "INSERT INTO company_social_media_accounts (company_id, platform) VALUES (:id, 'Twitter')"),
                ("company_timeline_events", "INSERT INTO company_timeline_events (company_id, title) VALUES (:id, 'Founded')"),
                ("company_product_items", "INSERT INTO company_product_items (company_id, type, value) VALUES (:id, 'range', 'Test Product')"),
                ("company_product_categories", "INSERT INTO company_product_categories (company_id, category_name) VALUES (:id, 'Electronics')"),
                ("company_job_offers", "INSERT INTO company_job_offers (company_id, title) VALUES (:id, 'Engineer')"),
                ("company_csr_initiatives", "INSERT INTO company_csr_initiatives (company_id, type, value) VALUES (:id, 'charity', 'Donation')"),
                ("company_press_items", "INSERT INTO company_press_items (company_id, type, value) VALUES (:id, 'article', 'News Article')"),
                ("company_team_members", "INSERT INTO company_team_members (company_id, first_name, last_name) VALUES (:id, 'John', 'Doe')"),
            ]

            for table_name, insert_sql in child_inserts:
                db_session.execute(text(insert_sql), {"id": test_company_id})
            db_session.commit()

            # Verify all children exist
            for table_name in CHILD_TABLES:
                result = db_session.execute(
                    text(f"SELECT COUNT(*) FROM {table_name} WHERE company_id = :id"),
                    {"id": test_company_id}
                ).scalar()
                assert result > 0, f"{table_name} should have records before delete"

            # Delete the company
            db_session.execute(
                text("DELETE FROM companies WHERE id = :id"),
                {"id": test_company_id}
            )
            db_session.commit()

            # Verify all children were cascaded
            for table_name in CHILD_TABLES:
                result = db_session.execute(
                    text(f"SELECT COUNT(*) FROM {table_name} WHERE company_id = :id"),
                    {"id": test_company_id}
                ).scalar()
                assert result == 0, f"{table_name} should be empty after CASCADE delete"

        finally:
            # Clean up in case of failure
            db_session.rollback()
            db_session.execute(
                text("DELETE FROM companies WHERE id = :id"),
                {"id": test_company_id}
            )
            try:
                db_session.commit()
            except Exception:
                db_session.rollback()

    def test_parent_set_null_on_delete(self, db_session):
        """Verify deleting parent team member sets children's parent_id to NULL."""
        test_company_id = 91002

        try:
            # Create a company
            db_session.execute(
                text("""
                    INSERT INTO companies (id, name, website, organization_id)
                    VALUES (:id, 'Team Hierarchy Test', 'https://team-test.com', 'test-org-team')
                """),
                {"id": test_company_id}
            )
            db_session.commit()

            # Create CEO (parent_id = NULL)
            db_session.execute(
                text("""
                    INSERT INTO company_team_members (id, company_id, first_name, last_name, position, parent_id)
                    VALUES (91101, :company_id, 'Jane', 'CEO', 'Chief Executive Officer', NULL)
                """),
                {"company_id": test_company_id}
            )

            # Create subordinate (parent_id = CEO's id)
            db_session.execute(
                text("""
                    INSERT INTO company_team_members (id, company_id, first_name, last_name, position, parent_id)
                    VALUES (91102, :company_id, 'John', 'Manager', 'Department Manager', 91101)
                """),
                {"company_id": test_company_id}
            )
            db_session.commit()

            # Verify subordinate has parent_id set
            result = db_session.execute(
                text("SELECT parent_id FROM company_team_members WHERE id = 91102")
            ).scalar()
            assert result == 91101, "Subordinate should have parent_id = 91101"

            # Delete the CEO (parent)
            db_session.execute(
                text("DELETE FROM company_team_members WHERE id = 91101")
            )
            db_session.commit()

            # Verify subordinate's parent_id is now NULL (not deleted)
            result = db_session.execute(
                text("SELECT id, parent_id FROM company_team_members WHERE id = 91102")
            ).fetchone()
            assert result is not None, "Subordinate should still exist after parent deletion"
            assert result[1] is None, "Subordinate's parent_id should be NULL after parent deletion"

        finally:
            # Clean up
            db_session.rollback()
            db_session.execute(
                text("DELETE FROM companies WHERE id = :id"),
                {"id": test_company_id}
            )
            try:
                db_session.commit()
            except Exception:
                db_session.rollback()

    def test_fk_prevents_invalid_company_id(self, db_session):
        """Verify FK constraint prevents inserting with non-existent company_id."""
        with pytest.raises(Exception) as exc_info:
            db_session.execute(
                text("""
                    INSERT INTO company_online_services (company_id, name)
                    VALUES (99999, 'Invalid Service')
                """)
            )
            db_session.commit()

        error_msg = str(exc_info.value).lower()
        assert 'foreign key' in error_msg or 'violates foreign key constraint' in error_msg or 'fk' in error_msg

    def test_fk_prevents_invalid_parent_id(self, db_session):
        """Verify FK constraint prevents inserting team member with non-existent parent_id."""
        test_company_id = 91003

        try:
            # Create a company first
            db_session.execute(
                text("""
                    INSERT INTO companies (id, name, website, organization_id)
                    VALUES (:id, 'Invalid Parent Test', 'https://invalid-parent.com', 'test-org-inv')
                """),
                {"id": test_company_id}
            )
            db_session.commit()

            # Try to insert team member with non-existent parent_id
            with pytest.raises(Exception) as exc_info:
                db_session.execute(
                    text("""
                        INSERT INTO company_team_members (company_id, first_name, last_name, parent_id)
                        VALUES (:id, 'Orphan', 'Employee', 99999)
                    """),
                    {"id": test_company_id}
                )
                db_session.commit()

            error_msg = str(exc_info.value).lower()
            assert 'foreign key' in error_msg or 'violates foreign key constraint' in error_msg or 'fk' in error_msg

        finally:
            # Clean up
            db_session.rollback()
            db_session.execute(
                text("DELETE FROM companies WHERE id = :id"),
                {"id": test_company_id}
            )
            try:
                db_session.commit()
            except Exception:
                db_session.rollback()


class TestTableSchemaCorrectness:
    """Additional tests for table schema correctness."""

    def test_online_services_has_correct_columns(self, db_engine):
        """Verify company_online_services has all expected columns."""
        inspector = inspect(db_engine)
        columns = {col['name'] for col in inspector.get_columns('company_online_services')}

        expected = {
            'id', 'company_id',
            'name', 'name_source', 'name_value_fr',
            'description', 'description_source', 'description_value_fr',
            'created_at'
        }
        assert expected.issubset(columns), f"Missing columns: {expected - columns}"

    def test_timeline_events_has_correct_columns(self, db_engine):
        """Verify company_timeline_events has all expected columns."""
        inspector = inspect(db_engine)
        columns = {col['name'] for col in inspector.get_columns('company_timeline_events')}

        expected = {
            'id', 'company_id',
            'date', 'date_source',
            'title', 'title_source', 'title_value_fr',
            'description', 'description_source', 'description_value_fr',
            'category', 'category_source', 'category_value_fr',
            'location', 'location_source',
            'impact', 'impact_source', 'impact_value_fr',
            'created_at'
        }
        assert expected.issubset(columns), f"Missing columns: {expected - columns}"

    def test_product_categories_has_array_columns(self, db_engine):
        """Verify company_product_categories has TEXT[] array columns."""
        inspector = inspect(db_engine)
        columns = inspector.get_columns('company_product_categories')

        items_col = next((col for col in columns if col['name'] == 'items'), None)
        items_fr_col = next((col for col in columns if col['name'] == 'items_value_fr'), None)

        assert items_col is not None, "items column not found"
        assert items_fr_col is not None, "items_value_fr column not found"

        # Check that these are array types
        assert 'ARRAY' in str(items_col['type']).upper(), \
            f"items should be ARRAY type, got {items_col['type']}"
        assert 'ARRAY' in str(items_fr_col['type']).upper(), \
            f"items_value_fr should be ARRAY type, got {items_fr_col['type']}"

    def test_team_members_has_correct_columns(self, db_engine):
        """Verify company_team_members has all expected columns."""
        inspector = inspect(db_engine)
        columns = {col['name'] for col in inspector.get_columns('company_team_members')}

        expected = {
            'id', 'company_id', 'parent_id',
            'position', 'position_source', 'position_value_fr',
            'first_name', 'first_name_source',
            'last_name', 'last_name_source',
            'linkedin_url', 'linkedin_url_source',
            'created_at'
        }
        assert expected.issubset(columns), f"Missing columns: {expected - columns}"
