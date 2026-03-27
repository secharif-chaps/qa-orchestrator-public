"""convert datetime columns to timezone aware

Revision ID: 004
Revises: 003
Create Date: 2025-11-24

"""

from alembic import op

# revision identifiers, used by Alembic.
revision = "004"
down_revision = "003"
branch_labels = None
depends_on = None


def upgrade() -> None:
    """Convert datetime columns to timezone-aware (TIMESTAMPTZ).

    This migration converts all naive datetime columns to timezone-aware
    columns across companies and tasks tables. Existing naive timestamps
    are assumed to be UTC and converted accordingly.

    Note: Must drop and recreate materialized views that depend on altered columns.
    """

    # Step 1: Drop materialized views that depend on tasks.created_at
    op.execute("DROP MATERIALIZED VIEW IF EXISTS task_type_cost_summary CASCADE")
    op.execute("DROP MATERIALIZED VIEW IF EXISTS organization_cost_summary CASCADE")

    # Step 2: Convert companies table datetime columns
    op.execute("""
        ALTER TABLE companies
        ALTER COLUMN created_at TYPE TIMESTAMP WITH TIME ZONE
        USING created_at AT TIME ZONE 'UTC'
    """)

    op.execute("""
        ALTER TABLE companies
        ALTER COLUMN created_at SET DEFAULT NOW()
    """)

    op.execute("""
        ALTER TABLE companies
        ALTER COLUMN updated_at TYPE TIMESTAMP WITH TIME ZONE
        USING updated_at AT TIME ZONE 'UTC'
    """)

    op.execute("""
        ALTER TABLE companies
        ALTER COLUMN updated_at SET DEFAULT NOW()
    """)

    # Step 3: Convert tasks table datetime columns
    op.execute("""
        ALTER TABLE tasks
        ALTER COLUMN created_at TYPE TIMESTAMP WITH TIME ZONE
        USING created_at AT TIME ZONE 'UTC'
    """)

    op.execute("""
        ALTER TABLE tasks
        ALTER COLUMN created_at SET DEFAULT NOW()
    """)

    op.execute("""
        ALTER TABLE tasks
        ALTER COLUMN updated_at TYPE TIMESTAMP WITH TIME ZONE
        USING updated_at AT TIME ZONE 'UTC'
    """)

    op.execute("""
        ALTER TABLE tasks
        ALTER COLUMN updated_at SET DEFAULT NOW()
    """)

    # Convert tasks started_at and completed_at columns if they exist
    op.execute("""
        DO $$ BEGIN
            IF EXISTS (
                SELECT 1 FROM information_schema.columns
                WHERE table_name = 'tasks' AND column_name = 'started_at'
            ) THEN
                ALTER TABLE tasks
                ALTER COLUMN started_at TYPE TIMESTAMP WITH TIME ZONE
                USING started_at AT TIME ZONE 'UTC';
            END IF;
        END $$;
    """)

    op.execute("""
        DO $$ BEGIN
            IF EXISTS (
                SELECT 1 FROM information_schema.columns
                WHERE table_name = 'tasks' AND column_name = 'completed_at'
            ) THEN
                ALTER TABLE tasks
                ALTER COLUMN completed_at TYPE TIMESTAMP WITH TIME ZONE
                USING completed_at AT TIME ZONE 'UTC';
            END IF;
        END $$;
    """)

    # Step 4: Recreate materialized views with timezone-aware columns
    op.execute("""
        CREATE MATERIALIZED VIEW task_type_cost_summary AS
        SELECT
            t.type as task_type,
            COUNT(*) as total_tasks,
            COUNT(CASE WHEN t.status = 'succeeded' THEN 1 END) as successful_tasks,
            COUNT(CASE WHEN t.status = 'error' THEN 1 END) as failed_tasks,
            AVG(CASE WHEN t.input_tokens IS NOT NULL THEN t.input_tokens END) as avg_input_tokens,
            AVG(CASE WHEN t.output_tokens IS NOT NULL THEN t.output_tokens END) as avg_output_tokens,
            SUM(CASE WHEN t.total_cost IS NOT NULL THEN t.total_cost ELSE 0 END) as total_cost,
            AVG(CASE WHEN t.total_cost IS NOT NULL THEN t.total_cost END) as avg_cost_per_task,
            MIN(t.created_at) as first_task_date,
            MAX(t.created_at) as last_task_date
        FROM tasks t
        GROUP BY t.type
    """)

    op.execute("""
        CREATE MATERIALIZED VIEW organization_cost_summary AS
        SELECT
            c.organization_id,
            COUNT(DISTINCT c.id) as total_companies,
            COUNT(t.id) as total_tasks,
            COUNT(CASE WHEN t.status = 'succeeded' THEN 1 END) as successful_tasks,
            COUNT(CASE WHEN t.status = 'error' THEN 1 END) as failed_tasks,
            SUM(CASE WHEN t.total_cost IS NOT NULL THEN t.total_cost ELSE 0 END) as total_cost,
            AVG(CASE WHEN t.total_cost IS NOT NULL THEN t.total_cost END) as avg_cost_per_task,
            MIN(t.created_at) as first_task_date,
            MAX(t.created_at) as last_task_date
        FROM companies c
        LEFT JOIN tasks t ON c.id = t.company_id
        GROUP BY c.organization_id
    """)

    # Recreate indexes on materialized views
    op.execute("CREATE INDEX idx_task_type_cost_summary_task_type ON task_type_cost_summary (task_type)")
    op.execute("CREATE INDEX idx_organization_cost_summary_org_id ON organization_cost_summary (organization_id)")


