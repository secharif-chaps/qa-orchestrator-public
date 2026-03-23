"""Add 1:1 company section tables

Revision ID: 013
Revises: 012
Create Date: 2025-12-29

Creates 7 new 1:1 section tables for normalized company data storage:
- company_profile: Profile data (insights, group_name, business_line, etc.)
- company_digital: Digital strategy data (insights, overall_strategy, etc.)
- company_timeline: Timeline insights
- company_products: Products data (insights, customer_type, marketing_positioning)
- company_jobs: Jobs insights (total_openings, top_departments, etc.)
- company_csr: CSR data (insights, responsibility)
- company_press: Press insights

Each table uses company_id as primary key with foreign key to companies table.
ON DELETE CASCADE ensures cleanup when companies are deleted.
"""

import sqlalchemy as sa

from alembic import op

# revision identifiers, used by Alembic
revision = "013"
down_revision = "012"
branch_labels = None
depends_on = None


def upgrade():
    """Create 1:1 section tables for company data."""

    # ============================================================================
    # 1. CREATE COMPANY_PROFILE TABLE
    # ============================================================================
    print("Creating company_profile table...")
    op.create_table(
        "company_profile",
        # Primary key is also foreign key to companies
        sa.Column("company_id", sa.Integer(), nullable=False),
        # Insights with SourcedValue pattern (translatable)
        sa.Column("insights", sa.Text(), nullable=True),
        sa.Column("insights_source", sa.Text(), nullable=True),
        sa.Column("insights_value_fr", sa.Text(), nullable=True),
        # Group name (proper noun - no translation)
        sa.Column("group_name", sa.Text(), nullable=True),
        sa.Column("group_name_source", sa.Text(), nullable=True),
        # Business line (translatable)
        sa.Column("business_line", sa.Text(), nullable=True),
        sa.Column("business_line_source", sa.Text(), nullable=True),
        sa.Column("business_line_value_fr", sa.Text(), nullable=True),
        # Catchphrase (translatable)
        sa.Column("catchphrase", sa.Text(), nullable=True),
        sa.Column("catchphrase_source", sa.Text(), nullable=True),
        sa.Column("catchphrase_value_fr", sa.Text(), nullable=True),
        # Establishment year (number - no translation)
        sa.Column("establishment_year", sa.Text(), nullable=True),
        sa.Column("establishment_year_source", sa.Text(), nullable=True),
        # Employee count (number - no translation)
        sa.Column("employee_count", sa.Text(), nullable=True),
        sa.Column("employee_count_source", sa.Text(), nullable=True),
        # Revenue (number - no translation)
        sa.Column("revenue", sa.Text(), nullable=True),
        sa.Column("revenue_source", sa.Text(), nullable=True),
        # CEO (proper noun - no translation)
        sa.Column("ceo", sa.Text(), nullable=True),
        sa.Column("ceo_source", sa.Text(), nullable=True),
        # Headquarters (proper noun/location - no translation)
        sa.Column("hq", sa.Text(), nullable=True),
        sa.Column("hq_source", sa.Text(), nullable=True),
        # Timestamps
        sa.Column("created_at", sa.DateTime(timezone=True), server_default=sa.func.now(), nullable=False),
        sa.Column(
            "updated_at",
            sa.DateTime(timezone=True),
            server_default=sa.func.now(),
            onupdate=sa.func.now(),
            nullable=False,
        ),
        # Primary key constraint
        sa.PrimaryKeyConstraint("company_id"),
        # Foreign key to companies table with CASCADE delete
        sa.ForeignKeyConstraint(["company_id"], ["companies.id"], ondelete="CASCADE"),
    )

    # ============================================================================
    # 2. CREATE COMPANY_DIGITAL TABLE
    # ============================================================================
    print("Creating company_digital table...")
    op.create_table(
        "company_digital",
        # Primary key is also foreign key to companies
        sa.Column("company_id", sa.Integer(), nullable=False),
        # Insights with SourcedValue pattern (translatable)
        sa.Column("insights", sa.Text(), nullable=True),
        sa.Column("insights_source", sa.Text(), nullable=True),
        sa.Column("insights_value_fr", sa.Text(), nullable=True),
        # Overall strategy (translatable)
        sa.Column("overall_strategy", sa.Text(), nullable=True),
        sa.Column("overall_strategy_source", sa.Text(), nullable=True),
        sa.Column("overall_strategy_value_fr", sa.Text(), nullable=True),
        # Digital transformation (translatable)
        sa.Column("digital_transformation", sa.Text(), nullable=True),
        sa.Column("digital_transformation_source", sa.Text(), nullable=True),
        sa.Column("digital_transformation_value_fr", sa.Text(), nullable=True),
        # E-commerce capabilities (translatable)
        sa.Column("ecommerce_capabilities", sa.Text(), nullable=True),
        sa.Column("ecommerce_capabilities_source", sa.Text(), nullable=True),
        sa.Column("ecommerce_capabilities_value_fr", sa.Text(), nullable=True),
        # Mobile strategy (translatable)
        sa.Column("mobile_strategy", sa.Text(), nullable=True),
        sa.Column("mobile_strategy_source", sa.Text(), nullable=True),
        sa.Column("mobile_strategy_value_fr", sa.Text(), nullable=True),
        # Digital marketing approach (translatable)
        sa.Column("digital_marketing_approach", sa.Text(), nullable=True),
        sa.Column("digital_marketing_approach_source", sa.Text(), nullable=True),
        sa.Column("digital_marketing_approach_value_fr", sa.Text(), nullable=True),
        # Loyalty program (translatable)
        sa.Column("loyalty_program", sa.Text(), nullable=True),
        sa.Column("loyalty_program_source", sa.Text(), nullable=True),
        sa.Column("loyalty_program_value_fr", sa.Text(), nullable=True),
        # Timestamps
        sa.Column("created_at", sa.DateTime(timezone=True), server_default=sa.func.now(), nullable=False),
        sa.Column(
            "updated_at",
            sa.DateTime(timezone=True),
            server_default=sa.func.now(),
            onupdate=sa.func.now(),
            nullable=False,
        ),
        # Primary key constraint
        sa.PrimaryKeyConstraint("company_id"),
        # Foreign key to companies table with CASCADE delete
        sa.ForeignKeyConstraint(["company_id"], ["companies.id"], ondelete="CASCADE"),
    )

    # ============================================================================
    # 3. CREATE COMPANY_TIMELINE TABLE
    # ============================================================================
    print("Creating company_timeline table...")
    op.create_table(
        "company_timeline",
        # Primary key is also foreign key to companies
        sa.Column("company_id", sa.Integer(), nullable=False),
        # Insights with SourcedValue pattern (translatable)
        sa.Column("insights", sa.Text(), nullable=True),
        sa.Column("insights_source", sa.Text(), nullable=True),
        sa.Column("insights_value_fr", sa.Text(), nullable=True),
        # Timestamps
        sa.Column("created_at", sa.DateTime(timezone=True), server_default=sa.func.now(), nullable=False),
        sa.Column(
            "updated_at",
            sa.DateTime(timezone=True),
            server_default=sa.func.now(),
            onupdate=sa.func.now(),
            nullable=False,
        ),
        # Primary key constraint
        sa.PrimaryKeyConstraint("company_id"),
        # Foreign key to companies table with CASCADE delete
        sa.ForeignKeyConstraint(["company_id"], ["companies.id"], ondelete="CASCADE"),
    )

    # ============================================================================
    # 4. CREATE COMPANY_PRODUCTS TABLE
    # ============================================================================
    print("Creating company_products table...")
    op.create_table(
        "company_products",
        # Primary key is also foreign key to companies
        sa.Column("company_id", sa.Integer(), nullable=False),
        # Insights with SourcedValue pattern (translatable)
        sa.Column("insights", sa.Text(), nullable=True),
        sa.Column("insights_source", sa.Text(), nullable=True),
        sa.Column("insights_value_fr", sa.Text(), nullable=True),
        # Customer type (translatable)
        sa.Column("customer_type", sa.Text(), nullable=True),
        sa.Column("customer_type_source", sa.Text(), nullable=True),
        sa.Column("customer_type_value_fr", sa.Text(), nullable=True),
        # Marketing positioning (translatable)
        sa.Column("marketing_positioning", sa.Text(), nullable=True),
        sa.Column("marketing_positioning_source", sa.Text(), nullable=True),
        sa.Column("marketing_positioning_value_fr", sa.Text(), nullable=True),
        # Timestamps
        sa.Column("created_at", sa.DateTime(timezone=True), server_default=sa.func.now(), nullable=False),
        sa.Column(
            "updated_at",
            sa.DateTime(timezone=True),
            server_default=sa.func.now(),
            onupdate=sa.func.now(),
            nullable=False,
        ),
        # Primary key constraint
        sa.PrimaryKeyConstraint("company_id"),
        # Foreign key to companies table with CASCADE delete
        sa.ForeignKeyConstraint(["company_id"], ["companies.id"], ondelete="CASCADE"),
    )

    # ============================================================================
    # 5. CREATE COMPANY_JOBS TABLE
    # ============================================================================
    print("Creating company_jobs table...")
    op.create_table(
        "company_jobs",
        # Primary key is also foreign key to companies
        sa.Column("company_id", sa.Integer(), nullable=False),
        # Total openings (integer value - no translation)
        sa.Column("insights_total_openings", sa.Integer(), nullable=True),
        sa.Column("insights_total_openings_source", sa.Text(), nullable=True),
        # Top departments (translatable)
        sa.Column("insights_top_departments", sa.Text(), nullable=True),
        sa.Column("insights_top_departments_source", sa.Text(), nullable=True),
        sa.Column("insights_top_departments_value_fr", sa.Text(), nullable=True),
        # Hiring focus (translatable)
        sa.Column("insights_hiring_focus", sa.Text(), nullable=True),
        sa.Column("insights_hiring_focus_source", sa.Text(), nullable=True),
        sa.Column("insights_hiring_focus_value_fr", sa.Text(), nullable=True),
        # Growth indicators (translatable)
        sa.Column("insights_growth_indicators", sa.Text(), nullable=True),
        sa.Column("insights_growth_indicators_source", sa.Text(), nullable=True),
        sa.Column("insights_growth_indicators_value_fr", sa.Text(), nullable=True),
        # Timestamps
        sa.Column("created_at", sa.DateTime(timezone=True), server_default=sa.func.now(), nullable=False),
        sa.Column(
            "updated_at",
            sa.DateTime(timezone=True),
            server_default=sa.func.now(),
            onupdate=sa.func.now(),
            nullable=False,
        ),
        # Primary key constraint
        sa.PrimaryKeyConstraint("company_id"),
        # Foreign key to companies table with CASCADE delete
        sa.ForeignKeyConstraint(["company_id"], ["companies.id"], ondelete="CASCADE"),
    )

    # ============================================================================
    # 6. CREATE COMPANY_CSR TABLE
    # ============================================================================
    print("Creating company_csr table...")
    op.create_table(
        "company_csr",
        # Primary key is also foreign key to companies
        sa.Column("company_id", sa.Integer(), nullable=False),
        # Insights with SourcedValue pattern (translatable)
        sa.Column("insights", sa.Text(), nullable=True),
        sa.Column("insights_source", sa.Text(), nullable=True),
        sa.Column("insights_value_fr", sa.Text(), nullable=True),
        # Responsibility (translatable)
        sa.Column("responsibility", sa.Text(), nullable=True),
        sa.Column("responsibility_source", sa.Text(), nullable=True),
        sa.Column("responsibility_value_fr", sa.Text(), nullable=True),
        # Timestamps
        sa.Column("created_at", sa.DateTime(timezone=True), server_default=sa.func.now(), nullable=False),
        sa.Column(
            "updated_at",
            sa.DateTime(timezone=True),
            server_default=sa.func.now(),
            onupdate=sa.func.now(),
            nullable=False,
        ),
        # Primary key constraint
        sa.PrimaryKeyConstraint("company_id"),
        # Foreign key to companies table with CASCADE delete
        sa.ForeignKeyConstraint(["company_id"], ["companies.id"], ondelete="CASCADE"),
    )

    # ============================================================================
    # 7. CREATE COMPANY_PRESS TABLE
    # ============================================================================
    print("Creating company_press table...")
    op.create_table(
        "company_press",
        # Primary key is also foreign key to companies
        sa.Column("company_id", sa.Integer(), nullable=False),
        # Insights with SourcedValue pattern (translatable)
        sa.Column("insights", sa.Text(), nullable=True),
        sa.Column("insights_source", sa.Text(), nullable=True),
        sa.Column("insights_value_fr", sa.Text(), nullable=True),
        # Timestamps
        sa.Column("created_at", sa.DateTime(timezone=True), server_default=sa.func.now(), nullable=False),
        sa.Column(
            "updated_at",
            sa.DateTime(timezone=True),
            server_default=sa.func.now(),
            onupdate=sa.func.now(),
            nullable=False,
        ),
        # Primary key constraint
        sa.PrimaryKeyConstraint("company_id"),
        # Foreign key to companies table with CASCADE delete
        sa.ForeignKeyConstraint(["company_id"], ["companies.id"], ondelete="CASCADE"),
    )

    print("All 7 section tables created successfully!")


def downgrade():
    """Drop all 1:1 section tables."""

    print("Dropping 1:1 section tables...")

    # Drop tables in reverse order
    op.drop_table("company_press")
    op.drop_table("company_csr")
    op.drop_table("company_jobs")
    op.drop_table("company_products")
    op.drop_table("company_timeline")
    op.drop_table("company_digital")
    op.drop_table("company_profile")

    print("All 7 section tables dropped successfully!")
