"""LangGraph migration cleanup

Revision ID: 029
Revises: 028
Create Date: 2026-03-17

Removes Dify/Celery infrastructure from database:
- Deletes data_collection tasks
- Drops task_dependencies table
- Drops workflow_configs table
- Drops is_prerequisite column from tasks
- Drops raw knowledge columns from companies (except worldcheck)
- Rebuilds task_status_enum (removes 'blocked')
- Rebuilds task_type_enum (removes 'data_collection')
"""

import sqlalchemy as sa

from alembic import op

# revision identifiers, used by Alembic.
revision = "029"
down_revision = "028"
branch_labels = None
depends_on = None

SCHEMA = "screen_schema"


def upgrade():
    # 1. Delete data_collection tasks
    op.execute(f"DELETE FROM {SCHEMA}.tasks WHERE type = 'data_collection'")

    # 2. Drop task_dependencies table
    op.execute(f"DROP TABLE IF EXISTS {SCHEMA}.task_dependencies CASCADE")

    # 3. Drop workflow_configs table
    op.execute(f"DROP TABLE IF EXISTS {SCHEMA}.workflow_configs CASCADE")

    # 4. Drop is_prerequisite column and its index from tasks
    op.execute(f"DROP INDEX IF EXISTS {SCHEMA}.idx_tasks_is_prerequisite")
    op.drop_column("tasks", "is_prerequisite", schema=SCHEMA)

    # 5. Drop raw knowledge columns from companies (keep raw_worldcheck_knowledge)
    for col in [
        "raw_mistral_knowledge",
        "raw_gpt_knowledge",
        "raw_wikipedia_knowledge",
        "raw_scraped_website_knowledge",
        "raw_pappers_knowledge",
    ]:
        op.drop_column("companies", col, schema=SCHEMA)

    # 6. Drop materialized views that depend on the enums
    op.execute("DROP MATERIALIZED VIEW IF EXISTS public.task_type_cost_summary CASCADE")
    op.execute("DROP MATERIALIZED VIEW IF EXISTS public.organization_cost_summary CASCADE")

    # 7. Rebuild task_status_enum (remove 'blocked')
    op.execute("ALTER TYPE screen_schema.task_status_enum RENAME TO task_status_enum_old")
    op.execute("CREATE TYPE screen_schema.task_status_enum AS ENUM ('pending', 'running', 'succeeded', 'error')")
    op.execute(
        f"ALTER TABLE {SCHEMA}.tasks "
        f"ALTER COLUMN status TYPE screen_schema.task_status_enum "
        f"USING status::text::screen_schema.task_status_enum"
    )
    op.execute("DROP TYPE screen_schema.task_status_enum_old")

    # 8. Rebuild task_type_enum (remove 'data_collection')
    op.execute("ALTER TYPE screen_schema.task_type_enum RENAME TO task_type_enum_old")
    op.execute(
        "CREATE TYPE screen_schema.task_type_enum AS ENUM "
        "('profile', 'digital', 'timeline', 'products', 'jobs', 'csr', 'press', 'team')"
    )
    op.execute(
        f"ALTER TABLE {SCHEMA}.tasks "
        f"ALTER COLUMN type TYPE screen_schema.task_type_enum "
        f"USING type::text::screen_schema.task_type_enum"
    )
    op.execute("DROP TYPE screen_schema.task_type_enum_old")

    # 9. Recreate materialized views with updated enums
    op.execute("""
        CREATE MATERIALIZED VIEW public.task_type_cost_summary AS
        SELECT type AS task_type,
            count(*) AS total_tasks,
            count(CASE WHEN status = 'succeeded' THEN 1 ELSE NULL END) AS successful_tasks,
            count(CASE WHEN status = 'error' THEN 1 ELSE NULL END) AS failed_tasks,
            avg(CASE WHEN input_tokens IS NOT NULL THEN input_tokens ELSE NULL END) AS avg_input_tokens,
            avg(CASE WHEN output_tokens IS NOT NULL THEN output_tokens ELSE NULL END) AS avg_output_tokens,
            sum(CASE WHEN total_cost IS NOT NULL THEN total_cost ELSE 0.0 END) AS total_cost,
            avg(CASE WHEN total_cost IS NOT NULL THEN total_cost ELSE NULL END) AS avg_cost_per_task,
            min(created_at) AS first_task_date,
            max(created_at) AS last_task_date
        FROM screen_schema.tasks t
        GROUP BY type
    """)

    op.execute("""
        CREATE MATERIALIZED VIEW public.organization_cost_summary AS
        SELECT c.organization_id,
            count(DISTINCT c.id) AS total_companies,
            count(t.id) AS total_tasks,
            count(CASE WHEN t.status = 'succeeded' THEN 1 ELSE NULL END) AS successful_tasks,
            count(CASE WHEN t.status = 'error' THEN 1 ELSE NULL END) AS failed_tasks,
            sum(CASE WHEN t.total_cost IS NOT NULL THEN t.total_cost ELSE 0.0 END) AS total_cost,
            avg(CASE WHEN t.total_cost IS NOT NULL THEN t.total_cost ELSE NULL END) AS avg_cost_per_task,
            min(t.created_at) AS first_task_date,
            max(t.created_at) AS last_task_date
        FROM screen_schema.companies c
        LEFT JOIN screen_schema.tasks t ON c.id = t.company_id
        GROUP BY c.organization_id
    """)


