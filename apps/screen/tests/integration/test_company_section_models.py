"""Tests for Company section models (1:1 relationships).

These tests verify the core functionality of the company section models:
1. Model creation with all fields
2. Relationship to Company model (bidirectional)
3. uselist=False enforcement on 1:1 relationships
4. Timestamps auto-populate on creation
"""

from datetime import UTC, datetime

import pytest

from app.models.company import Company
from app.models.company_sections import (
    CompanyCsr,
    CompanyDigital,
    CompanyJobs,
    CompanyPress,
    CompanyProducts,
    CompanyProfile,
    CompanyTimeline,
)

pytestmark = pytest.mark.integration


@pytest.fixture
def sample_company(db_session):
    """Create a sample company for testing."""
    company = Company(
        name="Test Company",
        website="https://testcompany.com",
        organization_id="org-uuid-123",
        owner_id="owner-uuid-456",
        owner_username="testuser",
    )
    db_session.add(company)
    db_session.commit()
    db_session.refresh(company)
    return company


class TestSectionModelCreation:
    """Test section model creation with all fields."""

    def test_create_company_profile_with_all_fields(self, db_session, sample_company):
        """Test creating CompanyProfile with all SourcedValue fields."""
        profile = CompanyProfile(
            company_id=sample_company.id,
            insights="AI-generated company summary",
            insights_source="Chaps-e",
            group_name="Test Group Inc",
            group_name_source="https://wikipedia.org/wiki/TestGroup",
            business_line="Technology consulting",
            business_line_source="https://testcompany.com/about",
            catchphrase="Innovation through technology",
            catchphrase_source="https://testcompany.com",
            establishment_year="2010",
            establishment_year_source="https://testcompany.com/about",
            employee_count="500",
            employee_count_source="https://linkedin.com/company/testcompany",
            revenue="50M USD",
            revenue_source="https://testcompany.com/investors",
            ceo="John Doe",
            ceo_source="https://linkedin.com/in/johndoe",
            hq="Paris, France",
            hq_source="https://testcompany.com/contact",
        )
        db_session.add(profile)
        db_session.commit()
        db_session.refresh(profile)

        assert profile.company_id == sample_company.id
        assert profile.insights == "AI-generated company summary"
        assert profile.insights_source == "Chaps-e"
        assert profile.group_name == "Test Group Inc"
        assert profile.business_line == "Technology consulting"
        assert profile.establishment_year == "2010"
        assert profile.employee_count == "500"
        assert profile.revenue == "50M USD"
        assert profile.ceo == "John Doe"
        assert profile.hq == "Paris, France"

    def test_create_company_digital_with_all_fields(self, db_session, sample_company):
        """Test creating CompanyDigital with all strategy fields."""
        digital = CompanyDigital(
            company_id=sample_company.id,
            insights="Strong digital presence",
            insights_source="Chaps-e",
            overall_strategy="Omnichannel approach",
            overall_strategy_source="https://testcompany.com/digital",
            digital_transformation="Cloud-first initiative",
            digital_transformation_source="https://news.com/article",
            ecommerce_capabilities="Full e-commerce platform",
            ecommerce_capabilities_source="https://testcompany.com/shop",
            mobile_strategy="Native iOS and Android apps",
            mobile_strategy_source="https://testcompany.com/apps",
            digital_marketing_approach="Social media and SEO",
            digital_marketing_approach_source="Chaps-e",
            loyalty_program="Premium membership program",
            loyalty_program_source="https://testcompany.com/vip",
        )
        db_session.add(digital)
        db_session.commit()
        db_session.refresh(digital)

        assert digital.company_id == sample_company.id
        assert digital.insights == "Strong digital presence"
        assert digital.overall_strategy == "Omnichannel approach"
        assert digital.digital_transformation == "Cloud-first initiative"
        assert digital.ecommerce_capabilities == "Full e-commerce platform"
        assert digital.mobile_strategy == "Native iOS and Android apps"
        assert digital.digital_marketing_approach == "Social media and SEO"
        assert digital.loyalty_program == "Premium membership program"

    def test_create_company_jobs_with_structured_insights(self, db_session, sample_company):
        """Test creating CompanyJobs with structured insights fields."""
        jobs = CompanyJobs(
            company_id=sample_company.id,
            insights_total_openings=150,
            insights_total_openings_source="https://careers.testcompany.com",
            insights_top_departments="Engineering, Marketing, Sales",
            insights_top_departments_source="https://careers.testcompany.com",
            insights_hiring_focus="AI and machine learning specialists",
            insights_hiring_focus_source="Chaps-e",
            insights_growth_indicators="30% YoY hiring increase",
            insights_growth_indicators_source="Chaps-e",
        )
        db_session.add(jobs)
        db_session.commit()
        db_session.refresh(jobs)

        assert jobs.company_id == sample_company.id
        assert jobs.insights_total_openings == 150
        assert jobs.insights_top_departments == "Engineering, Marketing, Sales"
        assert jobs.insights_hiring_focus == "AI and machine learning specialists"
        assert jobs.insights_growth_indicators == "30% YoY hiring increase"


