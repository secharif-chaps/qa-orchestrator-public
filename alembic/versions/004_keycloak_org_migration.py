"""Migrate from workspace-based to Keycloak organization-based multi-tenancy

Revision ID: 004_keycloak_org_migration
Revises: 003_add_data_collection_task
Create Date: 2025-11-13

This migration handles the complete migration from database-managed workspaces
to Keycloak Organizations for multi-tenancy management.

Changes:
1. Truncate all data tables (companies, folders, folder_items, tasks)
2. Add organization_id (UUID) columns to companies, folders, tasks
3. Update owner fields from username strings to UUIDs
4. Drop workspace-related tables
5. Remove workspace foreign keys

IMPORTANT: This migration TRUNCATES ALL DATA. Only run in development/testing.
"""
from alembic import op
import sqlalchemy as sa
from sqlalchemy.dialects import postgresql

# revision identifiers, used by Alembic.
revision = '004_keycloak_org_migration'
down_revision = '003'
branch_labels = None
depends_on = None


def upgrade():
    """Upgrade database schema to use Keycloak Organizations"""

    # Step 1: Truncate all data tables (no production data to preserve)
    print("Truncating data tables...")
    op.execute("TRUNCATE TABLE tasks CASCADE")
    op.execute("TRUNCATE TABLE folder_items CASCADE")
    op.execute("TRUNCATE TABLE folders CASCADE")
    op.execute("TRUNCATE TABLE companies CASCADE")

    # Step 2: Drop workspace foreign key from companies
    print("Dropping workspace foreign key from companies...")
    op.drop_constraint('companies_workspace_id_fkey', 'companies', type_='foreignkey')

    # Step 3: Add organization_id to companies table
    print("Adding organization_id to companies...")
    op.add_column('companies', sa.Column('organization_id', sa.String(), nullable=True))
    op.create_index(op.f('ix_companies_organization_id'), 'companies', ['organization_id'], unique=False)

    # Make organization_id NOT NULL after adding index
    op.alter_column('companies', 'organization_id', nullable=False)

    # Step 4: Add owner_id (UUID) to companies, keep owner_username for now
    print("Adding owner_id to companies...")
    op.add_column('companies', sa.Column('owner_id', sa.String(), nullable=True))
    op.create_index(op.f('ix_companies_owner_id'), 'companies', ['owner_id'], unique=False)

    # Step 5: Drop old workspace_id column from companies
    print("Dropping workspace_id from companies...")
    op.drop_index('ix_companies_workspace_id', table_name='companies')
    op.drop_column('companies', 'workspace_id')

    # Step 6: Drop workspace foreign key from folders
    print("Dropping workspace foreign key from folders...")
    op.drop_constraint('folders_workspace_id_fkey', 'folders', type_='foreignkey')

    # Step 7: Add organization_id to folders table
    print("Adding organization_id to folders...")
    op.add_column('folders', sa.Column('organization_id', sa.String(), nullable=True))
    op.create_index(op.f('ix_folders_organization_id'), 'folders', ['organization_id'], unique=False)

    # Make organization_id NOT NULL
    op.alter_column('folders', 'organization_id', nullable=False)

    # Step 8: Update folders owner field from string to UUID
    print("Updating folders owner field...")
    op.add_column('folders', sa.Column('owner_id', sa.String(), nullable=True))
    op.create_index(op.f('ix_folders_owner_id'), 'folders', ['owner_id'], unique=False)

    # Step 9: Drop old workspace_id and owner columns from folders
    print("Dropping old columns from folders...")
    op.drop_index('ix_folders_workspace_id', table_name='folders')
    op.drop_column('folders', 'workspace_id')
    # Keep old owner column for now (may be used in folder_items)

    # Step 10: Add organization_id to tasks table
    print("Adding organization_id to tasks...")
    op.add_column('tasks', sa.Column('organization_id', sa.String(), nullable=True))
    op.create_index(op.f('ix_tasks_organization_id'), 'tasks', ['organization_id'], unique=False)

    # Step 11: Drop workspace tables
    print("Dropping workspace tables...")
    op.drop_table('user_workspace_permissions')
    op.drop_table('workspace_members')
    op.drop_table('workspace_modules')
    op.drop_table('workspaces')

    print("Migration complete! Database schema updated for Keycloak Organizations.")


