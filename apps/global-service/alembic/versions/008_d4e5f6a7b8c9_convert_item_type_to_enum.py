"""008 Convert folder_items.item_type from String to Enum

Revision ID: d4e5f6a7b8c9
Revises: c3d4e5f6a7b8
Create Date: 2026-02-16 16:00:00.000000

Converts the item_type column from a VARCHAR to a PostgreSQL enum type.
Enum values: company (Screen), watchfile (Target), explore (Explore).
"""
from typing import Sequence, Union

from alembic import op
import sqlalchemy as sa


# revision identifiers, used by Alembic.
revision: str = 'd4e5f6a7b8c9'
down_revision: Union[str, Sequence[str], None] = 'c3d4e5f6a7b8'
branch_labels: Union[str, Sequence[str], None] = None
depends_on: Union[str, Sequence[str], None] = None

SCHEMA = 'global_schema'


def upgrade() -> None:
    """Convert item_type from String to Enum."""
    # 1. Create the item_type enum type in global_schema
    op.execute("""
        DO $$ BEGIN
            CREATE TYPE global_schema.item_type AS ENUM ('company', 'watchfile', 'explore');
        EXCEPTION
            WHEN duplicate_object THEN NULL;
        END $$;
    """)

    # 2. Alter column: cast existing String values to the new enum type
    op.execute("""
        ALTER TABLE global_schema.folder_items
        ALTER COLUMN item_type TYPE global_schema.item_type
        USING item_type::global_schema.item_type;
    """)


def downgrade() -> None:
    """Revert item_type back to String."""
    # 1. Convert enum column back to varchar
    op.execute("""
        ALTER TABLE global_schema.folder_items
        ALTER COLUMN item_type TYPE VARCHAR
        USING item_type::text;
    """)

    # 2. Drop the enum type
    op.execute("DROP TYPE IF EXISTS global_schema.item_type;")
