"""Create stream tables.

Revision ID: 001_create_stream
Revises:
Create Date: 2026-03-30

"""

import sqlalchemy as sa
from sqlalchemy.dialects import postgresql

from alembic import op

# revision identifiers
revision = "001_create_stream"
down_revision = None
branch_labels = None
depends_on = None

SCHEMA = "stream_schema"


def upgrade() -> None:
    # Create schema
    op.execute(f"CREATE SCHEMA IF NOT EXISTS {SCHEMA}")

    # Create enum types explicitly
    op.execute(f"CREATE TYPE {SCHEMA}.channel_type_enum AS ENUM ('teams', 'slack_webhook', 'webhook')")
    op.execute(f"CREATE TYPE {SCHEMA}.stream_mode_enum AS ENUM ('live', 'recurrence')")
    op.execute(f"CREATE TYPE {SCHEMA}.stream_status_enum AS ENUM ('draft', 'active', 'paused', 'archived')")
    op.execute(f"CREATE TYPE {SCHEMA}.delivery_status_enum AS ENUM ('pending', 'delivered', 'failed', 'skipped')")

    # Create streams table
    op.create_table(
        "streams",
        sa.Column("id", sa.Integer(), primary_key=True, autoincrement=True),
        sa.Column("name", sa.String(255), nullable=False),
        sa.Column("description", sa.Text(), nullable=True),
        sa.Column(
            "channel_type",
            postgresql.ENUM("teams", "slack_webhook", "webhook", name="channel_type_enum", schema=SCHEMA, create_type=False),
            nullable=False,
        ),
        sa.Column("channel_config", postgresql.JSONB(), nullable=False, server_default="{}"),
        sa.Column(
            "mode",
            postgresql.ENUM("live", "recurrence", name="stream_mode_enum", schema=SCHEMA, create_type=False),
            nullable=False,
        ),
        sa.Column("cron_expression", sa.String(100), nullable=True),
        sa.Column(
            "status",
            postgresql.ENUM("draft", "active", "paused", "archived", name="stream_status_enum", schema=SCHEMA, create_type=False),
            nullable=False,
            server_default="active",
        ),
        sa.Column("organization_id", sa.String(), nullable=False),
        sa.Column("owner_id", sa.String(), nullable=False),
        sa.Column("owner_username", sa.String(), nullable=True),
        sa.Column("subscribed_events", postgresql.JSONB(), nullable=False, server_default="[]"),
        sa.Column("created_at", sa.DateTime(timezone=True), server_default=sa.func.now(), nullable=False),
        sa.Column("updated_at", sa.DateTime(timezone=True), server_default=sa.func.now(), nullable=False),
        schema=SCHEMA,
    )
    op.create_index("ix_streams_organization_id", "streams", ["organization_id"], schema=SCHEMA)

    # Create stream_events table
    op.create_table(
        "stream_events",
        sa.Column("id", sa.Integer(), primary_key=True, autoincrement=True),
        sa.Column("event_type", sa.String(255), nullable=False),
        sa.Column("folder_id", sa.String(), nullable=False),
        sa.Column("source", sa.String(100), nullable=False),
        sa.Column("payload", postgresql.JSONB(), nullable=False, server_default="{}"),
        sa.Column("organization_id", sa.String(), nullable=False),
        sa.Column("is_deleted", sa.Boolean(), nullable=False, server_default="false"),
        sa.Column("created_at", sa.DateTime(timezone=True), server_default=sa.func.now(), nullable=False),
        sa.Column("entity_id", sa.String(255), nullable=True),
        sa.Column("entity_type", sa.String(100), nullable=True),
        sa.Column("summary", sa.Text(), nullable=True),
        schema=SCHEMA,
    )
    op.create_index("ix_stream_events_event_type", "stream_events", ["event_type"], schema=SCHEMA)
    op.create_index("ix_stream_events_folder_id", "stream_events", ["folder_id"], schema=SCHEMA)
    op.create_index("ix_stream_events_source", "stream_events", ["source"], schema=SCHEMA)
    op.create_index("ix_stream_events_organization_id", "stream_events", ["organization_id"], schema=SCHEMA)
    op.create_unique_constraint(
        "uq_event_source_entity_org",
        "stream_events",
        ["source", "entity_id", "organization_id"],
        schema=SCHEMA,
    )

    # Create stream_deliveries table
    op.create_table(
        "stream_deliveries",
        sa.Column("id", sa.Integer(), primary_key=True, autoincrement=True),
        sa.Column(
            "stream_id",
            sa.Integer(),
            sa.ForeignKey(f"{SCHEMA}.streams.id", ondelete="CASCADE"),
            nullable=False,
        ),
        sa.Column(
            "event_id",
            sa.Integer(),
            sa.ForeignKey(f"{SCHEMA}.stream_events.id", ondelete="CASCADE"),
            nullable=False,
        ),
        sa.Column(
            "status",
            postgresql.ENUM("pending", "delivered", "failed", "skipped", name="delivery_status_enum", schema=SCHEMA, create_type=False),
            nullable=False,
            server_default="pending",
        ),
        sa.Column("error_message", sa.Text(), nullable=True),
        sa.Column("response_metadata", postgresql.JSONB(), nullable=True),
        sa.Column("delivered_at", sa.DateTime(timezone=True), nullable=True),
        sa.Column("created_at", sa.DateTime(timezone=True), server_default=sa.func.now(), nullable=False),
        sa.Column("attempt_count", sa.Integer(), nullable=False, server_default="0"),
        sa.Column("next_retry_at", sa.DateTime(timezone=True), nullable=True),
        schema=SCHEMA,
    )
    op.create_index("ix_stream_deliveries_stream_id", "stream_deliveries", ["stream_id"], schema=SCHEMA)
    op.create_index("ix_stream_deliveries_event_id", "stream_deliveries", ["event_id"], schema=SCHEMA)


def downgrade() -> None:
    op.drop_table("stream_deliveries", schema=SCHEMA)
    op.drop_table("stream_events", schema=SCHEMA)
    op.drop_table("streams", schema=SCHEMA)

    op.execute(f"DROP TYPE IF EXISTS {SCHEMA}.delivery_status_enum")
    op.execute(f"DROP TYPE IF EXISTS {SCHEMA}.stream_status_enum")
    op.execute(f"DROP TYPE IF EXISTS {SCHEMA}.stream_mode_enum")
    op.execute(f"DROP TYPE IF EXISTS {SCHEMA}.channel_type_enum")

    op.execute(f"DROP SCHEMA IF EXISTS {SCHEMA} CASCADE")
