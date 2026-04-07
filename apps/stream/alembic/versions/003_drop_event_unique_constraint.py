"""Drop unique constraint on stream_events to allow repeated events with time-window dedup.

Revision ID: 003_drop_event_uq
Revises: 002_add_folder_id
Create Date: 2026-04-01
"""

from alembic import op

# revision identifiers
revision = "003_drop_event_uq"
down_revision = "002_add_folder_id"
branch_labels = None
depends_on = None

SCHEMA = "stream_schema"


def upgrade() -> None:
    op.drop_constraint("uq_event_source_entity_org", "stream_events", schema=SCHEMA)


def downgrade() -> None:
    op.create_unique_constraint(
        "uq_event_source_entity_org",
        "stream_events",
        ["source", "entity_id", "organization_id"],
        schema=SCHEMA,
    )