def downgrade():
    """Downgrade to workspace-based schema (NOT RECOMMENDED)"""

    print("WARNING: Downgrading will recreate workspace tables but data will be lost!")

    # Recreate workspaces table
    op.create_table('workspaces',
        sa.Column('id', sa.Integer(), nullable=False),
        sa.Column('name', sa.String(), nullable=False),
        sa.Column('slug', sa.String(), nullable=False),
        sa.Column('created_at', sa.DateTime(), nullable=True),
        sa.Column('updated_at', sa.DateTime(), nullable=True),
        sa.PrimaryKeyConstraint('id')
    )
    op.create_index(op.f('ix_workspaces_slug'), 'workspaces', ['slug'], unique=True)

    # Recreate workspace_members table
    op.create_table('workspace_members',
        sa.Column('id', sa.Integer(), nullable=False),
        sa.Column('workspace_id', sa.Integer(), nullable=False),
        sa.Column('username', sa.String(), nullable=False),
        sa.Column('role', sa.String(), nullable=False),
        sa.Column('created_at', sa.DateTime(), nullable=True),
        sa.ForeignKeyConstraint(['workspace_id'], ['workspaces.id'], ),
        sa.PrimaryKeyConstraint('id')
    )

    # Recreate workspace_modules table
    op.create_table('workspace_modules',
        sa.Column('id', sa.Integer(), nullable=False),
        sa.Column('workspace_id', sa.Integer(), nullable=False),
        sa.Column('module_name', sa.String(), nullable=False),
        sa.Column('enabled', sa.Boolean(), nullable=False),
        sa.Column('token_count', sa.Integer(), nullable=False),
        sa.Column('created_at', sa.DateTime(), nullable=True),
        sa.Column('updated_at', sa.DateTime(), nullable=True),
        sa.ForeignKeyConstraint(['workspace_id'], ['workspaces.id'], ),
        sa.PrimaryKeyConstraint('id')
    )

    # Recreate user_workspace_permissions table
    op.create_table('user_workspace_permissions',
        sa.Column('id', sa.Integer(), nullable=False),
        sa.Column('workspace_id', sa.Integer(), nullable=False),
        sa.Column('username', sa.String(), nullable=False),
        sa.Column('permission', sa.String(), nullable=False),
        sa.Column('created_at', sa.DateTime(), nullable=True),
        sa.ForeignKeyConstraint(['workspace_id'], ['workspaces.id'], ),
        sa.PrimaryKeyConstraint('id')
    )

    # Remove organization_id from tasks
    op.drop_index(op.f('ix_tasks_organization_id'), table_name='tasks')
    op.drop_column('tasks', 'organization_id')

    # Restore folders
    op.add_column('folders', sa.Column('workspace_id', sa.Integer(), nullable=True))
    op.create_index('ix_folders_workspace_id', 'folders', ['workspace_id'], unique=False)
    op.drop_index(op.f('ix_folders_owner_id'), table_name='folders')
    op.drop_column('folders', 'owner_id')
    op.drop_index(op.f('ix_folders_organization_id'), table_name='folders')
    op.drop_column('folders', 'organization_id')

    # Restore companies
    op.add_column('companies', sa.Column('workspace_id', sa.Integer(), nullable=True))
    op.create_index('ix_companies_workspace_id', 'companies', ['workspace_id'], unique=False)
    op.drop_index(op.f('ix_companies_owner_id'), table_name='companies')
    op.drop_column('companies', 'owner_id')
    op.drop_index(op.f('ix_companies_organization_id'), table_name='companies')
    op.drop_column('companies', 'organization_id')

    print("Downgrade complete. WARNING: All data has been lost!")
