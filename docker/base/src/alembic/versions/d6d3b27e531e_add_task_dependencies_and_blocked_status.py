"""add task dependencies and blocked status

Revision ID: d6d3b27e531e
Revises: 003
Create Date: 2025-10-20 12:54:04.465045

"""
from alembic import op
import sqlalchemy as sa


# revision identifiers, used by Alembic.
revision = 'd6d3b27e531e'
down_revision = '003'
branch_labels = None
depends_on = None


def upgrade():
    # Add BLOCKED status to task_status_enum
    op.execute("ALTER TYPE task_status_enum ADD VALUE IF NOT EXISTS 'blocked'")

    # Create task_dependencies table
    op.create_table(
        'task_dependencies',
        sa.Column('id', sa.Integer(), primary_key=True),
        sa.Column('task_id', sa.Integer(), nullable=False),
        sa.Column('depends_on_task_id', sa.Integer(), nullable=False),
        sa.Column('created_at', sa.DateTime(), server_default=sa.text('now()'), nullable=False),
        sa.ForeignKeyConstraint(['task_id'], ['tasks.id'], ondelete='CASCADE'),
        sa.ForeignKeyConstraint(['depends_on_task_id'], ['tasks.id'], ondelete='CASCADE'),
        sa.UniqueConstraint('task_id', 'depends_on_task_id', name='uq_task_dependency')
    )

    # Create indexes
    op.create_index('idx_task_dependencies_task_id', 'task_dependencies', ['task_id'])
    op.create_index('idx_task_dependencies_depends_on_task_id', 'task_dependencies', ['depends_on_task_id'])

    # Add is_prerequisite flag to tasks table
    op.add_column('tasks', sa.Column('is_prerequisite', sa.Boolean(), server_default='false', nullable=False))
    op.create_index('idx_tasks_is_prerequisite', 'tasks', ['is_prerequisite'])


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