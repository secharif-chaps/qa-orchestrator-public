"""add module_name column to organization_modules

Revision ID: 003
Revises: 002
Create Date: 2025-11-17

"""

from alembic import op

# revision identifiers, used by Alembic.
revision = "003"
down_revision = "002_drop_workspace_tables"
branch_labels = None
depends_on = None


def upgrade() -> None:
    """Add module_name column and enum type if missing."""

    # Create modulename enum type if it doesn't exist
    op.execute("""
        DO $$ BEGIN
            CREATE TYPE modulename AS ENUM ('screen', 'target', 'explore');
        EXCEPTION
            WHEN duplicate_object THEN null;
        END $$;
    """)

    # Add module_name column if it doesn't exist
    op.execute("""
        DO $$ BEGIN
            ALTER TABLE organization_modules
            ADD COLUMN module_name modulename NOT NULL DEFAULT 'screen';
        EXCEPTION
            WHEN duplicate_column THEN null;
        END $$;
    """)

    # Add unique constraint if it doesn't exist
    op.execute("""
        DO $$ BEGIN
            ALTER TABLE organization_modules
            ADD CONSTRAINT uq_organization_modules_organization_module
            UNIQUE (organization_id, module_name);
        EXCEPTION
            WHEN duplicate_table THEN null;
        END $$;
    """)

    # Create index on module_name if it doesn't exist
    op.execute("""
        DO $$ BEGIN
            CREATE INDEX IF NOT EXISTS ix_organization_modules_module_name
            ON organization_modules (module_name);
        EXCEPTION
            WHEN duplicate_table THEN null;
        END $$;
    """)


def downgrade() -> None:
    """Remove module_name column."""
    op.drop_index("ix_organization_modules_module_name", table_name="organization_modules", if_exists=True)
    op.execute("ALTER TABLE organization_modules DROP CONSTRAINT IF EXISTS uq_organization_modules_organization_module")
    op.drop_column("organization_modules", "module_name")
    # Note: Not dropping the enum type as it might be used elsewhere
