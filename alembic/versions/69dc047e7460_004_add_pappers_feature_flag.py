"""004 add pappers feature flag

Revision ID: 69dc047e7460
Revises: b2c3d4e5f6g7
Create Date: 2026-02-10 08:14:07.309501

This migration adds the 'pappers' value to the featureflag enum
in global_schema to support Pappers API integration.
"""
from typing import Sequence, Union

from alembic import op
import sqlalchemy as sa


# revision identifiers, used by Alembic.
revision: str = '69dc047e7460'
down_revision: Union[str, Sequence[str], None] = 'b2c3d4e5f6g7'
branch_labels: Union[str, Sequence[str], None] = None
depends_on: Union[str, Sequence[str], None] = None

SCHEMA = "global_schema"


def upgrade() -> None:
    """Add 'pappers' value to featureflag enum."""
    # Add new enum value to existing enum type
    op.execute(
        f"ALTER TYPE {SCHEMA}.featureflag "
        f"ADD VALUE IF NOT EXISTS 'pappers'"
    )


def downgrade() -> None:
    """Remove 'pappers' value from featureflag enum.

    Note: PostgreSQL doesn't support removing enum values directly.
    This would require recreating the enum type and all dependent columns,
    which is complex and risky. Instead, we leave the enum value in place.

    If strict rollback is required, manually drop and recreate the enum:
    1. ALTER TABLE organization_feature_flags
       ALTER COLUMN flag TYPE varchar;
    2. DROP TYPE global_schema.featureflag;
    3. CREATE TYPE global_schema.featureflag AS ENUM
       ('translation', 'discover');
    4. ALTER TABLE organization_feature_flags
       ALTER COLUMN flag TYPE global_schema.featureflag
       USING flag::global_schema.featureflag;
    """
    pass  # PostgreSQL doesn't support removing enum values
