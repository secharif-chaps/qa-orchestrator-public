"""Migrate module tokens to global token balance

This migration:
1. Queries all unique organization_ids from organization_modules
2. For each org: SUMs token_count regardless of enabled state
3. Inserts organization record with summed balance
4. Creates initial TokenTransaction record for audit trail

Revision ID: 011
Revises: 010
Create Date: 2025-12-16
"""

from alembic import op
import sqlalchemy as sa
from sqlalchemy.sql import text

# revision identifiers, used by Alembic.
revision = "011"
down_revision = "010"
branch_labels = None
depends_on = None


def upgrade() -> None:
    """Migrate token data from organization_modules to organizations table."""
    connection = op.get_bind()

    # Query all unique organization_ids with their summed token counts
    # This includes disabled modules per requirements
    result = connection.execute(
        text("""
            SELECT organization_id, COALESCE(SUM(token_count), 0) as total_tokens
            FROM organization_modules
            GROUP BY organization_id
        """)
    )

    organizations_to_migrate = result.fetchall()

    for org_id, total_tokens in organizations_to_migrate:
        # Skip if organization already exists (idempotency)
        existing = connection.execute(
            text("SELECT 1 FROM organizations WHERE organization_id = :org_id"),
            {"org_id": org_id},
        ).fetchone()

        if existing:
            continue

        # Insert organization record with summed balance
        connection.execute(
            text("""
                INSERT INTO organizations (organization_id, token_balance, created_at)
                VALUES (:org_id, :token_balance, NOW())
            """),
            {"org_id": org_id, "token_balance": total_tokens},
        )

        # Create initial transaction record for audit trail
        # Only if there are tokens to record (skip zero-balance migrations)
        if total_tokens > 0:
            connection.execute(
                text("""
                    INSERT INTO token_transactions (
                        organization_id,
                        amount,
                        balance_after,
                        transaction_type,
                        reference_type,
                        created_at,
                        created_by
                    )
                    VALUES (
                        :org_id,
                        :amount,
                        :balance_after,
                        'adjustment',
                        'system',
                        NOW(),
                        'system'
                    )
                """),
                {
                    "org_id": org_id,
                    "amount": total_tokens,
                    "balance_after": total_tokens,
                },
            )


def downgrade() -> None:
    """Remove migrated organization and transaction records.

    Note: This only removes records created by this migration.
    It does NOT restore the original token_count values in organization_modules.
    The token_count column is preserved until the next migration removes it.
    """
    connection = op.get_bind()

    # Get organization IDs that were migrated (have system adjustment transactions)
    result = connection.execute(
        text("""
            SELECT DISTINCT organization_id
            FROM token_transactions
            WHERE transaction_type = 'adjustment'
            AND reference_type = 'system'
            AND created_by = 'system'
        """)
    )

    migrated_orgs = [row[0] for row in result.fetchall()]

    # Delete transaction records first (FK constraint)
    for org_id in migrated_orgs:
        connection.execute(
            text("""
                DELETE FROM token_transactions
                WHERE organization_id = :org_id
                AND transaction_type = 'adjustment'
                AND reference_type = 'system'
                AND created_by = 'system'
            """),
            {"org_id": org_id},
        )

    # Delete organization records that have no other transactions
    # (only records created by this migration)
    for org_id in migrated_orgs:
        # Check if organization has any remaining transactions
        remaining = connection.execute(
            text("""
                SELECT COUNT(*) FROM token_transactions
                WHERE organization_id = :org_id
            """),
            {"org_id": org_id},
        ).scalar()

        if remaining == 0:
            connection.execute(
                text("DELETE FROM organizations WHERE organization_id = :org_id"),
                {"org_id": org_id},
            )
