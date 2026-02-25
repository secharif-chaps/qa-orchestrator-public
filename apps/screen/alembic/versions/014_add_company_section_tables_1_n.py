"""Add 1:N company child tables

Revision ID: 014
Revises: 013
Create Date: 2025-12-29

Creates 10 new 1:N child tables for normalized company data storage:
- company_online_services: Digital services offered by the company
- company_social_media_accounts: Social media presence
- company_timeline_events: Historical events
- company_product_items: Products (range, partner brands, private labels)
- company_product_categories: Product category groupings
- company_job_offers: Job openings
- company_csr_initiatives: CSR initiatives by type
- company_press_items: Press coverage by type
- company_team_members: Team hierarchy with adjacency list pattern

Also creates 3 ENUM types:
- product_item_type_enum: 'range', 'partner_brand', 'private_label'
- csr_initiative_type_enum: 7 CSR initiative types
- press_item_type_enum: 8 press item types

Each table uses company_id FK with ON DELETE CASCADE.
Team members use self-referential parent_id FK with ON DELETE SET NULL.
"""

from alembic import op
import sqlalchemy as sa
from sqlalchemy.dialects.postgresql import ENUM


# revision identifiers, used by Alembic
revision = '014'
down_revision = '013'
branch_labels = None
depends_on = None


# Define ENUM types
product_item_type_enum = ENUM(
    'range', 'partner_brand', 'private_label',
    name='product_item_type_enum',
    create_type=False  # We'll create it explicitly
)

csr_initiative_type_enum = ENUM(
    'responsibility', 'charity', 'sustainability', 'community', 'diversity', 'ethics', 'awards',
    name='csr_initiative_type_enum',
    create_type=False
)

press_item_type_enum = ENUM(
    'article', 'press_release', 'media_mention', 'award', 'product_launch', 'interview', 'financial', 'partnership',
    name='press_item_type_enum',
    create_type=False
)