class TestSectionModelRelationships:
    """Test bidirectional relationships between Company and section models."""

    def test_company_to_profile_relationship(self, db_session, sample_company):
        """Test navigating from Company to CompanyProfile."""
        profile = CompanyProfile(company_id=sample_company.id, insights="Profile insights", insights_source="Chaps-e")
        db_session.add(profile)
        db_session.commit()
        db_session.refresh(sample_company)

        # Navigate from company to profile
        assert sample_company.profile_data is not None
        assert sample_company.profile_data.insights == "Profile insights"

    def test_profile_to_company_relationship(self, db_session, sample_company):
        """Test navigating from CompanyProfile to Company."""
        profile = CompanyProfile(company_id=sample_company.id, insights="Profile insights", insights_source="Chaps-e")
        db_session.add(profile)
        db_session.commit()
        db_session.refresh(profile)

        # Navigate from profile to company
        assert profile.company is not None
        assert profile.company.id == sample_company.id
        assert profile.company.name == "Test Company"

    def test_all_section_relationships_work(self, db_session, sample_company):
        """Test all 7 section models have working bidirectional relationships."""
        # Create all sections
        profile = CompanyProfile(company_id=sample_company.id, insights="Profile")
        digital = CompanyDigital(company_id=sample_company.id, insights="Digital")
        timeline = CompanyTimeline(company_id=sample_company.id, insights="Timeline")
        products = CompanyProducts(company_id=sample_company.id, insights="Products")
        jobs = CompanyJobs(company_id=sample_company.id, insights_total_openings=10)
        csr = CompanyCsr(company_id=sample_company.id, insights="CSR")
        press = CompanyPress(company_id=sample_company.id, insights="Press")

        db_session.add_all([profile, digital, timeline, products, jobs, csr, press])
        db_session.commit()
        db_session.refresh(sample_company)

        # Verify all relationships from Company side
        assert sample_company.profile_data is not None
        assert sample_company.digital_data is not None
        assert sample_company.timeline_data is not None
        assert sample_company.products_data is not None
        assert sample_company.jobs_data is not None
        assert sample_company.csr_data is not None
        assert sample_company.press_data is not None

        # Verify reverse relationships
        assert profile.company.id == sample_company.id
        assert digital.company.id == sample_company.id
        assert timeline.company.id == sample_company.id
        assert products.company.id == sample_company.id
        assert jobs.company.id == sample_company.id
        assert csr.company.id == sample_company.id
        assert press.company.id == sample_company.id


class TestUselistFalseEnforcement:
    """Test that uselist=False properly enforces 1:1 relationships."""

    def test_profile_data_is_single_object_not_list(self, db_session, sample_company):
        """Test that profile_data returns a single object, not a list."""
        profile = CompanyProfile(company_id=sample_company.id, insights="Single profile")
        db_session.add(profile)
        db_session.commit()
        db_session.refresh(sample_company)

        # Should be a single object, not a list
        assert not isinstance(sample_company.profile_data, list)
        assert isinstance(sample_company.profile_data, CompanyProfile)

    def test_digital_data_is_single_object_not_list(self, db_session, sample_company):
        """Test that digital_data returns a single object, not a list."""
        digital = CompanyDigital(company_id=sample_company.id, insights="Single digital")
        db_session.add(digital)
        db_session.commit()
        db_session.refresh(sample_company)

        # Should be a single object, not a list
        assert not isinstance(sample_company.digital_data, list)
        assert isinstance(sample_company.digital_data, CompanyDigital)

    def test_empty_relationship_is_none_not_empty_list(self, db_session, sample_company):
        """Test that empty 1:1 relationship is None, not empty list."""
        db_session.refresh(sample_company)

        # When no profile exists, should be None, not []
        assert sample_company.profile_data is None
        assert sample_company.digital_data is None
        assert sample_company.timeline_data is None
        assert sample_company.products_data is None
        assert sample_company.jobs_data is None
        assert sample_company.csr_data is None
        assert sample_company.press_data is None


class TestTimestampsAutoPopulate:
    """Test that created_at and updated_at timestamps auto-populate."""

    def test_created_at_auto_populates_on_insert(self, db_session, sample_company):
        """Test that created_at is automatically set on insert."""
        profile = CompanyProfile(company_id=sample_company.id, insights="Test insights")
        db_session.add(profile)
        db_session.commit()
        db_session.refresh(profile)

        # Verify timestamp exists and is recent (within last minute)
        assert profile.created_at is not None
        now = datetime.now(UTC)
        time_diff = abs((now - profile.created_at.replace(tzinfo=UTC)).total_seconds())
        assert time_diff < 60, f"created_at timestamp is not recent: {time_diff}s ago"

    def test_updated_at_auto_populates_on_insert(self, db_session, sample_company):
        """Test that updated_at is automatically set on insert."""
        digital = CompanyDigital(company_id=sample_company.id, insights="Test digital")
        db_session.add(digital)
        db_session.commit()
        db_session.refresh(digital)

        # Verify timestamp exists and is recent (within last minute)
        assert digital.updated_at is not None
        now = datetime.now(UTC)
        time_diff = abs((now - digital.updated_at.replace(tzinfo=UTC)).total_seconds())
        assert time_diff < 60, f"updated_at timestamp is not recent: {time_diff}s ago"

    def test_all_section_models_have_timestamps(self, db_session, sample_company):
        """Test all 7 section models auto-populate timestamps."""
        sections = [
            CompanyProfile(company_id=sample_company.id, insights="Profile"),
            CompanyDigital(company_id=sample_company.id, insights="Digital"),
            CompanyTimeline(company_id=sample_company.id, insights="Timeline"),
            CompanyProducts(company_id=sample_company.id, insights="Products"),
            CompanyJobs(company_id=sample_company.id, insights_total_openings=5),
            CompanyCsr(company_id=sample_company.id, insights="CSR"),
            CompanyPress(company_id=sample_company.id, insights="Press"),
        ]

        db_session.add_all(sections)
        db_session.commit()

        for section in sections:
            db_session.refresh(section)
            assert section.created_at is not None, f"{section.__class__.__name__} missing created_at"
            assert section.updated_at is not None, f"{section.__class__.__name__} missing updated_at"
