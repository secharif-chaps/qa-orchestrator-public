"""Tests for token data migration logic.

NOTE: These tests are skipped because the token migration logic has been migrated
to global-service as part of Story #870 (Phase 2 microservices migration).

These tests verify the migration from module-based tokens to global tokens:
1. Summing tokens across multiple modules for one organization
2. Organization record creation with summed balance
3. Initial transaction record creation for audit trail

Note: These tests use raw SQL to simulate the pre-migration state where
organization_modules has a token_count column. This is necessary because
the OrganizationModule model has been updated to not include token_count.
"""

import pytest
from sqlalchemy import text

from app.models.organization import (
    Organization,
    ReferenceType,
    TokenTransaction,
    TransactionType,
)

# Skip entire module - Token migration logic migrated to global-service (Story #870)
pytestmark = pytest.mark.skip(
    reason="Token migration logic migrated to global-service"
)


class TestTokenMigrationLogic:
    """Test migration logic for summing tokens across modules.

    These tests simulate the migration scenario using raw SQL queries,
    since the OrganizationModule model no longer has the token_count column.
    """

    def test_sum_tokens_across_modules_for_one_org(self, db_session):
        """Test summing token counts from all modules for a single organization.

        Verifies that:
        - All module token_counts are included in the sum
        - Disabled modules are also included (per requirements)
        """
        org_id = "org-migration-test-001"

        # Add token_count column if it doesn't exist (for testing migration)
        # This simulates the pre-migration database state
        try:
            db_session.execute(
                text("ALTER TABLE organization_modules ADD COLUMN IF NOT EXISTS token_count INTEGER DEFAULT 0")
            )
            db_session.commit()
        except Exception:
            db_session.rollback()

        # Insert test data using raw SQL (pre-migration state)
        db_session.execute(
            text("""
                INSERT INTO organization_modules (organization_id, module_name, enabled, token_count, created_at)
                VALUES
                    (:org_id, 'screen', true, 100, NOW()),
                    (:org_id, 'target', false, 50, NOW()),
                    (:org_id, 'explore', true, 75, NOW())
            """),
            {"org_id": org_id},
        )
        db_session.commit()

        # Simulate the migration query: sum token_count for the organization
        result = db_session.execute(
            text("""
                SELECT COALESCE(SUM(token_count), 0) as total_tokens
                FROM organization_modules
                WHERE organization_id = :org_id
            """),
            {"org_id": org_id},
        )
        total_tokens = result.scalar()

        # Expected: 100 (screen) + 50 (target, disabled) + 75 (explore) = 225
        assert total_tokens == 225

    def test_organization_record_creation_with_summed_balance(self, db_session):
        """Test creating Organization record with summed token balance.

        Verifies the pattern used in migration to create organization records.
        """
        org_id = "org-migration-test-002"

        # Add token_count column if it doesn't exist
        try:
            db_session.execute(
                text("ALTER TABLE organization_modules ADD COLUMN IF NOT EXISTS token_count INTEGER DEFAULT 0")
            )
            db_session.commit()
        except Exception:
            db_session.rollback()

        # Insert test data using raw SQL
        db_session.execute(
            text("""
                INSERT INTO organization_modules (organization_id, module_name, enabled, token_count, created_at)
                VALUES
                    (:org_id, 'screen', true, 200, NOW()),
                    (:org_id, 'target', true, 100, NOW())
            """),
            {"org_id": org_id},
        )
        db_session.commit()

        # Step 1: Calculate total tokens from modules (migration pattern)
        result = db_session.execute(
            text("""
                SELECT COALESCE(SUM(token_count), 0)
                FROM organization_modules
                WHERE organization_id = :org_id
            """),
            {"org_id": org_id},
        )
        total_tokens = result.scalar()

        # Step 2: Create Organization record with summed balance
        org = Organization(
            organization_id=org_id,
            token_balance=total_tokens,
        )
        db_session.add(org)
        db_session.commit()
        db_session.refresh(org)

        # Verify organization was created with correct balance
        assert org.organization_id == org_id
        assert org.token_balance == 300  # 200 + 100
        assert org.created_at is not None

    def test_initial_transaction_record_creation(self, db_session):
        """Test creating initial transaction record for migration audit trail.

        Verifies the migration creates a proper adjustment transaction.
        """
        org_id = "org-migration-test-003"

        # Add token_count column if it doesn't exist
        try:
            db_session.execute(
                text("ALTER TABLE organization_modules ADD COLUMN IF NOT EXISTS token_count INTEGER DEFAULT 0")
            )
            db_session.commit()
        except Exception:
            db_session.rollback()

        # Insert test data using raw SQL
        db_session.execute(
            text("""
                INSERT INTO organization_modules (organization_id, module_name, enabled, token_count, created_at)
                VALUES (:org_id, 'screen', true, 500, NOW())
            """),
            {"org_id": org_id},
        )
        db_session.commit()

        # Step 1: Calculate total tokens
        result = db_session.execute(
            text("""
                SELECT COALESCE(SUM(token_count), 0)
                FROM organization_modules
                WHERE organization_id = :org_id
            """),
            {"org_id": org_id},
        )
        total_tokens = result.scalar()

        # Step 2: Create Organization record
        org = Organization(
            organization_id=org_id,
            token_balance=total_tokens,
        )
        db_session.add(org)
        db_session.commit()

        # Step 3: Create initial transaction for audit trail
        transaction = TokenTransaction(
            organization_id=org_id,
            amount=total_tokens,  # Positive because it's an initial balance
            balance_after=total_tokens,
            transaction_type=TransactionType.adjustment,
            reference_type=ReferenceType.system,
            created_by="system",  # Migration runs as system
        )
        db_session.add(transaction)
        db_session.commit()
        db_session.refresh(transaction)

        # Verify transaction record
        assert transaction.id is not None
        assert transaction.organization_id == org_id
        assert transaction.amount == 500
        assert transaction.balance_after == 500
        assert transaction.transaction_type == TransactionType.adjustment
        assert transaction.reference_type == ReferenceType.system
        assert transaction.created_by == "system"