def upgrade():
    """Create 1:N child tables for company data."""

    # ============================================================================
    # 1. CREATE ENUM TYPES
    # ============================================================================
    print("Creating ENUM types...")

    # Create product_item_type_enum
    op.execute("""
        CREATE TYPE product_item_type_enum AS ENUM (
            'range', 'partner_brand', 'private_label'
        )
    """)

    # Create csr_initiative_type_enum
    op.execute("""
        CREATE TYPE csr_initiative_type_enum AS ENUM (
            'responsibility', 'charity', 'sustainability', 'community', 'diversity', 'ethics', 'awards'
        )
    """)

    # Create press_item_type_enum
    op.execute("""
        CREATE TYPE press_item_type_enum AS ENUM (
            'article', 'press_release', 'media_mention', 'award', 'product_launch', 'interview', 'financial', 'partnership'
        )
    """)

    # ============================================================================
    # 2. CREATE COMPANY_ONLINE_SERVICES TABLE
    # ============================================================================
    print("Creating company_online_services table...")
    op.create_table(
        'company_online_services',
        # Primary key
        sa.Column('id', sa.Integer(), nullable=False, autoincrement=True),
        # Foreign key to companies
        sa.Column('company_id', sa.Integer(), nullable=False),

        # Name with SourcedValue pattern (translatable)
        sa.Column('name', sa.Text(), nullable=True),
        sa.Column('name_source', sa.Text(), nullable=True),
        sa.Column('name_value_fr', sa.Text(), nullable=True),

        # Description with SourcedValue pattern (translatable)
        sa.Column('description', sa.Text(), nullable=True),
        sa.Column('description_source', sa.Text(), nullable=True),
        sa.Column('description_value_fr', sa.Text(), nullable=True),

        # Timestamp
        sa.Column('created_at', sa.DateTime(timezone=True), server_default=sa.func.now(), nullable=False),

        # Primary key constraint
        sa.PrimaryKeyConstraint('id'),

        # Foreign key to companies table with CASCADE delete
        sa.ForeignKeyConstraint(['company_id'], ['companies.id'], ondelete='CASCADE')
    )

    # Create index on company_id for faster lookups
    op.create_index('idx_online_services_company_id', 'company_online_services', ['company_id'])

    # ============================================================================
    # 3. CREATE COMPANY_SOCIAL_MEDIA_ACCOUNTS TABLE
    # ============================================================================
    print("Creating company_social_media_accounts table...")
    op.create_table(
        'company_social_media_accounts',
        # Primary key
        sa.Column('id', sa.Integer(), nullable=False, autoincrement=True),
        # Foreign key to companies
        sa.Column('company_id', sa.Integer(), nullable=False),

        # Platform (no translation - proper noun)
        sa.Column('platform', sa.Text(), nullable=True),
        sa.Column('platform_source', sa.Text(), nullable=True),

        # URL (no translation - identifier)
        sa.Column('url', sa.Text(), nullable=True),
        sa.Column('url_source', sa.Text(), nullable=True),

        # Timestamp
        sa.Column('created_at', sa.DateTime(timezone=True), server_default=sa.func.now(), nullable=False),

        # Primary key constraint
        sa.PrimaryKeyConstraint('id'),

        # Foreign key to companies table with CASCADE delete
        sa.ForeignKeyConstraint(['company_id'], ['companies.id'], ondelete='CASCADE')
    )

    # Create index on company_id
    op.create_index('idx_social_media_company_id', 'company_social_media_accounts', ['company_id'])

    # ============================================================================
    # 4. CREATE COMPANY_TIMELINE_EVENTS TABLE
    # ============================================================================
    print("Creating company_timeline_events table...")
    op.create_table(
        'company_timeline_events',
        # Primary key
        sa.Column('id', sa.Integer(), nullable=False, autoincrement=True),
        # Foreign key to companies
        sa.Column('company_id', sa.Integer(), nullable=False),

        # Date (no translation - date/number)
        sa.Column('date', sa.Text(), nullable=True),
        sa.Column('date_source', sa.Text(), nullable=True),

        # Title (translatable)
        sa.Column('title', sa.Text(), nullable=True),
        sa.Column('title_source', sa.Text(), nullable=True),
        sa.Column('title_value_fr', sa.Text(), nullable=True),

        # Description (translatable)
        sa.Column('description', sa.Text(), nullable=True),
        sa.Column('description_source', sa.Text(), nullable=True),
        sa.Column('description_value_fr', sa.Text(), nullable=True),

        # Category (translatable)
        sa.Column('category', sa.Text(), nullable=True),
        sa.Column('category_source', sa.Text(), nullable=True),
        sa.Column('category_value_fr', sa.Text(), nullable=True),

        # Location (no translation - proper noun/location)
        sa.Column('location', sa.Text(), nullable=True),
        sa.Column('location_source', sa.Text(), nullable=True),

        # Impact (translatable)
        sa.Column('impact', sa.Text(), nullable=True),
        sa.Column('impact_source', sa.Text(), nullable=True),
        sa.Column('impact_value_fr', sa.Text(), nullable=True),

        # Timestamp
        sa.Column('created_at', sa.DateTime(timezone=True), server_default=sa.func.now(), nullable=False),

        # Primary key constraint
        sa.PrimaryKeyConstraint('id'),

        # Foreign key to companies table with CASCADE delete
        sa.ForeignKeyConstraint(['company_id'], ['companies.id'], ondelete='CASCADE')
    )

    # Create index on company_id
    op.create_index('idx_timeline_events_company_id', 'company_timeline_events', ['company_id'])

    # ============================================================================
    # 5. CREATE COMPANY_PRODUCT_ITEMS TABLE
    # ============================================================================
    print("Creating company_product_items table...")
    op.create_table(
        'company_product_items',
        # Primary key
        sa.Column('id', sa.Integer(), nullable=False, autoincrement=True),
        # Foreign key to companies
        sa.Column('company_id', sa.Integer(), nullable=False),

        # Type ENUM (range, partner_brand, private_label)
        sa.Column('type', product_item_type_enum, nullable=False),

        # Value with source (value_fr only for 'range' type conceptually, but column exists for all)
        sa.Column('value', sa.Text(), nullable=True),
        sa.Column('value_source', sa.Text(), nullable=True),
        sa.Column('value_value_fr', sa.Text(), nullable=True),

        # Timestamp
        sa.Column('created_at', sa.DateTime(timezone=True), server_default=sa.func.now(), nullable=False),

        # Primary key constraint
        sa.PrimaryKeyConstraint('id'),

        # Foreign key to companies table with CASCADE delete
        sa.ForeignKeyConstraint(['company_id'], ['companies.id'], ondelete='CASCADE')
    )

    # Create index on company_id
    op.create_index('idx_product_items_company_id', 'company_product_items', ['company_id'])

    # ============================================================================
    # 6. CREATE COMPANY_PRODUCT_CATEGORIES TABLE
    # ============================================================================
    print("Creating company_product_categories table...")
    op.create_table(
        'company_product_categories',
        # Primary key
        sa.Column('id', sa.Integer(), nullable=False, autoincrement=True),
        # Foreign key to companies
        sa.Column('company_id', sa.Integer(), nullable=False),

        # Category name (translatable)
        sa.Column('category_name', sa.Text(), nullable=True),
        sa.Column('category_name_value_fr', sa.Text(), nullable=True),

        # Items array (translatable)
        sa.Column('items', sa.ARRAY(sa.Text()), nullable=True),
        sa.Column('items_value_fr', sa.ARRAY(sa.Text()), nullable=True),

        # Timestamp
        sa.Column('created_at', sa.DateTime(timezone=True), server_default=sa.func.now(), nullable=False),

        # Primary key constraint
        sa.PrimaryKeyConstraint('id'),

        # Foreign key to companies table with CASCADE delete
        sa.ForeignKeyConstraint(['company_id'], ['companies.id'], ondelete='CASCADE')
    )

    # Create index on company_id
    op.create_index('idx_product_categories_company_id', 'company_product_categories', ['company_id'])

    # ============================================================================
    # 7. CREATE COMPANY_JOB_OFFERS TABLE
    # ============================================================================
    print("Creating company_job_offers table...")
    op.create_table(
        'company_job_offers',
        # Primary key
        sa.Column('id', sa.Integer(), nullable=False, autoincrement=True),
        # Foreign key to companies
        sa.Column('company_id', sa.Integer(), nullable=False),

        # Title (translatable)
        sa.Column('title', sa.Text(), nullable=True),
        sa.Column('title_source', sa.Text(), nullable=True),
        sa.Column('title_value_fr', sa.Text(), nullable=True),

        # Location (no translation - location)
        sa.Column('location', sa.Text(), nullable=True),
        sa.Column('location_source', sa.Text(), nullable=True),

        # Department (translatable)
        sa.Column('department', sa.Text(), nullable=True),
        sa.Column('department_source', sa.Text(), nullable=True),
        sa.Column('department_value_fr', sa.Text(), nullable=True),

        # Description (translatable)
        sa.Column('description', sa.Text(), nullable=True),
        sa.Column('description_source', sa.Text(), nullable=True),
        sa.Column('description_value_fr', sa.Text(), nullable=True),

        # Requirements (translatable)
        sa.Column('requirements', sa.Text(), nullable=True),
        sa.Column('requirements_source', sa.Text(), nullable=True),
        sa.Column('requirements_value_fr', sa.Text(), nullable=True),

        # Posted date (no translation - date)
        sa.Column('posted_date', sa.Text(), nullable=True),
        sa.Column('posted_date_source', sa.Text(), nullable=True),

        # Timestamp
        sa.Column('created_at', sa.DateTime(timezone=True), server_default=sa.func.now(), nullable=False),

        # Primary key constraint
        sa.PrimaryKeyConstraint('id'),

        # Foreign key to companies table with CASCADE delete
        sa.ForeignKeyConstraint(['company_id'], ['companies.id'], ondelete='CASCADE')
    )

    # Create index on company_id
    op.create_index('idx_job_offers_company_id', 'company_job_offers', ['company_id'])

    # ============================================================================
    # 8. CREATE COMPANY_CSR_INITIATIVES TABLE
    # ============================================================================
    print("Creating company_csr_initiatives table...")
    op.create_table(
        'company_csr_initiatives',
        # Primary key
        sa.Column('id', sa.Integer(), nullable=False, autoincrement=True),
        # Foreign key to companies
        sa.Column('company_id', sa.Integer(), nullable=False),

        # Type ENUM
        sa.Column('type', csr_initiative_type_enum, nullable=False),

        # Value with SourcedValue pattern (translatable)
        sa.Column('value', sa.Text(), nullable=True),
        sa.Column('value_source', sa.Text(), nullable=True),
        sa.Column('value_value_fr', sa.Text(), nullable=True),

        # Timestamp
        sa.Column('created_at', sa.DateTime(timezone=True), server_default=sa.func.now(), nullable=False),

        # Primary key constraint
        sa.PrimaryKeyConstraint('id'),

        # Foreign key to companies table with CASCADE delete
        sa.ForeignKeyConstraint(['company_id'], ['companies.id'], ondelete='CASCADE')
    )

    # Create index on company_id
    op.create_index('idx_csr_initiatives_company_id', 'company_csr_initiatives', ['company_id'])

    # ============================================================================
    # 9. CREATE COMPANY_PRESS_ITEMS TABLE
    # ============================================================================
    print("Creating company_press_items table...")
    op.create_table(
        'company_press_items',
        # Primary key
        sa.Column('id', sa.Integer(), nullable=False, autoincrement=True),
        # Foreign key to companies
        sa.Column('company_id', sa.Integer(), nullable=False),

        # Type ENUM
        sa.Column('type', press_item_type_enum, nullable=False),

        # Value with SourcedValue pattern (translatable)
        sa.Column('value', sa.Text(), nullable=True),
        sa.Column('value_source', sa.Text(), nullable=True),
        sa.Column('value_value_fr', sa.Text(), nullable=True),

        # Timestamp
        sa.Column('created_at', sa.DateTime(timezone=True), server_default=sa.func.now(), nullable=False),

        # Primary key constraint
        sa.PrimaryKeyConstraint('id'),

        # Foreign key to companies table with CASCADE delete
        sa.ForeignKeyConstraint(['company_id'], ['companies.id'], ondelete='CASCADE')
    )

    # Create index on company_id
    op.create_index('idx_press_items_company_id', 'company_press_items', ['company_id'])

    # ============================================================================
    # 10. CREATE COMPANY_TEAM_MEMBERS TABLE
    # ============================================================================
    print("Creating company_team_members table...")
    op.create_table(
        'company_team_members',
        # Primary key
        sa.Column('id', sa.Integer(), nullable=False, autoincrement=True),
        # Foreign key to companies
        sa.Column('company_id', sa.Integer(), nullable=False),
        # Self-referential FK for hierarchy (CEO has parent_id = NULL)
        sa.Column('parent_id', sa.Integer(), nullable=True),

        # Position (translatable)
        sa.Column('position', sa.Text(), nullable=True),
        sa.Column('position_source', sa.Text(), nullable=True),
        sa.Column('position_value_fr', sa.Text(), nullable=True),

        # First name (no translation - proper noun)
        sa.Column('first_name', sa.Text(), nullable=True),
        sa.Column('first_name_source', sa.Text(), nullable=True),

        # Last name (no translation - proper noun)
        sa.Column('last_name', sa.Text(), nullable=True),
        sa.Column('last_name_source', sa.Text(), nullable=True),

        # LinkedIn URL (no translation - identifier)
        sa.Column('linkedin_url', sa.Text(), nullable=True),
        sa.Column('linkedin_url_source', sa.Text(), nullable=True),

        # Timestamp
        sa.Column('created_at', sa.DateTime(timezone=True), server_default=sa.func.now(), nullable=False),

        # Primary key constraint
        sa.PrimaryKeyConstraint('id'),

        # Foreign key to companies table with CASCADE delete
        sa.ForeignKeyConstraint(['company_id'], ['companies.id'], ondelete='CASCADE'),

        # Self-referential FK with SET NULL on delete (if parent deleted, children become root-level)
        sa.ForeignKeyConstraint(['parent_id'], ['company_team_members.id'], ondelete='SET NULL')
    )

    # Create indexes
    op.create_index('idx_team_members_company_id', 'company_team_members', ['company_id'])
    op.create_index('idx_team_members_parent_id', 'company_team_members', ['parent_id'])

    print("All 10 child tables created successfully!")


