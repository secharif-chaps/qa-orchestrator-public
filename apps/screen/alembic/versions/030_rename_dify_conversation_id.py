"""Rename dify_conversation_id to conversation_id

Revision ID: 030
Revises: 029
Create Date: 2026-03-18

Removes legacy Dify naming from the chapse_conversation_context table.
Renames column, unique index, and constraint accordingly.
"""

from alembic import op

# revision identifiers, used by Alembic.
revision = "030"
down_revision = "029"
branch_labels = None
depends_on = None

SCHEMA = "screen_schema"
TABLE = "chapse_conversation_context"


def upgrade() -> None:
    """Rename dify_conversation_id column and its index."""
    op.alter_column(
        TABLE,
        "dify_conversation_id",
        new_column_name="conversation_id",
        schema=SCHEMA,
    )

    # Rename the unique index
    op.execute(f"ALTER INDEX {SCHEMA}.ix_chapse_context_dify_id RENAME TO ix_chapse_context_conversation_id")


def downgrade() -> None:
    """Revert column and index rename."""
    op.alter_column(
        TABLE,
        "conversation_id",
        new_column_name="dify_conversation_id",
        schema=SCHEMA,
    )

    op.execute(f"ALTER INDEX {SCHEMA}.ix_chapse_context_conversation_id RENAME TO ix_chapse_context_dify_id")
