"""003 Create organization tables in global_schema

Revision ID: b2c3d4e5f6g7
Revises: a1b2c3d4e5f6
Create Date: 2026-02-05 11:00:00.000000

This migration creates the organization-related tables in global_schema:
- organizations: Organization-level settings including token balance
- token_transactions: Audit log for all token operations
- organization_modules: Module enablement per organization
- organization_feature_flags: Feature flags per organization
"""

from typing import Sequence, Union

import sqlalchemy as sa
from alembic import op

# revision identifiers, used by Alembic.
revision: str = "b2c3d4e5f6g7"
down_revision: Union[str, Sequence[str], None] = "a1b2c3d4e5f6"
branch_labels: Union[str, Sequence[str], None] = None
depends_on: Union[str, Sequence[str], None] = None

SCHEMA = "global_schema"


def upgrade() -> None:
    """Create organization tables and ENUMs in global_schema.

    Note: ENUM types are auto-created by SQLAlchemy when used in sa.Enum columns.
    """

    # Create organizations table
    op.create_table(
        "organizations",
        sa.Column("organization_id", sa.String(), nullable=False),
        sa.Column("token_balance", sa.Integer(), nullable=False, server_default="0"),
        sa.Column(
            "created_at",
            sa.DateTime(timezone=True),
            nullable=False,
            server_default=sa.text("now()"),
        ),
        sa.Column("updated_at", sa.DateTime(timezone=True), nullable=True),
        sa.PrimaryKeyConstraint("organization_id"),
        schema=SCHEMA,
    )
    op.create_index(
        "ix_organizations_organization_id",
        "organizations",
        ["organization_id"],
        schema=SCHEMA,
    )

    # Create token_transactions table
    op.create_table(
        "token_transactions",
        sa.Column("id", sa.Integer(), nullable=False, autoincrement=True),
        sa.Column("organization_id", sa.String(), nullable=False),
        sa.Column("amount", sa.Integer(), nullable=False),
        sa.Column("balance_after", sa.Integer(), nullable=False),
        sa.Column(
            "transaction_type",
            sa.Enum(
                "add",
                "consume",
                "adjustment",
                name="transaction_type",
                schema=SCHEMA,
            ),
            nullable=False,
        ),
        sa.Column(
            "reference_type",
            sa.Enum(
                "company",
                "csv_import",
                "refresh",
                "manual",
                "system",
                name="reference_type",
                schema=SCHEMA,
            ),
            nullable=False,
        ),
        sa.Column("reference_id", sa.String(), nullable=True),
        sa.Column(
            "created_at",
            sa.DateTime(timezone=True),
            nullable=False,
            server_default=sa.text("now()"),
        ),
        sa.Column("created_by", sa.String(), nullable=False),
        sa.PrimaryKeyConstraint("id"),
        sa.ForeignKeyConstraint(
            ["organization_id"],
            [f"{SCHEMA}.organizations.organization_id"],
            ondelete="CASCADE",
        ),
        schema=SCHEMA,
    )
    op.create_index(
        "ix_token_transactions_id",
        "token_transactions",
        ["id"],
        schema=SCHEMA,
    )
    op.create_index(
        "ix_token_transactions_organization_id",
        "token_transactions",
        ["organization_id"],
        schema=SCHEMA,
    )
    op.create_index(
        "ix_token_transactions_org_created",
        "token_transactions",
        ["organization_id", "created_at"],
        schema=SCHEMA,
    )

    # Create organization_modules table
    op.create_table(
        "organization_modules",
        sa.Column("id", sa.Integer(), nullable=False, autoincrement=True),
        sa.Column("organization_id", sa.String(), nullable=False),
        sa.Column(
            "module_name",
            sa.Enum(
                "screen",
                "target",
                "explore",
                name="modulename",
                schema=SCHEMA,
            ),
            nullable=False,
        ),
        sa.Column("enabled", sa.Boolean(), nullable=False, server_default="false"),
        sa.Column(
            "created_at",
            sa.DateTime(timezone=True),
            server_default=sa.text("now()"),
        ),
        sa.Column("updated_at", sa.DateTime(timezone=True), nullable=True),
        sa.PrimaryKeyConstraint("id"),
        sa.UniqueConstraint(
            "organization_id",
            "module_name",
            name="uq_organization_modules_organization_module",
        ),
        schema=SCHEMA,
    )
    op.create_index(
        "ix_organization_modules_id",
        "organization_modules",
        ["id"],
        schema=SCHEMA,
    )
    op.create_index(
        "ix_organization_modules_organization_id",
        "organization_modules",
        ["organization_id"],
        schema=SCHEMA,
    )

    # Create organization_feature_flags table
    op.create_table(
        "organization_feature_flags",
        sa.Column("id", sa.Integer(), nullable=False, autoincrement=True),
        sa.Column("organization_id", sa.String(), nullable=False),
        sa.Column(
            "flag",
            sa.Enum(
                "translation",
                "discover",
                name="featureflag",
                schema=SCHEMA,
            ),
            nullable=False,
        ),
        sa.Column("enabled", sa.Boolean(), nullable=False, server_default="false"),
        sa.Column("enabled_at", sa.DateTime(timezone=True), nullable=True),
        sa.Column("config", sa.JSON(), nullable=True),
        sa.Column(
            "created_at",
            sa.DateTime(timezone=True),
            server_default=sa.text("now()"),
        ),
        sa.Column("updated_at", sa.DateTime(timezone=True), nullable=True),
        sa.PrimaryKeyConstraint("id"),
        sa.UniqueConstraint(
            "organization_id", "flag", name="uq_organization_feature_flags_org_flag"
        ),
        schema=SCHEMA,
    )
    op.create_index(
        "ix_organization_feature_flags_id",
        "organization_feature_flags",
        ["id"],
        schema=SCHEMA,
    )
    op.create_index(
        "ix_organization_feature_flags_organization_id",
        "organization_feature_flags",
        ["organization_id"],
        schema=SCHEMA,
    )
    op.create_index(
        "ix_organization_feature_flags_org_enabled",
        "organization_feature_flags",
        ["organization_id", "enabled"],
        schema=SCHEMA,
    )


def downgrade() -> None:
    """Drop organization tables and ENUMs from global_schema."""

    # Drop tables (in reverse order due to foreign keys)
    op.drop_table("organization_feature_flags", schema=SCHEMA)
    op.drop_table("organization_modules", schema=SCHEMA)
    op.drop_table("token_transactions", schema=SCHEMA)
    op.drop_table("organizations", schema=SCHEMA)

    # Drop ENUM types
    op.execute(f"DROP TYPE {SCHEMA}.featureflag")
    op.execute(f"DROP TYPE {SCHEMA}.modulename")
    op.execute(f"DROP TYPE {SCHEMA}.reference_type")
    op.execute(f"DROP TYPE {SCHEMA}.transaction_type")
