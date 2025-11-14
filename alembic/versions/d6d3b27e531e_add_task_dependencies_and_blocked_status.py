"""add task dependencies and blocked status

Revision ID: d6d3b27e531e
Revises: 003
Create Date: 2025-10-20 12:54:04.465045

"""
from alembic import op
import sqlalchemy as sa


# revision identifiers, used by Alembic.
revision = 'd6d3b27e531e'
down_revision = '005_rename_workspace_modules'
branch_labels = None
depends_on = None


def upgrade():
    # Add BLOCKED status to task_status_enum
    op.execute("ALTER TYPE task_status_enum ADD VALUE IF NOT EXISTS 'blocked'")

    # Create task_dependencies table if it doesn't exist
    op.execute("""
        CREATE TABLE IF NOT EXISTS task_dependencies (
            id SERIAL PRIMARY KEY,
            task_id INTEGER NOT NULL REFERENCES tasks(id) ON DELETE CASCADE,
            depends_on_task_id INTEGER NOT NULL REFERENCES tasks(id) ON DELETE CASCADE,
            created_at TIMESTAMP WITHOUT TIME ZONE DEFAULT now() NOT NULL,
            CONSTRAINT uq_task_dependency UNIQUE (task_id, depends_on_task_id)
        )
    """)

    # Create indexes if they don't exist
    op.execute("CREATE INDEX IF NOT EXISTS idx_task_dependencies_task_id ON task_dependencies(task_id)")
    op.execute("CREATE INDEX IF NOT EXISTS idx_task_dependencies_depends_on_task_id ON task_dependencies(depends_on_task_id)")

    # Add is_prerequisite flag to tasks table if it doesn't exist
    op.execute("""
        DO $$
        BEGIN
            IF NOT EXISTS (SELECT 1 FROM information_schema.columns
                          WHERE table_name='tasks' AND column_name='is_prerequisite') THEN
                ALTER TABLE tasks ADD COLUMN is_prerequisite BOOLEAN DEFAULT false NOT NULL;
            END IF;
        END $$;
    """)
    op.execute("CREATE INDEX IF NOT EXISTS idx_tasks_is_prerequisite ON tasks(is_prerequisite)")


def downgrade():
    # Remove index and column
    op.drop_index('idx_tasks_is_prerequisite')
    op.drop_column('tasks', 'is_prerequisite')

    # Drop indexes
    op.drop_index('idx_task_dependencies_depends_on_task_id')
    op.drop_index('idx_task_dependencies_task_id')

    # Drop table
    op.drop_table('task_dependencies')

    # Note: Cannot remove enum value in PostgreSQL without recreating the type
    # The 'blocked' status will remain in the enum but won't be used after downgrade 