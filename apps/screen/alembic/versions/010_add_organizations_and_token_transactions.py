"""Add organizations and token_transactions tables

This migration creates the new tables for the global token system:
1. organizations - Stores organization-level settings including global token balance
2. token_transactions - Audit log for all token operations

This is the first migration in the global token system feature.
Token data migration and organization_modules cleanup will follow in
separate migrations.

Revision ID: 010
Revises: 009
Create Date: 2025-12-16
"""

from alembic import op
import sqlalchemy as sa
from sqlalchemy.dialects import postgresql

# revision identifiers, used by Alembic.
revision = '010'
down_revision = '009'
branch_labels = None
depends_on = None


def upgrade() -> None:
    """Create organizations and token_transactions tables."""

    # Create transaction_type enum
    op.execute("CREATE TYPE transaction_type AS ENUM ('add', 'consume', 'adjustment')")

    # Create reference_type enum
    op.execute("CREATE TYPE reference_type AS ENUM ('company', 'csv_import', 'manual', 'system')")

    # Create organizations table
    op.create_table(
        'organizations',
        sa.Column('organization_id', sa.String(), nullable=False),
        sa.Column('token_balance', sa.Integer(), nullable=False, server_default='0'),
        sa.Column(
            'created_at',
            sa.DateTime(timezone=True),
            server_default=sa.func.now(),
            nullable=False
        ),
        sa.Column('updated_at', sa.DateTime(timezone=True), nullable=True),
        sa.PrimaryKeyConstraint('organization_id')
    )

    # Create index on organization_id (for performance on lookups)
    op.create_index(
        'ix_organizations_organization_id',
        'organizations',
        ['organization_id'],
        unique=True
    )

    # Create token_transactions table
    op.create_table(
        'token_transactions',
        sa.Column('id', sa.Integer(), nullable=False),
        sa.Column('organization_id', sa.String(), nullable=False),
        sa.Column('amount', sa.Integer(), nullable=False),
        sa.Column('balance_after', sa.Integer(), nullable=False),
        sa.Column(
            'transaction_type',
            postgresql.ENUM('add', 'consume', 'adjustment', name='transaction_type', create_type=False),
            nullable=False
        ),
        sa.Column(
            'reference_type',
            postgresql.ENUM('company', 'csv_import', 'manual', 'system', name='reference_type', create_type=False),
            nullable=False
        ),
        sa.Column('reference_id', sa.String(), nullable=True),
        sa.Column(
            'created_at',
            sa.DateTime(timezone=True),
            server_default=sa.func.now(),
            nullable=False
        ),
        sa.Column('created_by', sa.String(), nullable=False),
        sa.ForeignKeyConstraint(
            ['organization_id'],
            ['organizations.organization_id'],
            ondelete='CASCADE'
        ),
        sa.PrimaryKeyConstraint('id')
    )

    # Create index on id
    op.create_index(
        'ix_token_transactions_id',
        'token_transactions',
        ['id'],
        unique=False
    )

    # Create index on organization_id
    op.create_index(
        'ix_token_transactions_organization_id',
        'token_transactions',
        ['organization_id'],
        unique=False
    )

    # Create composite index on (organization_id, created_at) for efficient history queries
    op.create_index(
        'ix_token_transactions_org_created',
        'token_transactions',
        ['organization_id', 'created_at'],
        unique=False
    )


def downgrade() -> None:
    """Drop token_transactions and organizations tables."""

    # Drop indexes
    op.drop_index('ix_token_transactions_org_created', table_name='token_transactions')
    op.drop_index('ix_token_transactions_organization_id', table_name='token_transactions')
    op.drop_index('ix_token_transactions_id', table_name='token_transactions')

    # Drop token_transactions table
    op.drop_table('token_transactions')

    # Drop organizations index
    op.drop_index('ix_organizations_organization_id', table_name='organizations')

    # Drop organizations table
    op.drop_table('organizations')

    # Drop enum types
    op.execute('DROP TYPE IF EXISTS reference_type')
    op.execute('DROP TYPE IF EXISTS transaction_type')
