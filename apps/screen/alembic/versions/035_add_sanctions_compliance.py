"""Add sanctions and compliance tables and task type

Revision ID: 035
Revises: 034
Create Date: 2026-03-29

Adds:
- sanction_type_enum in screen_schema
- risk_level_enum in screen_schema
- company_sanctions table in screen_schema (1:1 summary)
- company_sanction_items table in screen_schema (1:N items)
- 'sanctions' value to task_type_enum
"""

from alembic import op

revision = "035"
down_revision = "034"
branch_labels = None
depends_on = None

SCHEMA = "screen_schema"


def upgrade():
    # 1. Add 'sanctions' value to task_type_enum
    op.execute("ALTER TYPE screen_schema.task_type_enum ADD VALUE IF NOT EXISTS 'sanctions'")

    # 2. Create sanction_type_enum
    op.execute(
        f"DO $$ BEGIN "
        f"CREATE TYPE {SCHEMA}.sanction_type_enum AS ENUM "
        f"('unfair_competition', 'data_protection', 'consumer_protection', "
        f"'ip_rights_infringement', 'regulatory_enforcement', 'financial_crime', "
        f"'corruption', 'money_laundering', 'terrorism_financing', "
        f"'tax_evasion', 'sanctions_violation', 'environmental', 'other'); "
        f"EXCEPTION WHEN duplicate_object THEN NULL; END $$"
    )

    # 3. Create risk_level_enum
    op.execute(
        f"DO $$ BEGIN "
        f"CREATE TYPE {SCHEMA}.risk_level_enum AS ENUM "
        f"('low', 'medium', 'high', 'critical'); "
        f"EXCEPTION WHEN duplicate_object THEN NULL; END $$"
    )

    # 4. Create company_sanctions table (1:1 summary, company_id as PK+FK)
    op.execute(f"""
        CREATE TABLE {SCHEMA}.company_sanctions (
            company_id INTEGER NOT NULL REFERENCES {SCHEMA}.companies(id) ON DELETE CASCADE,
            insights TEXT,
            insights_source TEXT,
            overall_risk_level TEXT,
            overall_risk_justification TEXT,
            total_sanctions_count INTEGER DEFAULT 0,
            created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
            updated_at TIMESTAMPTZ,
            PRIMARY KEY (company_id)
        )
    """)

    # 5. Create company_sanction_items table (1:N items)
    op.execute(f"""
        CREATE TABLE {SCHEMA}.company_sanction_items (
            id SERIAL PRIMARY KEY,
            company_id INTEGER NOT NULL REFERENCES {SCHEMA}.companies(id) ON DELETE CASCADE,
            entity_name TEXT NOT NULL,
            country TEXT,
            sanction_nature TEXT,
            description TEXT,
            source_code TEXT,
            sanction_type {SCHEMA}.sanction_type_enum,
            date TEXT,
            weblinks TEXT[],
            is_onu_eu_ofac BOOLEAN NOT NULL DEFAULT FALSE,
            risk_level {SCHEMA}.risk_level_enum,
            risk_justification TEXT,
            created_at TIMESTAMPTZ NOT NULL DEFAULT now()
        )
    """)

    # 6. Create index on company_sanction_items.company_id
    op.execute(f"CREATE INDEX ix_company_sanction_items_company_id ON {SCHEMA}.company_sanction_items (company_id)")


def downgrade():
    # 1. Drop materialized views
    op.execute("DROP MATERIALIZED VIEW IF EXISTS public.task_type_cost_summary CASCADE")
    op.execute("DROP MATERIALIZED VIEW IF EXISTS public.organization_cost_summary CASCADE")

    # 2. Drop sanctions tables
    op.execute(f"DROP TABLE IF EXISTS {SCHEMA}.company_sanction_items CASCADE")
    op.execute(f"DROP TABLE IF EXISTS {SCHEMA}.company_sanctions CASCADE")

    # 3. Drop enums
    op.execute(f"DROP TYPE IF EXISTS {SCHEMA}.sanction_type_enum")
    op.execute(f"DROP TYPE IF EXISTS {SCHEMA}.risk_level_enum")

    # 4. Rebuild task_type_enum without 'sanctions'
    op.execute(f"DELETE FROM {SCHEMA}.tasks WHERE type = 'sanctions'")
    op.execute("ALTER TYPE screen_schema.task_type_enum RENAME TO task_type_enum_old")
    op.execute(
        "CREATE TYPE screen_schema.task_type_enum AS ENUM "
        "('profile', 'digital', 'timeline', 'products', 'jobs', 'csr', 'press', 'team', 'financial', 'corporate_structure')"
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
