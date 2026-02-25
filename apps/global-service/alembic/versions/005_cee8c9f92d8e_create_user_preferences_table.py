"""005 create user_preferences table in global_schema

Revision ID: cee8c9f92d8e
Revises: 69dc047e7460
Create Date: 2026-02-18

Adds user_preferences table to store user-specific preferences (AI preferences
for Chapse Assist, etc.) using a flexible JSONB structure keyed by Keycloak user UUID.
"""

from typing import Sequence, Union

import sqlalchemy as sa
from alembic import op

revision: str = "cee8c9f92d8e"
down_revision: Union[str, Sequence[str], None] = "69dc047e7460"
branch_labels: Union[str, Sequence[str], None] = None
depends_on: Union[str, Sequence[str], None] = None

SCHEMA = "global_schema"


def upgrade() -> None:
    """Create user_preferences table in global_schema."""
    op.create_table(
        "user_preferences",
        sa.Column("id", sa.Integer(), primary_key=True, autoincrement=True),
        sa.Column("user_id", sa.String(), nullable=False),
        sa.Column(
            "preferences",
            sa.dialects.postgresql.JSONB(),
            nullable=False,
            server_default="{}",
        ),
        sa.Column(
            "created_at",
            sa.DateTime(timezone=True),
            server_default=sa.func.now(),
            nullable=False,
        ),
        sa.Column(
            "updated_at",
            sa.DateTime(timezone=True),
            server_default=sa.func.now(),
            nullable=True,
        ),
        schema=SCHEMA,
    )

    # Unique index on user_id for fast lookups and upsert conflict detection
    op.create_index(
        "ix_user_preferences_user_id",
        "user_preferences",
        ["user_id"],
        unique=True,
        schema=SCHEMA,
    )


def downgrade() -> None:
    """Drop user_preferences table from global_schema."""
    op.drop_index(
        "ix_user_preferences_user_id",
        table_name="user_preferences",
        schema=SCHEMA,
    )
    op.drop_table("user_preferences", schema=SCHEMA)