def downgrade() -> None:
    """Convert timezone-aware datetime columns back to naive timestamps.

    Note: This will lose timezone information. All timestamps will be
    converted to their UTC equivalent as naive timestamps.
    """

    # Step 1: Drop materialized views
    op.execute("DROP MATERIALIZED VIEW IF EXISTS task_type_cost_summary CASCADE")
    op.execute("DROP MATERIALIZED VIEW IF EXISTS organization_cost_summary CASCADE")

    # Step 2: Convert companies table back to naive timestamps
    op.execute("""
        ALTER TABLE companies
        ALTER COLUMN created_at TYPE TIMESTAMP WITHOUT TIME ZONE
        USING created_at AT TIME ZONE 'UTC'
    """)

    op.execute("""
        ALTER TABLE companies
        ALTER COLUMN updated_at TYPE TIMESTAMP WITHOUT TIME ZONE
        USING updated_at AT TIME ZONE 'UTC'
    """)

    # Step 3: Convert tasks table back to naive timestamps
    op.execute("""
        ALTER TABLE tasks
        ALTER COLUMN created_at TYPE TIMESTAMP WITHOUT TIME ZONE
        USING created_at AT TIME ZONE 'UTC'
    """)

    op.execute("""
        ALTER TABLE tasks
        ALTER COLUMN updated_at TYPE TIMESTAMP WITHOUT TIME ZONE
        USING updated_at AT TIME ZONE 'UTC'
    """)

    # Convert tasks started_at and completed_at back if they exist
    op.execute("""
        DO $$ BEGIN
            IF EXISTS (
                SELECT 1 FROM information_schema.columns
                WHERE table_name = 'tasks' AND column_name = 'started_at'
            ) THEN
                ALTER TABLE tasks
                ALTER COLUMN started_at TYPE TIMESTAMP WITHOUT TIME ZONE
                USING started_at AT TIME ZONE 'UTC';
            END IF;
        END $$;
    """)

    op.execute("""
        DO $$ BEGIN
            IF EXISTS (
                SELECT 1 FROM information_schema.columns
                WHERE table_name = 'tasks' AND column_name = 'completed_at'
            ) THEN
                ALTER TABLE tasks
                ALTER COLUMN completed_at TYPE TIMESTAMP WITHOUT TIME ZONE
                USING completed_at AT TIME ZONE 'UTC';
            END IF;
        END $$;
    """)

    # Step 4: Recreate materialized views with naive timestamps
    op.execute("""
        CREATE MATERIALIZED VIEW task_type_cost_summary AS
        SELECT
            t.type as task_type,
            COUNT(*) as total_tasks,
            COUNT(CASE WHEN t.status = 'succeeded' THEN 1 END) as successful_tasks,
            COUNT(CASE WHEN t.status = 'error' THEN 1 END) as failed_tasks,
            AVG(CASE WHEN t.input_tokens IS NOT NULL THEN t.input_tokens END) as avg_input_tokens,
            AVG(CASE WHEN t.output_tokens IS NOT NULL THEN t.output_tokens END) as avg_output_tokens,
            SUM(CASE WHEN t.total_cost IS NOT NULL THEN t.total_cost ELSE 0 END) as total_cost,
            AVG(CASE WHEN t.total_cost IS NOT NULL THEN t.total_cost END) as avg_cost_per_task,
            MIN(t.created_at) as first_task_date,
            MAX(t.created_at) as last_task_date
        FROM tasks t
        GROUP BY t.type
    """)

    op.execute("""
        CREATE MATERIALIZED VIEW organization_cost_summary AS
        SELECT
            c.organization_id,
            COUNT(DISTINCT c.id) as total_companies,
            COUNT(t.id) as total_tasks,
            COUNT(CASE WHEN t.status = 'succeeded' THEN 1 END) as successful_tasks,
            COUNT(CASE WHEN t.status = 'error' THEN 1 END) as failed_tasks,
            SUM(CASE WHEN t.total_cost IS NOT NULL THEN t.total_cost ELSE 0 END) as total_cost,
            AVG(CASE WHEN t.total_cost IS NOT NULL THEN t.total_cost END) as avg_cost_per_task,
            MIN(t.created_at) as first_task_date,
            MAX(t.created_at) as last_task_date
        FROM companies c
        LEFT JOIN tasks t ON c.id = t.company_id
        GROUP BY c.organization_id
    """)

    # Recreate indexes on materialized views
    op.execute("CREATE INDEX idx_task_type_cost_summary_task_type ON task_type_cost_summary (task_type)")
    op.execute("CREATE INDEX idx_organization_cost_summary_org_id ON organization_cost_summary (organization_id)")
