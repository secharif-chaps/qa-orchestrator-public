"""Drop deprecated workspace tables

Revision ID: 002_drop_workspace_tables
Revises: 001_initial_schema
Create Date: 2025-11-16

This migration drops the deprecated workspace-related tables that were replaced
by Keycloak Organizations architecture:
- workspaces
- workspace_members
- workspace_modules
- user_workspace_permissions

All organization and user management is now handled by Keycloak.
"""

import sqlalchemy as sa

from alembic import op

# revision identifiers, used by Alembic.
revision = "002_drop_workspace_tables"
down_revision = "001_initial_schema"
branch_labels = None
depends_on = None


def upgrade():
    """Drop deprecated workspace tables"""

    # Drop tables in correct order (respecting foreign key dependencies)
    op.execute("DROP TABLE IF EXISTS user_workspace_permissions CASCADE")
    op.execute("DROP TABLE IF EXISTS workspace_modules CASCADE")
    op.execute("DROP TABLE IF EXISTS workspace_members CASCADE")
    op.execute("DROP TABLE IF EXISTS workspaces CASCADE")

    # Drop enum types if they exist
    op.execute("DROP TYPE IF EXISTS workspacememberstatus CASCADE")
    op.execute("DROP TYPE IF EXISTS modulename CASCADE")


def downgrade():
    """Recreate workspace tables (for rollback purposes only)

    Note: This is a minimal recreation for rollback purposes.
    In practice, you should not downgrade from Keycloak Organizations
    back to database workspaces.
    """

    # Recreate enum types
    op.execute("""
        CREATE TYPE workspacememberstatus AS ENUM ('active', 'revoked')
    """)

    op.execute("""
        CREATE TYPE modulename AS ENUM ('screen', 'target', 'explore')
    """)

    # Recreate workspaces table
    op.create_table(
        "workspaces",
        sa.Column("id", sa.Integer(), nullable=False),
        sa.Column("name", sa.String(), nullable=False),
        sa.Column("description", sa.Text(), nullable=True),
        sa.Column("slug", sa.String(), nullable=False),
        sa.Column("created_at", sa.DateTime(timezone=True), server_default=sa.text("now()"), nullable=True),
        sa.Column("updated_at", sa.DateTime(timezone=True), nullable=True),
        sa.PrimaryKeyConstraint("id"),
        sa.UniqueConstraint("slug"),
    )
    op.create_index("ix_workspaces_id", "workspaces", ["id"])
    op.create_index("ix_workspaces_name", "workspaces", ["name"])
    op.create_index("ix_workspaces_slug", "workspaces", ["slug"])

    # Recreate workspace_members table
    op.create_table(
        "workspace_members",
        sa.Column("id", sa.Integer(), nullable=False),
        sa.Column("workspace_id", sa.Integer(), nullable=False),
        sa.Column("user_id", sa.String(), nullable=False),
        sa.Column("username", sa.String(), nullable=False),
        sa.Column("email", sa.String(), nullable=False),
        sa.Column("first_name", sa.String(100), nullable=True),
        sa.Column("last_name", sa.String(100), nullable=True),
        sa.Column("status", sa.Enum("active", "revoked", name="workspacememberstatus"), nullable=True),
        sa.Column("created_at", sa.DateTime(timezone=True), server_default=sa.text("now()"), nullable=True),
        sa.Column("updated_at", sa.DateTime(timezone=True), nullable=True),
        sa.ForeignKeyConstraint(["workspace_id"], ["workspaces.id"]),
        sa.PrimaryKeyConstraint("id"),
    )
    op.create_index("ix_workspace_members_email", "workspace_members", ["email"])
    op.create_index("ix_workspace_members_username", "workspace_members", ["username"])

    # Recreate workspace_modules table
    op.create_table(
        "workspace_modules",
        sa.Column("id", sa.Integer(), nullable=False),
        sa.Column("workspace_id", sa.Integer(), nullable=False),
        sa.Column("module_name", sa.Enum("screen", "target", "explore", name="modulename"), nullable=False),
        sa.Column("enabled", sa.Boolean(), nullable=False),
        sa.Column("token_count", sa.Integer(), nullable=False),
        sa.Column("created_at", sa.DateTime(timezone=True), server_default=sa.text("now()"), nullable=True),
        sa.Column("updated_at", sa.DateTime(timezone=True), nullable=True),
        sa.ForeignKeyConstraint(["workspace_id"], ["workspaces.id"]),
        sa.PrimaryKeyConstraint("id"),
        sa.UniqueConstraint("workspace_id", "module_name", name="uq_workspace_modules_workspace_module"),
    )

    # Recreate user_workspace_permissions table
    op.create_table(
        "user_workspace_permissions",
        sa.Column("id", sa.Integer(), nullable=False),
        sa.Column("user_id", sa.String(), nullable=False),
        sa.Column("workspace_id", sa.Integer(), nullable=True),
        sa.Column("permission", sa.String(), nullable=False),
        sa.Column("granted_by", sa.String(), nullable=True),
        sa.Column("granted_at", sa.DateTime(timezone=True), server_default=sa.text("now()"), nullable=True),
        sa.Column("created_at", sa.DateTime(timezone=True), server_default=sa.text("now()"), nullable=True),
        sa.Column("updated_at", sa.DateTime(timezone=True), nullable=True),
        sa.ForeignKeyConstraint(["workspace_id"], ["workspaces.id"]),
        sa.PrimaryKeyConstraint("id"),
        sa.UniqueConstraint("user_id", "workspace_id", "permission", name="unique_user_workspace_permission"),
    )
    op.create_index("ix_user_workspace_permissions_user_id", "user_workspace_permissions", ["user_id"])
    op.create_index("ix_user_workspace_permissions_workspace_id", "user_workspace_permissions", ["workspace_id"])
    op.create_index(
        "idx_user_workspace_permissions_lookup", "user_workspace_permissions", ["user_id", "workspace_id", "permission"]
    )
