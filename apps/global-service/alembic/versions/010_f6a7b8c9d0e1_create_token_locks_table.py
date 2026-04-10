"""010 Create token_locks table for lock/unlock pattern

Revision ID: f6a7b8c9d0e1
Revises: e5f6a7b8c9d0
Create Date: 2026-04-02 14:00:00.000000

Adds the token_locks table for pre-reserving tokens before an operation.
Supports SELECT FOR UPDATE for race condition prevention.
"""

from collections.abc import Sequence

import sqlalchemy as sa
from sqlalchemy.dialects.postgresql import UUID

from alembic import op

# revision identifiers, used by Alembic.
revision: str = "f6a7b8c9d0e1"
down_revision: str | Sequence[str] | None = "e5f6a7b8c9d0"
branch_labels: str | Sequence[str] | None = None
depends_on: str | Sequence[str] | None = None

SCHEMA = "global_schema"


def upgrade() -> None:
    # Create token_locks table
    # Note: sa.Enum auto-creates the PostgreSQL enum type
    op.create_table(
        "token_locks",
        sa.Column(
            "id",
            UUID(as_uuid=True),
            primary_key=True,
            server_default=sa.text("gen_random_uuid()"),
        ),
        sa.Column(
            "organization_id",
            sa.String(),
            sa.ForeignKey(
                f"{SCHEMA}.organizations.organization_id", ondelete="CASCADE"
            ),
            nullable=False,
        ),
        sa.Column("amount", sa.Integer(), nullable=False),
        sa.CheckConstraint("amount > 0", name="ck_token_locks_amount_positive"),
        sa.Column(
            "module",
            sa.Enum(
                "screen", "target", "explore", "stream",
                name="modulename",
                schema=SCHEMA,
                create_type=False,
            ),
            nullable=False,
        ),
        sa.Column("user_id", sa.String(), nullable=False),
        sa.Column("correlation_id", sa.String(), nullable=False),
        sa.UniqueConstraint("correlation_id", name="uq_token_locks_correlation_id"),
        sa.Column("reference_id", sa.String(), nullable=True),
        sa.Column(
            "status",
            sa.Enum(
                "locked", "confirmed", "released", "expired",
                name="token_lock_status",
                schema=SCHEMA,
            ),
            nullable=False,
            server_default="locked",
        ),
        sa.Column(
            "locked_at",
            sa.DateTime(timezone=True),
            server_default=sa.text("now()"),
            nullable=False,
        ),
        sa.Column("expires_at", sa.DateTime(timezone=True), nullable=False),
        sa.Column("settled_at", sa.DateTime(timezone=True), nullable=True),
        schema=SCHEMA,
    )

    # Create indexes
    op.create_index(
        "ix_token_locks_org_status",
        "token_locks",
        ["organization_id", "status"],
        schema=SCHEMA,
    )
    op.create_index(
        "ix_token_locks_expires_at",
        "token_locks",
        ["expires_at"],
        schema=SCHEMA,
    )


def downgrade() -> None:
    op.drop_index("ix_token_locks_expires_at", table_name="token_locks", schema=SCHEMA)
    op.drop_index("ix_token_locks_org_status", table_name="token_locks", schema=SCHEMA)
    op.drop_table("token_locks", schema=SCHEMA)
    sa.Enum(name="token_lock_status", schema=SCHEMA).drop(op.get_bind(), checkfirst=True)
