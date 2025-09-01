"""New initial migration with all current features

Revision ID: 001
Revises: 
Create Date: 2025-09-01 11:40:00.000000

"""
from alembic import op
import sqlalchemy as sa
from sqlalchemy.dialects import postgresql
from sqlalchemy.dialects.postgresql import UUID

# revision identifiers, used by Alembic.
revision = '001'
down_revision = None
branch_labels = None
depends_on = None

def upgrade():
    # Create enum types
    op.execute("CREATE TYPE workspacememberstatus AS ENUM ('active', 'revoked')")
    op.execute("CREATE TYPE task_type_enum AS ENUM ('profile', 'digital', 'timeline', 'products', 'jobs', 'csr', 'press', 'team')")
    op.execute("CREATE TYPE task_status_enum AS ENUM ('pending', 'running', 'succeeded', 'error')")
    op.execute("CREATE TYPE modulename AS ENUM ('screen', 'target', 'explore', 'stream')")
    
    # Create workspaces table
    op.create_table('workspaces',
        sa.Column('id', sa.Integer(), nullable=False),
        sa.Column('name', sa.String(), nullable=False),
        sa.Column('description', sa.Text(), nullable=True),
        sa.Column('slug', sa.String(), nullable=False),
        sa.Column('created_at', sa.DateTime(timezone=True), server_default=sa.func.now()),
        sa.Column('updated_at', sa.DateTime(timezone=True), nullable=True),
        sa.PrimaryKeyConstraint('id')
    )
    op.create_index('ix_workspaces_id', 'workspaces', ['id'])
    op.create_index('ix_workspaces_name', 'workspaces', ['name'])
    op.create_index('ix_workspaces_slug', 'workspaces', ['slug'], unique=True)
    
    # Create workspace_members table
    op.create_table('workspace_members',
        sa.Column('id', sa.Integer(), nullable=False),
        sa.Column('workspace_id', sa.Integer(), nullable=False),
        sa.Column('user_id', sa.String(), nullable=False),
        sa.Column('username', sa.String(), nullable=False),
        sa.Column('email', sa.String(), nullable=False),
        sa.Column('first_name', sa.String(), nullable=True),
        sa.Column('last_name', sa.String(), nullable=True),
        sa.Column('status', postgresql.ENUM('active', 'revoked', name='workspacememberstatus', create_type=False), nullable=True),
        sa.Column('created_at', sa.DateTime(timezone=True), server_default=sa.func.now()),
        sa.Column('updated_at', sa.DateTime(timezone=True), nullable=True),
        sa.ForeignKeyConstraint(['workspace_id'], ['workspaces.id'], ),
        sa.PrimaryKeyConstraint('id'),
        sa.UniqueConstraint('workspace_id', 'user_id', name='unique_workspace_user')
    )
    op.create_index('ix_workspace_members_id', 'workspace_members', ['id'])
    op.create_index('ix_workspace_members_username', 'workspace_members', ['username'])
    op.create_index('ix_workspace_members_email', 'workspace_members', ['email'])
    op.create_index('idx_workspace_members_workspace_id_status', 'workspace_members', ['workspace_id', 'status'])
    op.create_index('idx_workspace_members_user_id', 'workspace_members', ['user_id'])
    
    # NOTE: No users table - user references are handled via username strings only
    # Authentication is managed entirely by Keycloak
    
    # Create user_workspace_permissions table
    op.create_table(
        'user_workspace_permissions',
        sa.Column('id', sa.Integer, primary_key=True, index=True),
        sa.Column('user_id', sa.String, nullable=False, index=True),  # Keycloak user ID
        sa.Column('workspace_id', sa.Integer, sa.ForeignKey('workspaces.id'), nullable=True, index=True),  # NULL = global permission
        sa.Column('permission', sa.String, nullable=False, index=True),  # e.g., 'workspace.users.read'
        sa.Column('granted_by', sa.String, nullable=True),  # Who granted this permission
        sa.Column('created_at', sa.DateTime(timezone=True), server_default=sa.func.now()),
        sa.Column('updated_at', sa.DateTime(timezone=True), onupdate=sa.func.now()),
        
        # Unique constraint: user can't have same permission twice for same workspace
        sa.UniqueConstraint('user_id', 'workspace_id', 'permission', name='unique_user_workspace_permission')
    )
    op.create_index('idx_user_workspace_permissions_lookup', 'user_workspace_permissions', ['user_id', 'workspace_id', 'permission'])
    
    # Create companies table
    op.create_table('companies',
        sa.Column('id', sa.Integer(), nullable=False),
        sa.Column('name', sa.String(), nullable=False),
        sa.Column('website', sa.String(), nullable=False),
        sa.Column('created_at', sa.DateTime(), nullable=True),
        sa.Column('updated_at', sa.DateTime(), nullable=True),
        sa.Column('profile', postgresql.JSON(astext_type=sa.Text()), nullable=True),
        sa.Column('digital', postgresql.JSON(astext_type=sa.Text()), nullable=True),
        sa.Column('timeline', postgresql.JSON(astext_type=sa.Text()), nullable=True),
        sa.Column('products', postgresql.JSON(astext_type=sa.Text()), nullable=True),
        sa.Column('jobs', postgresql.JSON(astext_type=sa.Text()), nullable=True),
        sa.Column('csr', postgresql.JSON(astext_type=sa.Text()), nullable=True),
        sa.Column('press', postgresql.JSON(astext_type=sa.Text()), nullable=True),
        sa.Column('team', postgresql.JSON(astext_type=sa.Text()), nullable=True),
        sa.Column('error', sa.String(), nullable=True),
        sa.Column('owner_username', sa.String(), nullable=False, server_default='suh'),
        sa.Column('workspace_id', sa.Integer(), nullable=True),
        sa.Column('owner_id', sa.String(), nullable=True),
        sa.Column('created_by', sa.String(), nullable=True),
        sa.Column('is_deleted', sa.Boolean(), nullable=False, server_default='false'),
        sa.ForeignKeyConstraint(['workspace_id'], ['workspaces.id'], ),
        sa.PrimaryKeyConstraint('id')
    )
    op.create_index(op.f('ix_companies_id'), 'companies', ['id'], unique=False)
    op.create_index(op.f('ix_companies_name'), 'companies', ['name'], unique=False)
    op.create_index(op.f('ix_companies_website'), 'companies', ['website'], unique=False)
    op.create_index(op.f('ix_companies_owner_username'), 'companies', ['owner_username'], unique=False)
    op.create_index('ix_companies_owner_id', 'companies', ['owner_id'])
    op.create_index('ix_companies_created_by', 'companies', ['created_by'])
    op.create_index('ix_companies_is_deleted', 'companies', ['is_deleted'])
    op.create_index('ix_companies_workspace_id', 'companies', ['workspace_id'])

    # Create tasks table with token tracking
    op.create_table('tasks',
        sa.Column('id', sa.Integer(), nullable=False),
        sa.Column('company_id', sa.Integer(), nullable=False),
        sa.Column('type', postgresql.ENUM('profile', 'digital', 'timeline', 'products', 'jobs', 'csr', 'press', 'team', name='task_type_enum', create_type=False), nullable=False),
        sa.Column('status', postgresql.ENUM('pending', 'running', 'succeeded', 'error', name='task_status_enum', create_type=False), nullable=False),
        sa.Column('error', sa.String(), nullable=True),
        sa.Column('created_at', sa.DateTime(), nullable=True),
        sa.Column('updated_at', sa.DateTime(), nullable=True),
        sa.Column('input_tokens', sa.Integer(), nullable=True),
        sa.Column('output_tokens', sa.Integer(), nullable=True),
        sa.Column('total_cost', sa.Float(), nullable=True),
        sa.ForeignKeyConstraint(['company_id'], ['companies.id'], ),
        sa.PrimaryKeyConstraint('id')
    )
    op.create_index(op.f('ix_tasks_id'), 'tasks', ['id'], unique=False)
    op.create_index('ix_tasks_company_id', 'tasks', ['company_id'])
    op.create_index('ix_tasks_created_at', 'tasks', ['created_at'])
    op.create_index('ix_tasks_status', 'tasks', ['status'])
    op.create_index('ix_tasks_type', 'tasks', ['type'])
    
    # Create workspace_modules table
    op.create_table('workspace_modules',
        sa.Column('id', sa.Integer(), nullable=False),
        sa.Column('workspace_id', sa.Integer(), nullable=False),
        sa.Column('module_name', postgresql.ENUM('screen', 'target', 'explore', 'stream', name='modulename', create_type=False), nullable=False),
        sa.Column('enabled', sa.Boolean(), nullable=False),
        sa.Column('token_count', sa.Integer(), nullable=False),
        sa.Column('created_at', sa.DateTime(timezone=True), nullable=True, server_default=sa.func.now()),
        sa.Column('updated_at', sa.DateTime(timezone=True), nullable=True),
        sa.ForeignKeyConstraint(['workspace_id'], ['workspaces.id'], ),
        sa.PrimaryKeyConstraint('id'),
        sa.UniqueConstraint('workspace_id', 'module_name', name='uq_workspace_modules_workspace_module')
    )
    op.create_index('ix_workspace_modules_id', 'workspace_modules', ['id'])
    op.create_index('ix_workspace_modules_workspace_id', 'workspace_modules', ['workspace_id'])
    op.create_index('ix_workspace_modules_module_name', 'workspace_modules', ['module_name'])
    
    # Create workflow_configs table
    op.create_table('workflow_configs',
        sa.Column('id', sa.Integer(), nullable=False),
        sa.Column('task_type', sa.String(50), nullable=False),
        sa.Column('title', sa.String(100), nullable=False),
        sa.Column('workflow_id', sa.String(100), nullable=True),
        sa.Column('api_key', sa.String(200), nullable=True),
        sa.Column('llm', sa.String(20), nullable=False),
        sa.Column('created_at', sa.DateTime(), nullable=True, server_default=sa.func.now()),
        sa.Column('updated_at', sa.DateTime(), nullable=True, server_default=sa.func.now()),
        sa.PrimaryKeyConstraint('id'),
        sa.UniqueConstraint('task_type')
    )
    op.create_index('ix_workflow_configs_id', 'workflow_configs', ['id'])
    
    # Create folders table with username owner reference
    op.create_table('folders',
        sa.Column('id', postgresql.UUID(as_uuid=True), server_default=sa.text('gen_random_uuid()'), nullable=False),
        sa.Column('workspace_id', sa.Integer(), nullable=False),
        sa.Column('owner', sa.String(), nullable=True),
        sa.Column('name', sa.String(), nullable=False),
        sa.Column('color', sa.String(), nullable=True),
        sa.Column('icon', sa.String(), nullable=True),
        sa.Column('tags', postgresql.ARRAY(sa.String()), server_default='{}', nullable=False),
        sa.Column('is_favorite', sa.Boolean(), server_default='false', nullable=False),
        sa.Column('is_deleted', sa.Boolean(), server_default='false', nullable=False),
        sa.Column('created_at', sa.DateTime(timezone=True), server_default=sa.text('now()'), nullable=False),
        sa.Column('updated_at', sa.DateTime(timezone=True), server_default=sa.text('now()'), nullable=False),
        sa.ForeignKeyConstraint(['workspace_id'], ['workspaces.id'], ondelete='CASCADE'),
        sa.PrimaryKeyConstraint('id')
    )
    op.create_index('ix_folders_workspace_id', 'folders', ['workspace_id'], unique=False)
    op.create_index('ix_folders_owner', 'folders', ['owner'], unique=False)
    op.create_index('ix_folders_is_deleted', 'folders', ['is_deleted'], unique=False)
    
    # Create folder_items junction table with username owner reference
    op.create_table('folder_items',
        sa.Column('id', postgresql.UUID(as_uuid=True), server_default=sa.text('gen_random_uuid()'), nullable=False),
        sa.Column('folder_id', postgresql.UUID(as_uuid=True), nullable=False),
        sa.Column('item_id', sa.String(), nullable=False),  # Keep as String to support integer company IDs
        sa.Column('item_type', sa.String(), nullable=False),  # 'company', 'contact', etc.
        sa.Column('position', sa.Integer(), nullable=True),  # For ordering items in folder
        sa.Column('added_at', sa.DateTime(timezone=True), server_default=sa.text('now()'), nullable=False),
        sa.Column('owner', sa.String(), nullable=True),
        sa.ForeignKeyConstraint(['folder_id'], ['folders.id'], ondelete='CASCADE'),
        sa.PrimaryKeyConstraint('id'),
        sa.UniqueConstraint('folder_id', 'item_id', 'item_type', name='uq_folder_item')
    )
    op.create_index('ix_folder_items_folder_id', 'folder_items', ['folder_id'], unique=False)
    op.create_index('ix_folder_items_item_id', 'folder_items', ['item_id'], unique=False)
    op.create_index('ix_folder_items_item_type', 'folder_items', ['item_type'], unique=False)
    
    # Create cost analysis views
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
        CREATE MATERIALIZED VIEW workspace_cost_summary AS
        SELECT 
            c.workspace_id,
            w.name as workspace_name,
            COUNT(DISTINCT c.id) as total_companies,
            COUNT(t.id) as total_tasks,
            COUNT(CASE WHEN t.status = 'succeeded' THEN 1 END) as successful_tasks,
            COUNT(CASE WHEN t.status = 'error' THEN 1 END) as failed_tasks,
            SUM(CASE WHEN t.input_tokens IS NOT NULL THEN t.input_tokens ELSE 0 END) as total_input_tokens,
            SUM(CASE WHEN t.output_tokens IS NOT NULL THEN t.output_tokens ELSE 0 END) as total_output_tokens,
            SUM(CASE WHEN t.total_cost IS NOT NULL THEN t.total_cost ELSE 0 END) as total_cost,
            AVG(CASE WHEN t.total_cost IS NOT NULL THEN t.total_cost END) as avg_cost_per_task
        FROM companies c
        JOIN workspaces w ON c.workspace_id = w.id
        LEFT JOIN tasks t ON c.id = t.company_id
        WHERE c.workspace_id IS NOT NULL
        GROUP BY c.workspace_id, w.name
    """)

    # Create indexes for materialized views
    op.execute("CREATE INDEX idx_task_type_cost_summary_task_type ON task_type_cost_summary (task_type)")
    op.execute("CREATE INDEX idx_workspace_cost_summary_workspace_id ON workspace_cost_summary (workspace_id)")
    
    # Insert default ChapsVision workspace
    op.execute("""
        INSERT INTO workspaces (id, name, description, slug, created_at)
        VALUES (1, 'ChapsVision', 'Main ChapsVision workspace', 'chapsvision', NOW())
        ON CONFLICT DO NOTHING
    """)

def downgrade():
    # Drop materialized views
    op.execute('DROP MATERIALIZED VIEW IF EXISTS workspace_cost_summary CASCADE')
    op.execute('DROP MATERIALIZED VIEW IF EXISTS task_type_cost_summary CASCADE')
    
    # Drop folder_items table
    op.drop_index('ix_folder_items_item_type', table_name='folder_items')
    op.drop_index('ix_folder_items_item_id', table_name='folder_items')
    op.drop_index('ix_folder_items_folder_id', table_name='folder_items')
    op.drop_table('folder_items')
    
    # Drop folders table
    op.drop_index('ix_folders_is_deleted', table_name='folders')
    op.drop_index('ix_folders_owner', table_name='folders')
    op.drop_index('ix_folders_workspace_id', table_name='folders')
    op.drop_table('folders')
    
    # Drop workflow_configs table
    op.drop_index('ix_workflow_configs_id', table_name='workflow_configs')
    op.drop_table('workflow_configs')
    
    # Drop workspace_modules table
    op.drop_index('ix_workspace_modules_module_name', table_name='workspace_modules')
    op.drop_index('ix_workspace_modules_workspace_id', table_name='workspace_modules')
    op.drop_index('ix_workspace_modules_id', table_name='workspace_modules')
    op.drop_table('workspace_modules')
    
    # Drop user_workspace_permissions table
    op.drop_index('idx_user_workspace_permissions_lookup', 'user_workspace_permissions')
    op.drop_table('user_workspace_permissions')
    
    # NOTE: No users table to drop - user references are handled via username strings only
    
    # Drop tasks table
    op.drop_index('ix_tasks_type', table_name='tasks')
    op.drop_index('ix_tasks_status', table_name='tasks')
    op.drop_index('ix_tasks_created_at', table_name='tasks')
    op.drop_index('ix_tasks_company_id', table_name='tasks')
    op.drop_index(op.f('ix_tasks_id'), table_name='tasks')
    op.drop_table('tasks')

    # Drop companies table
    op.drop_index('ix_companies_workspace_id', table_name='companies')
    op.drop_index('ix_companies_is_deleted', table_name='companies')
    op.drop_index('ix_companies_created_by', table_name='companies')
    op.drop_index('ix_companies_owner_id', table_name='companies')
    op.drop_index(op.f('ix_companies_owner_username'), table_name='companies')
    op.drop_index(op.f('ix_companies_website'), table_name='companies')
    op.drop_index(op.f('ix_companies_name'), table_name='companies')
    op.drop_index(op.f('ix_companies_id'), table_name='companies')
    op.drop_table('companies')
    
    # Drop workspace_members table
    op.drop_index('idx_workspace_members_user_id', table_name='workspace_members')
    op.drop_index('idx_workspace_members_workspace_id_status', table_name='workspace_members')
    op.drop_index('ix_workspace_members_email', table_name='workspace_members')
    op.drop_index('ix_workspace_members_username', table_name='workspace_members')
    op.drop_index('ix_workspace_members_id', table_name='workspace_members')
    op.drop_table('workspace_members')
    
    # Drop workspaces table
    op.drop_index('ix_workspaces_slug', table_name='workspaces')
    op.drop_index('ix_workspaces_name', table_name='workspaces')
    op.drop_index('ix_workspaces_id', table_name='workspaces')
    op.drop_table('workspaces')
    
    # Drop enum types
    op.execute('DROP TYPE IF EXISTS modulename')
    op.execute('DROP TYPE IF EXISTS task_status_enum')
    op.execute('DROP TYPE IF EXISTS task_type_enum')
    op.execute('DROP TYPE IF EXISTS workspacememberstatus')