def downgrade():
    # Rebuild enums with old values
    op.execute("ALTER TYPE screen_schema.task_type_enum RENAME TO task_type_enum_old")
    op.execute(
        "CREATE TYPE screen_schema.task_type_enum AS ENUM "
        "('profile', 'digital', 'timeline', 'products', 'jobs', 'csr', 'press', 'team', 'data_collection')"
    )
    op.execute(
        f"ALTER TABLE {SCHEMA}.tasks "
        f"ALTER COLUMN type TYPE screen_schema.task_type_enum "
        f"USING type::text::screen_schema.task_type_enum"
    )
    op.execute("DROP TYPE screen_schema.task_type_enum_old")

    op.execute("ALTER TYPE screen_schema.task_status_enum RENAME TO task_status_enum_old")
    op.execute(
        "CREATE TYPE screen_schema.task_status_enum AS ENUM ('pending', 'running', 'succeeded', 'error', 'blocked')"
    )
    op.execute(
        f"ALTER TABLE {SCHEMA}.tasks "
        f"ALTER COLUMN status TYPE screen_schema.task_status_enum "
        f"USING status::text::screen_schema.task_status_enum"
    )
    op.execute("DROP TYPE screen_schema.task_status_enum_old")

    # Restore columns
    for col in [
        "raw_mistral_knowledge",
        "raw_gpt_knowledge",
        "raw_wikipedia_knowledge",
        "raw_scraped_website_knowledge",
        "raw_pappers_knowledge",
    ]:
        op.add_column("companies", sa.Column(col, sa.String(), nullable=True), schema=SCHEMA)

    op.add_column(
        "tasks",
        sa.Column("is_prerequisite", sa.Boolean(), server_default="false", nullable=False),
        schema=SCHEMA,
    )
    op.create_index("idx_tasks_is_prerequisite", "tasks", ["is_prerequisite"], schema=SCHEMA)

    # Recreate task_dependencies table
    op.create_table(
        "task_dependencies",
        sa.Column("id", sa.Integer(), autoincrement=True, nullable=False),
        sa.Column("task_id", sa.Integer(), nullable=False),
        sa.Column("depends_on_task_id", sa.Integer(), nullable=False),
        sa.ForeignKeyConstraint(["task_id"], [f"{SCHEMA}.tasks.id"], ondelete="CASCADE"),
        sa.ForeignKeyConstraint(["depends_on_task_id"], [f"{SCHEMA}.tasks.id"], ondelete="CASCADE"),
        sa.PrimaryKeyConstraint("id"),
        sa.UniqueConstraint("task_id", "depends_on_task_id", name="uq_task_dependency"),
        schema=SCHEMA,
    )

    # Recreate workflow_configs table
    op.create_table(
        "workflow_configs",
        sa.Column("id", sa.Integer(), autoincrement=True, nullable=False),
        sa.Column("task_type", sa.String(50), nullable=False, unique=True),
        sa.Column("title", sa.String(255), nullable=False),
        sa.Column("api_key", sa.String(255), nullable=False),
        sa.Column("created_at", sa.DateTime(timezone=True), nullable=True),
        sa.Column("updated_at", sa.DateTime(timezone=True), nullable=True),
        sa.PrimaryKeyConstraint("id"),
        schema=SCHEMA,
    )