class TestMigrationEdgeCases:
    """Test edge cases for token migration."""

    def test_organization_with_zero_tokens(self, db_session):
        """Test migration handles organization with zero tokens correctly."""
        org_id = "org-zero-tokens"

        # Add token_count column if it doesn't exist
        try:
            db_session.execute(
                text("ALTER TABLE organization_modules ADD COLUMN IF NOT EXISTS token_count INTEGER DEFAULT 0")
            )
            db_session.commit()
        except Exception:
            db_session.rollback()

        # Insert module with zero tokens
        db_session.execute(
            text("""
                INSERT INTO organization_modules (organization_id, module_name, enabled, token_count, created_at)
                VALUES (:org_id, 'screen', true, 0, NOW())
            """),
            {"org_id": org_id},
        )
        db_session.commit()

        # Calculate total
        result = db_session.execute(
            text("""
                SELECT COALESCE(SUM(token_count), 0)
                FROM organization_modules
                WHERE organization_id = :org_id
            """),
            {"org_id": org_id},
        )
        total_tokens = result.scalar()

        assert total_tokens == 0

        # Create organization with zero balance
        org = Organization(
            organization_id=org_id,
            token_balance=total_tokens,
        )
        db_session.add(org)
        db_session.commit()

        assert org.token_balance == 0

    def test_disabled_modules_included_in_sum(self, db_session):
        """Test that disabled modules' tokens are included in migration sum.

        Per requirements: sum all tokens regardless of enabled/disabled state.
        """
        org_id = "org-disabled-test"

        # Add token_count column if it doesn't exist
        try:
            db_session.execute(
                text("ALTER TABLE organization_modules ADD COLUMN IF NOT EXISTS token_count INTEGER DEFAULT 0")
            )
            db_session.commit()
        except Exception:
            db_session.rollback()

        # Insert only disabled modules with tokens
        db_session.execute(
            text("""
                INSERT INTO organization_modules (organization_id, module_name, enabled, token_count, created_at)
                VALUES
                    (:org_id, 'screen', false, 100, NOW()),
                    (:org_id, 'target', false, 200, NOW())
            """),
            {"org_id": org_id},
        )
        db_session.commit()

        # Sum should include disabled modules
        result = db_session.execute(
            text("""
                SELECT COALESCE(SUM(token_count), 0)
                FROM organization_modules
                WHERE organization_id = :org_id
            """),
            {"org_id": org_id},
        )
        total_tokens = result.scalar()

        assert total_tokens == 300

    def test_migration_multiple_organizations(self, db_session):
        """Test migration correctly handles multiple organizations."""
        # Add token_count column if it doesn't exist
        try:
            db_session.execute(
                text("ALTER TABLE organization_modules ADD COLUMN IF NOT EXISTS token_count INTEGER DEFAULT 0")
            )
            db_session.commit()
        except Exception:
            db_session.rollback()

        # Insert data for multiple organizations
        db_session.execute(
            text("""
                INSERT INTO organization_modules (organization_id, module_name, enabled, token_count, created_at)
                VALUES
                    ('org-multi-001', 'screen', true, 200, NOW()),
                    ('org-multi-001', 'target', true, 100, NOW()),
                    ('org-multi-002', 'explore', false, 150, NOW()),
                    ('org-multi-003', 'screen', true, 0, NOW()),
                    ('org-multi-003', 'target', true, 0, NOW())
            """)
        )
        db_session.commit()

        # Expected sums per organization
        expected_sums = {
            "org-multi-001": 300,  # 200 + 100
            "org-multi-002": 150,  # 150
            "org-multi-003": 0,    # 0 + 0
        }

        # Query unique org IDs with their token sums
        result = db_session.execute(
            text("""
                SELECT organization_id, COALESCE(SUM(token_count), 0) as total_tokens
                FROM organization_modules
                WHERE organization_id LIKE 'org-multi-%'
                GROUP BY organization_id
            """)
        )
        rows = result.fetchall()

        # Verify sums for each organization
        for org_id, total_tokens in rows:
            assert total_tokens == expected_sums[org_id], f"Mismatch for {org_id}"

        # Verify all organizations were found
        assert len(rows) == 3
