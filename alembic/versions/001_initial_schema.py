"""Initial database schema with Keycloak Organizations

Revision ID: 001_initial_schema
Revises:
Create Date: 2025-11-14

This is a consolidated initial migration combining all schema changes:
- Base tables (companies, tasks, folders, etc.)
- Keycloak organization-based multi-tenancy
- User preferences with JSONB storage
- Task dependencies and blocked status
- Organization modules (token-based feature gating)
- Workflow configurations
- Cost analysis materialized views
"""
from alembic import op
import sqlalchemy as sa
from sqlalchemy.dialects import postgresql

# revision identifiers, used by Alembic.
revision = '001_initial_schema'
down_revision = None
branch_labels = None
depends_on = None


def upgrade():
    """Create complete initial database schema"""

    # ============================================================================
    # 1. CREATE ENUM TYPES
    # ============================================================================
    print("Creating enum types...")
    op.execute("CREATE TYPE task_type_enum AS ENUM ('profile', 'digital', 'timeline', 'products', 'jobs', 'csr', 'press', 'team', 'data_collection')")
    op.execute("CREATE TYPE task_status_enum AS ENUM ('pending', 'blocked', 'running', 'succeeded', 'error')")
    op.execute("CREATE TYPE modulename AS ENUM ('screen', 'target', 'explore')")

    # ============================================================================
    # 2. CREATE COMPANIES TABLE
    # ============================================================================
    print("Creating companies table...")
    op.create_table('companies',
        sa.Column('id', sa.Integer(), nullable=False),
        sa.Column('name', sa.String(), nullable=False),
        sa.Column('website', sa.String(), nullable=False),

        # Organization-based multi-tenancy (Keycloak)
        sa.Column('organization_id', sa.String(), nullable=False),

        # Owner fields - Keycloak user identification
        sa.Column('owner_id', sa.String(), nullable=True),  # Keycloak user UUID
        sa.Column('owner_username', sa.String(), nullable=True),  # Username for display

        # Timestamps
        sa.Column('created_at', sa.DateTime(), nullable=True),
        sa.Column('updated_at', sa.DateTime(), nullable=True),

        # Soft delete
        sa.Column('is_deleted', sa.Boolean(), nullable=False, server_default='false'),

        # JSON fields for task results
        sa.Column('profile', postgresql.JSON(astext_type=sa.Text()), nullable=True),
        sa.Column('digital', postgresql.JSON(astext_type=sa.Text()), nullable=True),
        sa.Column('timeline', postgresql.JSON(astext_type=sa.Text()), nullable=True),
        sa.Column('products', postgresql.JSON(astext_type=sa.Text()), nullable=True),
        sa.Column('jobs', postgresql.JSON(astext_type=sa.Text()), nullable=True),
        sa.Column('csr', postgresql.JSON(astext_type=sa.Text()), nullable=True),
        sa.Column('press', postgresql.JSON(astext_type=sa.Text()), nullable=True),
        sa.Column('team', postgresql.JSON(astext_type=sa.Text()), nullable=True),

        # Raw knowledge fields from data_collection task (migration 003)
        sa.Column('raw_mistral_knowledge', sa.String(), nullable=True),
        sa.Column('raw_claude_knowledge', sa.String(), nullable=True),
        sa.Column('raw_wikipedia_knowledge', sa.String(), nullable=True),
        sa.Column('raw_scraped_website_knowledge', sa.String(), nullable=True),

        # Error field
        sa.Column('error', sa.String(), nullable=True),

        sa.PrimaryKeyConstraint('id')
    )

    # Create indexes for companies
    op.create_index(op.f('ix_companies_id'), 'companies', ['id'], unique=False)
    op.create_index(op.f('ix_companies_name'), 'companies', ['name'], unique=False)
    op.create_index(op.f('ix_companies_website'), 'companies', ['website'], unique=False)
    op.create_index(op.f('ix_companies_organization_id'), 'companies', ['organization_id'], unique=False)
    op.create_index(op.f('ix_companies_owner_id'), 'companies', ['owner_id'], unique=False)
    op.create_index(op.f('ix_companies_owner_username'), 'companies', ['owner_username'], unique=False)
    op.create_index('ix_companies_is_deleted', 'companies', ['is_deleted'])

    # ============================================================================
    # 3. CREATE TASKS TABLE WITH DEPENDENCIES
    # ============================================================================
    print("Creating tasks table...")
    op.create_table('tasks',
        sa.Column('id', sa.Integer(), nullable=False),
        sa.Column('company_id', sa.Integer(), nullable=False),

        # Organization-based multi-tenancy
        sa.Column('organization_id', sa.String(), nullable=True),

        sa.Column('type', postgresql.ENUM('profile', 'digital', 'timeline', 'products', 'jobs', 'csr', 'press', 'team', 'data_collection', name='task_type_enum', create_type=False), nullable=False),
        sa.Column('status', postgresql.ENUM('pending', 'blocked', 'running', 'succeeded', 'error', name='task_status_enum', create_type=False), nullable=False),
        sa.Column('error', sa.String(), nullable=True),
        sa.Column('created_at', sa.DateTime(), nullable=True),
        sa.Column('updated_at', sa.DateTime(), nullable=True),

        # Token usage tracking
        sa.Column('input_tokens', sa.Integer(), nullable=True),
        sa.Column('output_tokens', sa.Integer(), nullable=True),
        sa.Column('total_cost', sa.Float(), nullable=True),

        # Prerequisite flag (migration d6d3b27e531e)
        sa.Column('is_prerequisite', sa.Boolean(), default=False, nullable=False),

        sa.ForeignKeyConstraint(['company_id'], ['companies.id'], ),
        sa.PrimaryKeyConstraint('id')
    )

    # Create indexes for tasks
    op.create_index(op.f('ix_tasks_id'), 'tasks', ['id'], unique=False)
    op.create_index('ix_tasks_company_id', 'tasks', ['company_id'])
    op.create_index(op.f('ix_tasks_organization_id'), 'tasks', ['organization_id'], unique=False)
    op.create_index('ix_tasks_created_at', 'tasks', ['created_at'])
    op.create_index('ix_tasks_status', 'tasks', ['status'])
    op.create_index('ix_tasks_type', 'tasks', ['type'])
    op.create_index('idx_tasks_is_prerequisite', 'tasks', ['is_prerequisite'])

    # ============================================================================
    # 4. CREATE TASK DEPENDENCIES TABLE (migration d6d3b27e531e)
    # ============================================================================
    print("Creating task_dependencies table...")
    op.create_table('task_dependencies',
        sa.Column('id', sa.Integer(), nullable=False),
        sa.Column('task_id', sa.Integer(), nullable=False),
        sa.Column('depends_on_task_id', sa.Integer(), nullable=False),
        sa.Column('created_at', sa.DateTime(), nullable=False, server_default=sa.func.now()),
        sa.ForeignKeyConstraint(['task_id'], ['tasks.id'], ondelete='CASCADE'),
        sa.ForeignKeyConstraint(['depends_on_task_id'], ['tasks.id'], ondelete='CASCADE'),
        sa.PrimaryKeyConstraint('id'),
        sa.UniqueConstraint('task_id', 'depends_on_task_id', name='uq_task_dependency')
    )

    # Create indexes for task_dependencies
    op.create_index('idx_task_dependencies_task_id', 'task_dependencies', ['task_id'])
    op.create_index('idx_task_dependencies_depends_on_task_id', 'task_dependencies', ['depends_on_task_id'])

    # ============================================================================
    # 5. CREATE ORGANIZATION_MODULES TABLE (migration 005)
    # ============================================================================
    print("Creating organization_modules table...")
    op.create_table('organization_modules',
        sa.Column('id', sa.Integer(), nullable=False),
        sa.Column('organization_id', sa.String(), nullable=False),
        sa.Column('module_name', postgresql.ENUM('screen', 'target', 'explore', name='modulename', create_type=False), nullable=False),
        sa.Column('enabled', sa.Boolean(), nullable=False),
        sa.Column('token_count', sa.Integer(), nullable=False),
        sa.Column('created_at', sa.DateTime(timezone=True), nullable=True, server_default=sa.func.now()),
        sa.Column('updated_at', sa.DateTime(timezone=True), nullable=True),
        sa.PrimaryKeyConstraint('id'),
        sa.UniqueConstraint('organization_id', 'module_name', name='uq_organization_modules_organization_module')
    )

    # Create indexes for organization_modules
    op.create_index('ix_organization_modules_id', 'organization_modules', ['id'])
    op.create_index('ix_organization_modules_organization_id', 'organization_modules', ['organization_id'])
    op.create_index('ix_organization_modules_module_name', 'organization_modules', ['module_name'])

    # ============================================================================
    # 6. CREATE WORKFLOW_CONFIGS TABLE
    # ============================================================================
    print("Creating workflow_configs table...")
    op.create_table('workflow_configs',
        sa.Column('id', sa.Integer(), nullable=False),
        sa.Column('task_type', sa.String(50), nullable=False),
        sa.Column('title', sa.String(100), nullable=False),
        sa.Column('api_key', sa.String(200), nullable=True),
        sa.Column('llm', sa.String(20), nullable=False),
        sa.Column('created_at', sa.DateTime(), nullable=True, server_default=sa.func.now()),
        sa.Column('updated_at', sa.DateTime(), nullable=True, server_default=sa.func.now()),
        sa.PrimaryKeyConstraint('id'),
        sa.UniqueConstraint('task_type')
    )
    op.create_index('ix_workflow_configs_id', 'workflow_configs', ['id'])

    # ============================================================================
    # 7. CREATE FOLDERS TABLE
    # ============================================================================
    print("Creating folders table...")
    op.create_table('folders',
        sa.Column('id', postgresql.UUID(as_uuid=True), server_default=sa.text('gen_random_uuid()'), nullable=False),

        # Organization-based multi-tenancy
        sa.Column('organization_id', sa.String(), nullable=False),

        # Owner fields - Keycloak user identification
        sa.Column('owner_id', sa.String(), nullable=True),  # Keycloak user UUID
        sa.Column('owner', sa.String(), nullable=True),  # Username for display (backward compat)

        sa.Column('name', sa.String(), nullable=False),
        sa.Column('color', sa.String(), nullable=True),
        sa.Column('icon', sa.String(), nullable=True),
        sa.Column('tags', postgresql.ARRAY(sa.String()), server_default='{}', nullable=False),
        sa.Column('is_favorite', sa.Boolean(), server_default='false', nullable=False),
        sa.Column('is_deleted', sa.Boolean(), server_default='false', nullable=False),
        sa.Column('created_at', sa.DateTime(timezone=True), server_default=sa.text('now()'), nullable=False),
        sa.Column('updated_at', sa.DateTime(timezone=True), server_default=sa.text('now()'), nullable=False),
        sa.PrimaryKeyConstraint('id')
    )

    # Create indexes for folders
    op.create_index(op.f('ix_folders_organization_id'), 'folders', ['organization_id'], unique=False)
    op.create_index(op.f('ix_folders_owner_id'), 'folders', ['owner_id'], unique=False)
    op.create_index('ix_folders_owner', 'folders', ['owner'], unique=False)
    op.create_index('ix_folders_is_deleted', 'folders', ['is_deleted'], unique=False)

    # ============================================================================
    # 8. CREATE FOLDER_ITEMS TABLE
    # ============================================================================
    print("Creating folder_items table...")
    op.create_table('folder_items',
        sa.Column('id', postgresql.UUID(as_uuid=True), server_default=sa.text('gen_random_uuid()'), nullable=False),
        sa.Column('folder_id', postgresql.UUID(as_uuid=True), nullable=False),
        sa.Column('item_id', sa.String(), nullable=False),
        sa.Column('item_type', sa.String(), nullable=False),
        sa.Column('position', sa.Integer(), nullable=True),
        sa.Column('added_at', sa.DateTime(timezone=True), server_default=sa.text('now()'), nullable=False),
        sa.Column('owner', sa.String(), nullable=True),
        sa.ForeignKeyConstraint(['folder_id'], ['folders.id'], ondelete='CASCADE'),
        sa.PrimaryKeyConstraint('id'),
        sa.UniqueConstraint('folder_id', 'item_id', 'item_type', name='uq_folder_item')
    )

    # Create indexes for folder_items
    op.create_index('ix_folder_items_folder_id', 'folder_items', ['folder_id'], unique=False)
    op.create_index('ix_folder_items_item_id', 'folder_items', ['item_id'], unique=False)
    op.create_index('ix_folder_items_item_type', 'folder_items', ['item_type'], unique=False)

    # ============================================================================
    # 9. CREATE USER_PREFERENCES TABLE (migration 002)
    # ============================================================================
    print("Creating user_preferences table...")
    op.create_table(
        'user_preferences',
        sa.Column('id', sa.Integer(), nullable=False),
        sa.Column('keycloak_user_id', sa.String(length=255), nullable=False),
        sa.Column('preferences', postgresql.JSONB, nullable=False, server_default='{}'),
        sa.Column('created_at', sa.DateTime(timezone=True), nullable=False, server_default=sa.func.now()),
        sa.Column('updated_at', sa.DateTime(timezone=True), nullable=False, server_default=sa.func.now()),
        sa.PrimaryKeyConstraint('id'),
        sa.UniqueConstraint('keycloak_user_id', name='uq_user_preferences_keycloak_user_id')
    )

    # Create indexes for user_preferences
    op.create_index('ix_user_preferences_id', 'user_preferences', ['id'])
    op.create_index('ix_user_preferences_keycloak_user_id', 'user_preferences', ['keycloak_user_id'], unique=True)
    op.create_index(
        'idx_user_preferences_jsonb',
        'user_preferences',
        ['preferences'],
        unique=False,
        postgresql_using='gin'
    )
    op.create_index(
        'idx_user_preferences_updated_at',
        'user_preferences',
        ['updated_at'],
        unique=False
    )

    # Create trigger function for auto-updating updated_at
    op.execute("""
        CREATE OR REPLACE FUNCTION update_user_preferences_updated_at()
        RETURNS TRIGGER AS $$
        BEGIN
            NEW.updated_at = CURRENT_TIMESTAMP;
            RETURN NEW;
        END;
        $$ LANGUAGE plpgsql;
    """)

    # Create trigger
    op.execute("""
        CREATE TRIGGER trigger_update_user_preferences_updated_at
            BEFORE UPDATE ON user_preferences
            FOR EACH ROW
            EXECUTE FUNCTION update_user_preferences_updated_at();
    """)

    # ============================================================================
    # 10. CREATE COST ANALYSIS MATERIALIZED VIEWS
    # ============================================================================
    print("Creating cost analysis materialized views...")
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
            SUM(CASE WHEN t.input_tokens IS NOT NULL THEN t.input_tokens ELSE 0 END) as total_input_tokens,
            SUM(CASE WHEN t.output_tokens IS NOT NULL THEN t.output_tokens ELSE 0 END) as total_output_tokens,
            SUM(CASE WHEN t.total_cost IS NOT NULL THEN t.total_cost ELSE 0 END) as total_cost,
            AVG(CASE WHEN t.total_cost IS NOT NULL THEN t.total_cost END) as avg_cost_per_task
        FROM companies c
        LEFT JOIN tasks t ON c.id = t.company_id
        WHERE c.organization_id IS NOT NULL
        GROUP BY c.organization_id
    """)

    # Create indexes for materialized views
    op.execute("CREATE INDEX idx_task_type_cost_summary_task_type ON task_type_cost_summary (task_type)")
    op.execute("CREATE INDEX idx_organization_cost_summary_organization_id ON organization_cost_summary (organization_id)")

    # ============================================================================
    # 11. INSERT SEED DATA
    # ============================================================================
    print("Inserting seed data...")

    # Insert workflow configs for all 9 task types (8 original + data_collection from migration 003)
    op.execute("""
        INSERT INTO workflow_configs (task_type, title, api_key, llm, created_at, updated_at) VALUES
        ('profile', 'Company Profile Analysis', NULL, 'mistral', NOW(), NOW()),
        ('digital', 'Digital Presence Analysis', NULL, 'mistral', NOW(), NOW()),
        ('timeline', 'Company Timeline Analysis', NULL, 'mistral', NOW(), NOW()),
        ('products', 'Products & Services Analysis', NULL, 'mistral', NOW(), NOW()),
        ('jobs', 'Jobs & Careers Analysis', NULL, 'mistral', NOW(), NOW()),
        ('csr', 'CSR & Sustainability Analysis', NULL, 'mistral', NOW(), NOW()),
        ('press', 'Press & Media Analysis', NULL, 'mistral', NOW(), NOW()),
        ('team', 'Team & Leadership Analysis', NULL, 'mistral', NOW(), NOW()),
        ('data_collection', 'Data Collection', 'app-qSdKHTLoR0WiESMRcVBKSzlI', 'mistral', NOW(), NOW())
        ON CONFLICT (task_type) DO NOTHING
    """)

    print("✅ Initial schema migration complete!")


def downgrade():
    """Drop all tables and types"""

    print("Dropping all tables and types...")

    # Drop trigger and function
    op.execute('DROP TRIGGER IF EXISTS trigger_update_user_preferences_updated_at ON user_preferences;')
    op.execute('DROP FUNCTION IF EXISTS update_user_preferences_updated_at();')

    # Drop materialized views
    op.execute('DROP MATERIALIZED VIEW IF EXISTS organization_cost_summary CASCADE')
    op.execute('DROP MATERIALIZED VIEW IF EXISTS task_type_cost_summary CASCADE')

    # Drop indexes
    op.drop_index('idx_user_preferences_updated_at', table_name='user_preferences')
    op.drop_index('idx_user_preferences_jsonb', table_name='user_preferences')
    op.drop_index('ix_user_preferences_keycloak_user_id', table_name='user_preferences')
    op.drop_index('ix_user_preferences_id', table_name='user_preferences')

    # Drop tables in reverse order of creation
    op.drop_table('user_preferences')

    op.drop_index('ix_folder_items_item_type', table_name='folder_items')
    op.drop_index('ix_folder_items_item_id', table_name='folder_items')
    op.drop_index('ix_folder_items_folder_id', table_name='folder_items')
    op.drop_table('folder_items')

    op.drop_index('ix_folders_is_deleted', table_name='folders')
    op.drop_index('ix_folders_owner', table_name='folders')
    op.drop_index(op.f('ix_folders_owner_id'), table_name='folders')
    op.drop_index(op.f('ix_folders_organization_id'), table_name='folders')
    op.drop_table('folders')

    op.drop_index('ix_workflow_configs_id', table_name='workflow_configs')
    op.drop_table('workflow_configs')

    op.drop_index('ix_organization_modules_module_name', table_name='organization_modules')
    op.drop_index('ix_organization_modules_organization_id', table_name='organization_modules')
    op.drop_index('ix_organization_modules_id', table_name='organization_modules')
    op.drop_table('organization_modules')

    op.drop_index('idx_task_dependencies_depends_on_task_id', table_name='task_dependencies')
    op.drop_index('idx_task_dependencies_task_id', table_name='task_dependencies')
    op.drop_table('task_dependencies')

    op.drop_index('idx_tasks_is_prerequisite', table_name='tasks')
    op.drop_index('ix_tasks_type', table_name='tasks')
    op.drop_index('ix_tasks_status', table_name='tasks')
    op.drop_index('ix_tasks_created_at', table_name='tasks')
    op.drop_index(op.f('ix_tasks_organization_id'), table_name='tasks')
    op.drop_index('ix_tasks_company_id', table_name='tasks')
    op.drop_index(op.f('ix_tasks_id'), table_name='tasks')
    op.drop_table('tasks')

    op.drop_index('ix_companies_is_deleted', table_name='companies')
    op.drop_index(op.f('ix_companies_owner_username'), table_name='companies')
    op.drop_index(op.f('ix_companies_owner_id'), table_name='companies')
    op.drop_index(op.f('ix_companies_organization_id'), table_name='companies')
    op.drop_index(op.f('ix_companies_website'), table_name='companies')
    op.drop_index(op.f('ix_companies_name'), table_name='companies')
    op.drop_index(op.f('ix_companies_id'), table_name='companies')
    op.drop_table('companies')

    # Drop enum types
    op.execute('DROP TYPE IF EXISTS modulename')
    op.execute('DROP TYPE IF EXISTS task_status_enum')
    op.execute('DROP TYPE IF EXISTS task_type_enum')

    print("✅ Downgrade complete!")
