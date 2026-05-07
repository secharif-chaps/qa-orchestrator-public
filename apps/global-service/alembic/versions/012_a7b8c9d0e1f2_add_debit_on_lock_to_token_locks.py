"""012 add debit_on_lock to token_locks

Revision ID: a7b8c9d0e1f2
Revises: 0e864470a6b4
Create Date: 2026-05-06 10:00:00.000000

Adds a discriminator column to ``token_locks`` so the available-balance
computation can tell apart:

- proxy locks (``debit_on_lock=True``): the proxy ``TokenLockManager.lock``
  already deducts the amount from ``organizations.token_balance`` at lock
  time. The lock acts as a refund reservation if the proxied call fails.
- service locks (``debit_on_lock=False``): the service ``TokenManager
  .lock_tokens`` flow does NOT debit the balance at lock time. The balance
  is decremented at confirm time. ``available_balance = token_balance -
  sum(active service locks)``.

Without this column ``_sum_active_locks`` would double-count proxy locks
(the amount has already been removed from ``token_balance``) and produce
spurious ``402 insufficient balance`` errors as soon as both producers run
in the same organization.
"""

from collections.abc import Sequence

import sqlalchemy as sa

from alembic import op

revision: str = "a7b8c9d0e1f2"
down_revision: str | Sequence[str] | None = "0e864470a6b4"
branch_labels: str | Sequence[str] | None = None
depends_on: str | Sequence[str] | None = None

SCHEMA = "global_schema"


def upgrade() -> None:
    op.add_column(
        "token_locks",
        sa.Column(
            "debit_on_lock",
            sa.Boolean(),
            nullable=False,
            server_default=sa.text("false"),
        ),
        schema=SCHEMA,
    )


def downgrade() -> None:
    op.drop_column("token_locks", "debit_on_lock", schema=SCHEMA)
