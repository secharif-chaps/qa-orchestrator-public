"""Add organization_feature_flags table.

Revision ID: 019
Revises: 018
Create Date: 2026-01-15

This migration creates the organization_feature_flags table for managing
add-on feature capabilities per organization. Unlike core modules (screen,
target, explore), feature flags are enhancements that can be toggled on/off.

Feature flags are OFF by default - organizations must explicitly enable them.
"""

from alembic import op

# revision identifiers, used by Alembic.
revision = "019"
down_revision = "018"
branch_labels = None
depends_on = None


def upgrade() -> None:
    """Create organization_feature_flags table and featureflag enum."""
    # Create the featureflag enum type if it doesn't exist
    op.execute("""
        DO $$
        BEGIN
            IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'featureflag') THEN
                CREATE TYPE featureflag AS ENUM ('translation');
            END IF;
        END
        $$;
    """)

    # Create the organization_feature_flags table using raw SQL
    # to avoid SQLAlchemy trying to create the enum again
    op.execute("""
        CREATE TABLE IF NOT EXISTS organization_feature_flags (
            id SERIAL PRIMARY KEY,
            organization_id VARCHAR NOT NULL,
            flag featureflag NOT NULL,
            enabled BOOLEAN NOT NULL DEFAULT FALSE,
            enabled_at TIMESTAMP WITH TIME ZONE,
            config JSONB,
            created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
            updated_at TIMESTAMP WITH TIME ZONE,
            CONSTRAINT uq_organization_feature_flags_org_flag
                UNIQUE (organization_id, flag)
        );
    """)

    # Create indexes
    op.execute("""
        CREATE INDEX IF NOT EXISTS ix_organization_feature_flags_organization_id
        ON organization_feature_flags (organization_id);
    """)
    op.execute("""
        CREATE INDEX IF NOT EXISTS ix_organization_feature_flags_org_enabled
        ON organization_feature_flags (organization_id, enabled);
    """)


def downgrade() -> None:
    """Drop organization_feature_flags table and featureflag enum."""
    op.execute("DROP INDEX IF EXISTS ix_organization_feature_flags_org_enabled")
    op.execute("DROP INDEX IF EXISTS ix_organization_feature_flags_organization_id")
    op.execute("DROP TABLE IF EXISTS organization_feature_flags")
    op.execute("DROP TYPE IF EXISTS featureflag")
