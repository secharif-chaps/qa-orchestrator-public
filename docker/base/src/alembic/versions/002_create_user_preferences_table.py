"""create user_preferences table

Revision ID: 002
Revises: 001
Create Date: 2025-10-14 18:00:00.000000

"""
from alembic import op
import sqlalchemy as sa
from sqlalchemy.dialects.postgresql import JSONB
from sqlalchemy.sql import func

# revision identifiers, used by Alembic.
revision = '002'
down_revision = '001'
branch_labels = None
depends_on = None


def upgrade():
    # Create user_preferences table with JSONB for flexible preferences storage
    op.create_table(
        'user_preferences',
        sa.Column('id', sa.Integer(), nullable=False),
        sa.Column('keycloak_user_id', sa.String(length=255), nullable=False),
        sa.Column('preferences', JSONB, nullable=False, server_default='{}'),
        sa.Column('created_at', sa.DateTime(timezone=True), nullable=False, server_default=func.now()),
        sa.Column('updated_at', sa.DateTime(timezone=True), nullable=False, server_default=func.now()),
        sa.PrimaryKeyConstraint('id'),
        sa.UniqueConstraint('keycloak_user_id', name='uq_user_preferences_keycloak_user_id')
    )

    # Create indexes
    op.create_index('ix_user_preferences_id', 'user_preferences', ['id'])
    op.create_index('ix_user_preferences_keycloak_user_id', 'user_preferences', ['keycloak_user_id'], unique=True)

    # Create GIN index for efficient JSONB queries
    op.create_index(
        'idx_user_preferences_jsonb',
        'user_preferences',
        ['preferences'],
        unique=False,
        postgresql_using='gin'
    )

    # Create index on updated_at for future analytics queries
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


def downgrade():
    # Drop trigger
    op.execute('DROP TRIGGER IF EXISTS trigger_update_user_preferences_updated_at ON user_preferences;')

    # Drop trigger function
    op.execute('DROP FUNCTION IF EXISTS update_user_preferences_updated_at();')

    # Drop indexes
    op.drop_index('idx_user_preferences_updated_at', table_name='user_preferences')
    op.drop_index('idx_user_preferences_jsonb', table_name='user_preferences')
    op.drop_index('ix_user_preferences_keycloak_user_id', table_name='user_preferences')
    op.drop_index('ix_user_preferences_id', table_name='user_preferences')

    # Drop table
    op.drop_table('user_preferences')
