"""add_cost_analysis_views

Revision ID: b54632488613
Revises: 51704b1cdfc0
Create Date: 2025-08-21 13:52:48.136114

"""
from alembic import op
import sqlalchemy as sa


# revision identifiers, used by Alembic.
revision = 'b54632488613'
down_revision = '51704b1cdfc0'
branch_labels = None
depends_on = None


def upgrade():
    # Create indexes for better performance on cost analysis queries
    op.create_index('ix_tasks_created_at', 'tasks', ['created_at'])
    op.create_index('ix_tasks_company_id', 'tasks', ['company_id'])
    op.create_index('ix_tasks_type', 'tasks', ['type'])
    op.create_index('ix_tasks_status', 'tasks', ['status'])
    op.create_index('ix_companies_workspace_id', 'companies', ['workspace_id'])
    
    # Create materialized view for cost analysis by workspace
    op.execute("""
        CREATE MATERIALIZED VIEW IF NOT EXISTS workspace_cost_summary AS
        SELECT 
            w.id as workspace_id,
            w.name as workspace_name,
            COUNT(DISTINCT c.id) as total_companies,
            COUNT(t.id) as total_tasks,
            COALESCE(SUM(t.input_tokens), 0) as total_input_tokens,
            COALESCE(SUM(t.output_tokens), 0) as total_output_tokens,
            COALESCE(SUM(t.total_cost), 0) as total_cost,
            DATE_TRUNC('day', t.created_at) as date
        FROM workspaces w
        LEFT JOIN companies c ON c.workspace_id = w.id
        LEFT JOIN tasks t ON t.company_id = c.id AND t.status = 'succeeded'
        WHERE t.created_at IS NOT NULL
        GROUP BY w.id, w.name, DATE_TRUNC('day', t.created_at)
    """)
    
    # Create index on materialized view
    op.execute("""
        CREATE INDEX IF NOT EXISTS idx_workspace_cost_summary_date 
        ON workspace_cost_summary(date)
    """)
    
    op.execute("""
        CREATE INDEX IF NOT EXISTS idx_workspace_cost_summary_workspace 
        ON workspace_cost_summary(workspace_id)
    """)
    
    # Create materialized view for cost analysis by task type
    op.execute("""
        CREATE MATERIALIZED VIEW IF NOT EXISTS task_type_cost_summary AS
        SELECT 
            t.type as task_type,
            COUNT(t.id) as task_count,
            COALESCE(SUM(t.input_tokens), 0) as total_input_tokens,
            COALESCE(SUM(t.output_tokens), 0) as total_output_tokens,
            COALESCE(SUM(t.total_cost), 0) as total_cost,
            COALESCE(AVG(t.total_cost), 0) as avg_cost_per_task,
            COALESCE(AVG(t.input_tokens), 0) as avg_input_tokens,
            COALESCE(AVG(t.output_tokens), 0) as avg_output_tokens,
            DATE_TRUNC('day', t.created_at) as date
        FROM tasks t
        WHERE t.status = 'succeeded' AND t.created_at IS NOT NULL
        GROUP BY t.type, DATE_TRUNC('day', t.created_at)
    """)
    
    # Create index on task type materialized view
    op.execute("""
        CREATE INDEX IF NOT EXISTS idx_task_type_cost_summary_date 
        ON task_type_cost_summary(date)
    """)
    
    op.execute("""
        CREATE INDEX IF NOT EXISTS idx_task_type_cost_summary_type 
        ON task_type_cost_summary(task_type)
    """)


def downgrade():
    # Drop materialized views
    op.execute("DROP MATERIALIZED VIEW IF EXISTS task_type_cost_summary")
    op.execute("DROP MATERIALIZED VIEW IF EXISTS workspace_cost_summary")
    
    # Drop indexes
    op.drop_index('ix_companies_workspace_id', 'companies')
    op.drop_index('ix_tasks_status', 'tasks')
    op.drop_index('ix_tasks_type', 'tasks')
    op.drop_index('ix_tasks_company_id', 'tasks')
    op.drop_index('ix_tasks_created_at', 'tasks') 