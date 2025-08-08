"""Initial migration with all tables

Revision ID: 001
Revises: 
Create Date: 2023-08-01 00:00:00.000000

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
    
    # Additional workspace member indexes (from 2a48646e2a67)
    op.create_index(
        'idx_workspace_members_workspace_id_status',
        'workspace_members',
        ['workspace_id', 'status']
    )
    op.create_index(
        'idx_workspace_members_user_id',
        'workspace_members',
        ['user_id']
    )
    
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
        sa.ForeignKeyConstraint(['workspace_id'], ['workspaces.id'], ),
        sa.PrimaryKeyConstraint('id')
    )
    op.create_index(op.f('ix_companies_id'), 'companies', ['id'], unique=False)
    op.create_index(op.f('ix_companies_name'), 'companies', ['name'], unique=False)
    op.create_index(op.f('ix_companies_website'), 'companies', ['website'], unique=False)
    op.create_index(op.f('ix_companies_owner_username'), 'companies', ['owner_username'], unique=False)
    op.create_index('ix_companies_owner_id', 'companies', ['owner_id'])
    op.create_index('ix_companies_created_by', 'companies', ['created_by'])

    # Create tasks table
    op.create_table('tasks',
        sa.Column('id', sa.Integer(), nullable=False),
        sa.Column('company_id', sa.Integer(), nullable=False),
        sa.Column('type', sa.Enum('profile', 'digital', 'timeline', 'products', 'jobs', 'csr', 'press', 'team', name='task_type_enum'), nullable=False),
        sa.Column('status', sa.Enum('pending', 'running', 'succeeded', 'error', name='task_status_enum'), nullable=False),
        sa.Column('error', sa.String(), nullable=True),
        sa.Column('created_at', sa.DateTime(), nullable=True),
        sa.Column('updated_at', sa.DateTime(), nullable=True),
        sa.ForeignKeyConstraint(['company_id'], ['companies.id'], ),
        sa.PrimaryKeyConstraint('id')
    )
    op.create_index(op.f('ix_tasks_id'), 'tasks', ['id'], unique=False)
    
    # Create users table
    op.create_table('users',
        sa.Column('id', UUID(as_uuid=True), primary_key=True),
        sa.Column('keycloak_id', sa.String(), nullable=False),
        sa.Column('username', sa.String(), nullable=False),
        sa.Column('email', sa.String(), nullable=False),
        sa.Column('first_name', sa.String(), nullable=True),
        sa.Column('last_name', sa.String(), nullable=True),
        sa.Column('is_active', sa.Boolean(), nullable=False, default=True),
        sa.Column('roles', sa.Text(), nullable=True),
        sa.Column('created_at', sa.DateTime(timezone=True), server_default=sa.func.now()),
        sa.Column('updated_at', sa.DateTime(timezone=True), nullable=True),
    )
    op.create_index('ix_users_keycloak_id', 'users', ['keycloak_id'], unique=True)
    op.create_index('ix_users_username', 'users', ['username'], unique=True)
    op.create_index('ix_users_email', 'users', ['email'], unique=True)
    
    # Create user_workspace_permissions table (from 2dd0b771bfc5)
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
    
    # Index for efficient permission lookups
    op.create_index(
        'idx_user_workspace_permissions_lookup',
        'user_workspace_permissions',
        ['user_id', 'workspace_id', 'permission']
    )
    
    # Create modulename enum type
    op.execute("CREATE TYPE modulename AS ENUM ('screen', 'target', 'explore', 'stream')")
    
    # Create workspace_modules table
    op.create_table('workspace_modules',
        sa.Column('id', sa.Integer(), nullable=False),
        sa.Column('workspace_id', sa.Integer(), nullable=False),
        sa.Column('module_name', postgresql.ENUM('screen', 'target', 'explore', 'stream', name='modulename', create_type=False), nullable=False),
        sa.Column('enabled', sa.Boolean(), nullable=False, default=False),
        sa.Column('token_count', sa.Integer(), nullable=False, default=0),
        sa.Column('created_at', sa.DateTime(timezone=True), server_default=sa.func.now()),
        sa.Column('updated_at', sa.DateTime(timezone=True), onupdate=sa.func.now()),
        sa.ForeignKeyConstraint(['workspace_id'], ['workspaces.id'], ),
        sa.PrimaryKeyConstraint('id'),
        sa.UniqueConstraint('workspace_id', 'module_name', name='uq_workspace_modules_workspace_module')
    )
    op.create_index('ix_workspace_modules_id', 'workspace_modules', ['id'])
    
    # Insert default ChapsVision workspace
    op.execute("""
        INSERT INTO workspaces (id, name, description, slug, created_at)
        VALUES (1, 'ChapsVision', 'Main ChapsVision workspace', 'chapsvision', NOW())
    """)
    
    # Set all existing companies to belong to ChapsVision workspace
    op.execute("UPDATE companies SET workspace_id = 1")


def downgrade():
    # Drop workspace_modules table
    op.drop_index('ix_workspace_modules_id', table_name='workspace_modules')
    op.drop_table('workspace_modules')
    
    # Drop modulename enum type
    op.execute('DROP TYPE IF EXISTS modulename')
    
    # Drop user_workspace_permissions table
    op.drop_index('idx_user_workspace_permissions_lookup', 'user_workspace_permissions')
    op.drop_table('user_workspace_permissions')
    
    # Drop users table
    op.drop_index('ix_users_email', table_name='users')
    op.drop_index('ix_users_username', table_name='users')
    op.drop_index('ix_users_keycloak_id', table_name='users')
    op.drop_table('users')
    
    # Drop tasks table
    op.drop_index(op.f('ix_tasks_id'), table_name='tasks')
    op.drop_table('tasks')

    # Drop enum types
    op.execute('DROP TYPE task_type_enum')
    op.execute('DROP TYPE task_status_enum')

    # Drop companies table
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
    
    # Drop enum type
    op.execute('DROP TYPE IF EXISTS workspacememberstatus')