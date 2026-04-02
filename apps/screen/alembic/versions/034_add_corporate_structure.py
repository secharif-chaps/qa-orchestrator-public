"""Add corporate structure entities table and task type

Revision ID: 034
Revises: 033
Create Date: 2026-03-27

Adds:
- corporate_relationship_type_enum in screen_schema
- company_corporate_entities table in screen_schema
- 'corporate_structure' value to task_type_enum
"""

from alembic import op

revision = "034"
down_revision = "033"
branch_labels = None
depends_on = None

SCHEMA = "screen_schema"


def upgrade():
    # 1. Add 'corporate_structure' value to task_type_enum
    op.execute("ALTER TYPE screen_schema.task_type_enum ADD VALUE IF NOT EXISTS 'corporate_structure'")

    # 2. Create corporate_relationship_type_enum
    op.execute(
        f"DO $$ BEGIN "
        f"CREATE TYPE {SCHEMA}.corporate_relationship_type_enum AS ENUM "
        f"('parent', 'subsidiary', 'affiliate', 'branch', 'regional_entity'); "
        f"EXCEPTION WHEN duplicate_object THEN NULL; END $$"
    )

    # 3. Create company_corporate_entities table using raw SQL to avoid
    #    SQLAlchemy sa.Enum() attempting to recreate the enum type
    op.execute(f"""
        CREATE TABLE {SCHEMA}.company_corporate_entities (
            id SERIAL PRIMARY KEY,
            company_id INTEGER NOT NULL REFERENCES {SCHEMA}.companies(id) ON DELETE CASCADE,
            type {SCHEMA}.corporate_relationship_type_enum NOT NULL,
            name TEXT NOT NULL,
            name_source TEXT,
            country TEXT,
            country_source TEXT,
            source TEXT,
            wc_reference_id TEXT,
            match_strength TEXT,
            created_at TIMESTAMPTZ NOT NULL DEFAULT now()
        )
    """)
    op.execute(
        f"CREATE INDEX ix_company_corporate_entities_company_id ON {SCHEMA}.company_corporate_entities (company_id)"
    )


def downgrade():
    # 1. Drop materialized views
    op.execute("DROP MATERIALIZED VIEW IF EXISTS public.task_type_cost_summary CASCADE")
    op.execute("DROP MATERIALIZED VIEW IF EXISTS public.organization_cost_summary CASCADE")

    # 2. Drop company_corporate_entities table
    op.execute(f"DROP TABLE IF EXISTS {SCHEMA}.company_corporate_entities CASCADE")

    # 3. Drop corporate_relationship_type_enum
    op.execute(f"DROP TYPE IF EXISTS {SCHEMA}.corporate_relationship_type_enum")

    # 4. Rebuild task_type_enum without 'corporate_structure'
    op.execute(f"DELETE FROM {SCHEMA}.tasks WHERE type = 'corporate_structure'")
    op.execute("ALTER TYPE screen_schema.task_type_enum RENAME TO task_type_enum_old")
    op.execute(
        "CREATE TYPE screen_schema.task_type_enum AS ENUM "
        "('profile', 'digital', 'timeline', 'products', 'jobs', 'csr', 'press', 'team', 'financial')"
    )
    op.execute(
        f"ALTER TABLE {SCHEMA}.tasks "
        f"ALTER COLUMN type TYPE screen_schema.task_type_enum "
        f"USING type::text::screen_schema.task_type_enum"
    )
    op.execute("DROP TYPE screen_schema.task_type_enum_old")

    # 5. Recreate materialized views
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
