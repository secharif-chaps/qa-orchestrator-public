"""Clean up organization_modules table

This migration:
1. Drops the token_count column from organization_modules
2. Deletes any records where module_name = 'stream' (if any exist)

Note: The modulename enum doesn't include 'stream', so there can't be any
records with that value. The DELETE statement uses text cast to avoid enum
validation errors. This is a no-op but included for documentation purposes.

Revision ID: 012
Revises: 011
Create Date: 2025-12-16
"""

import sqlalchemy as sa
from sqlalchemy.sql import text

from alembic import op

# revision identifiers, used by Alembic.
revision = "012"
down_revision = "011"
branch_labels = None
depends_on = None


def upgrade() -> None:
    """Remove token_count column and clean up invalid module records."""
    connection = op.get_bind()

    # Delete any 'stream' module records if they exist
    # Since the modulename enum doesn't include 'stream', we need to cast
    # to text for comparison. This is a no-op if no stream records exist
    # (which should always be the case since stream was never a valid enum value)
    connection.execute(
        text("""
            DELETE FROM organization_modules
            WHERE module_name::text = 'stream'
        """)
    )

    # Drop the token_count column - tokens are now in organizations table
    op.drop_column("organization_modules", "token_count")


def downgrade() -> None:
    """Restore token_count column.

    Note: This does NOT restore the original token values.
    The token data is now in the organizations table.
    """
    # Add back token_count column with default 0
    op.add_column(
        "organization_modules",
        sa.Column(
            "token_count",
            sa.Integer(),
            nullable=False,
            server_default="0",
        ),
    )

    # Remove the server default after column is added
    op.alter_column(
        "organization_modules",
        "token_count",
        server_default=None,
    )
