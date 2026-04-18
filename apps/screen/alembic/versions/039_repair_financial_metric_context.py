"""Repair missing context column on company_financial_metric

Revision ID: 039
Revises: 038
Create Date: 2026-04-18

Background:
    Two different migrations previously both claimed revision "036":
      - "036_add_financial_metric_description" (adds `context` column) — merged via MR !197 (TAR-1406)
      - "036_add_outbox_table" (creates outbox table) — merged earlier
    TAR-1583 fixed the duplicate by renumbering the outbox one to 037 and
    stream_feature_flag to 038, producing the linear chain 035 -> 036 -> 037 -> 038.

    However, environments that had already applied the *old* 036 (outbox) and
    stored `alembic_version = 037` before the rename remain stuck: from
    Alembic's point of view, revisions 036 and 037 are already applied, so the
    *new* 036 (which adds `context`) is never replayed. The `context` column is
    therefore missing on those databases (staging, any dev that migrated before
    the rename), causing runtime errors like:

        psycopg2.errors.UndefinedColumn:
            column company_financial_metric.context does not exist

Fix:
    Idempotent repair step. Uses `ADD COLUMN IF NOT EXISTS` so it is a no-op on
    databases where 036 was applied correctly (fresh envs after the rename),
    and creates the column on legacy envs where it is missing.
"""

from alembic import op

# revision identifiers, used by Alembic.
revision = "039"
down_revision = "038"
branch_labels = None
depends_on = None

SCREEN_SCHEMA = "screen_schema"


def upgrade() -> None:
    """Add `context` column to company_financial_metric if absent."""
    op.execute(f"ALTER TABLE {SCREEN_SCHEMA}.company_financial_metric ADD COLUMN IF NOT EXISTS context TEXT")


def downgrade() -> None:
    """No-op: downgrading belongs to revision 036, not this repair step."""
    pass
