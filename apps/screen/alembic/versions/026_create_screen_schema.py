"""Create screen_schema and move screen-related tables

Revision ID: 026
Revises: 025
Create Date: 2026-02-26

Moves all screen-module tables (companies, company_*, tasks, translations,
workflow_configs, chapse_conversation_context) from public schema to
screen_schema. Uses ALTER TABLE SET SCHEMA to preserve data, sequences,
indexes, and constraints.
"""

from alembic import op

# revision identifiers, used by Alembic.
revision = "026"
down_revision = "025"
branch_labels = None
depends_on = None

# Tables to move to screen_schema (ordered: parents before children)
TABLES = [
    # Core
    "companies",
    # 1:1 sections
    "company_profile",
    "company_digital",
    "company_timeline",
    "company_products",
    "company_jobs",
    "company_csr",
    "company_press",
    # 1:N children
    "company_online_services",
    "company_social_media_accounts",
    "company_timeline_events",
    "company_product_items",
    "company_product_categories",
    "company_job_offers",
    "company_csr_initiatives",
    "company_press_items",
    "company_team_members",
    # Tasks
    "tasks",
    "task_dependencies",
    # Workflow
    "workflow_configs",
    # Translations
    "translations",
    "translation_jobs",
    # Chapse
    "chapse_conversation_context",
]

# Enum types to move to screen_schema
ENUMS = [
    "task_type_enum",
    "task_status_enum",
    "product_item_type_enum",
    "csr_initiative_type_enum",
    "press_item_type_enum",
    "translation_job_status_enum",
]


def upgrade():
    op.execute("CREATE SCHEMA IF NOT EXISTS screen_schema")

    # Move enum types first (tables reference them)
    for enum_name in ENUMS:
        op.execute(f"ALTER TYPE public.{enum_name} SET SCHEMA screen_schema")

    # Move tables (parents before children due to FK dependencies)
    for table in TABLES:
        op.execute(f"ALTER TABLE public.{table} SET SCHEMA screen_schema")


def downgrade():
    # Move tables back (children before parents)
    for table in reversed(TABLES):
        op.execute(f"ALTER TABLE screen_schema.{table} SET SCHEMA public")

    # Move enum types back
    for enum_name in ENUMS:
        op.execute(f"ALTER TYPE screen_schema.{enum_name} SET SCHEMA public")

    op.execute("DROP SCHEMA IF EXISTS screen_schema")
