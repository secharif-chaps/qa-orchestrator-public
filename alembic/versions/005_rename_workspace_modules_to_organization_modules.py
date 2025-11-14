"""Rename workspace_modules table to organization_modules

Revision ID: 005_rename_workspace_modules
Revises: 004_keycloak_org_migration
Create Date: 2025-11-13

This migration renames the workspace_modules table to organization_modules
and updates the workspace_id column to organization_id (UUID).
"""
from alembic import op
import sqlalchemy as sa

# revision identifiers, used by Alembic.
revision = '005_rename_workspace_modules'
down_revision = '004_keycloak_org_migration'
branch_labels = None
depends_on = None


def upgrade():
    """Rename workspace_modules to organization_modules"""

    print("Renaming workspace_modules table to organization_modules...")

    # Step 1: Rename the table
    op.rename_table('workspace_modules', 'organization_modules')

    # Step 2: Drop the old workspace_id foreign key constraint
    op.drop_constraint('workspace_modules_workspace_id_fkey', 'organization_modules', type_='foreignkey')

    # Step 3: Rename workspace_id column to organization_id
    op.alter_column('organization_modules', 'workspace_id',
                    new_column_name='organization_id',
                    existing_type=sa.Integer(),
                    type_=sa.String(),
                    existing_nullable=False)

    # Step 4: Update the unique constraint name
    op.drop_constraint('uq_workspace_modules_workspace_module', 'organization_modules', type_='unique')
    op.create_unique_constraint('uq_organization_modules_organization_module',
                                'organization_modules',
                                ['organization_id', 'module_name'])

    print("Migration complete! workspace_modules → organization_modules")


def downgrade():
    """Downgrade to workspace_modules"""

    print("WARNING: Downgrading organization_modules to workspace_modules...")

    # Reverse Step 4: Update unique constraint
    op.drop_constraint('uq_organization_modules_organization_module', 'organization_modules', type_='unique')
    op.create_unique_constraint('uq_workspace_modules_workspace_module',
                                'organization_modules',
                                ['organization_id', 'module_name'])

    # Reverse Step 3: Rename organization_id back to workspace_id
    op.alter_column('organization_modules', 'organization_id',
                    new_column_name='workspace_id',
                    existing_type=sa.String(),
                    type_=sa.Integer(),
                    existing_nullable=False)

    # Reverse Step 2: Re-add foreign key (table doesn't exist, will fail if workspaces table dropped)
    # We can't restore the foreign key if workspaces table is gone
    # op.create_foreign_key('workspace_modules_workspace_id_fkey',
    #                      'organization_modules', 'workspaces',
    #                      ['workspace_id'], ['id'])

    # Reverse Step 1: Rename table back
    op.rename_table('organization_modules', 'workspace_modules')

    print("Downgrade complete. WARNING: Foreign key to workspaces not restored!")