def downgrade():
    """Drop all 1:N child tables and ENUM types."""

    print("Dropping 1:N child tables...")

    # Drop indexes first (they'll be dropped with tables, but explicit is better)
    op.drop_index('idx_team_members_parent_id', table_name='company_team_members')
    op.drop_index('idx_team_members_company_id', table_name='company_team_members')
    op.drop_index('idx_press_items_company_id', table_name='company_press_items')
    op.drop_index('idx_csr_initiatives_company_id', table_name='company_csr_initiatives')
    op.drop_index('idx_job_offers_company_id', table_name='company_job_offers')
    op.drop_index('idx_product_categories_company_id', table_name='company_product_categories')
    op.drop_index('idx_product_items_company_id', table_name='company_product_items')
    op.drop_index('idx_timeline_events_company_id', table_name='company_timeline_events')
    op.drop_index('idx_social_media_company_id', table_name='company_social_media_accounts')
    op.drop_index('idx_online_services_company_id', table_name='company_online_services')

    # Drop tables in reverse order of creation
    op.drop_table('company_team_members')
    op.drop_table('company_press_items')
    op.drop_table('company_csr_initiatives')
    op.drop_table('company_job_offers')
    op.drop_table('company_product_categories')
    op.drop_table('company_product_items')
    op.drop_table('company_timeline_events')
    op.drop_table('company_social_media_accounts')
    op.drop_table('company_online_services')

    # Drop ENUM types
    print("Dropping ENUM types...")
    op.execute("DROP TYPE press_item_type_enum")
    op.execute("DROP TYPE csr_initiative_type_enum")
    op.execute("DROP TYPE product_item_type_enum")

    print("All 10 child tables and ENUM types dropped successfully!")
