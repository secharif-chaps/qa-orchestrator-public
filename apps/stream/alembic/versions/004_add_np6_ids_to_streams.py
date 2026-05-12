"""Add np6_action_id column to streams.

The column is nullable: only newsletter streams populate it, and it is
filled lazily on the first dispatch (see ADR-0020 §"Mapping Newsletter
Concepts to NP6 Primitives", amended 2026-05-04). Non-newsletter streams
(Teams, Slack, Webhook) leave it NULL.

We intentionally do NOT track an `np6_segment_id`: the validated NP6
workflow addresses recipients directly by `unicity` in `/actions/{id}/executions`,
so per-stream segments are unnecessary. The only segment in play is the
tenant-wide BAT-test segment, configured via `NP6_BAT_TEST_SEGMENT_ID`.

Revision ID: 004_np6_ids_streams
Revises: 003_drop_event_uq
Create Date: 2026-04-30
"""

import sqlalchemy as sa

from alembic import op

revision = "004_np6_ids_streams"
down_revision = "003_drop_event_uq"
branch_labels = None
depends_on = None

SCHEMA = "stream_schema"


def upgrade() -> None:
    op.add_column(
        "streams",
        sa.Column("np6_action_id", sa.String(), nullable=True),
        schema=SCHEMA,
    )


def downgrade() -> None:
    op.drop_column("streams", "np6_action_id", schema=SCHEMA)
