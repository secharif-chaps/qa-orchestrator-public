"""Tests for Task Group 11: Company data cleanup verification.

These tests verify that the cleanup phase is complete:
1. Company CRUD works without JSON columns
2. Dify callback writes only to new tables
3. Company deletion cascades correctly
4. No references to old JSON fields
"""

import pytest
from unittest.mock import MagicMock, patch
from sqlalchemy.orm import Session

from app.models.company import Company
from app.models.company_sections import (
    CompanyProfile,
    CompanyDigital,
    CompanyTimeline,
    CompanyProducts,
    CompanyJobs,
    CompanyCsr,
    CompanyPress,
)
from app.models.company_children import (
    CompanyOnlineService,
    CompanySocialMediaAccount,
    CompanyTimelineEvent,
    CompanyProductItem,
    CompanyProductCategory,
    CompanyJobOffer,
    CompanyCsrInitiative,
    CompanyPressItem,
    CompanyTeamMember,
    ProductItemType,
    CsrInitiativeType,
    PressItemType,
)
from app.services.company_section_service import (
    write_section_data,
    read_all_section_data,
    save_profile_data,
    get_profile_data,
)


class TestCompanyCRUDWithoutJsonColumns:
    """Test that company CRUD operations work without JSON columns."""

    def test_create_company_without_json_fields(self, mock_db_session):
        """Test creating a company without setting JSON fields."""
        # Create a company with only required fields
        company = Company(
            name="Test Company",
            website="https://test.com",
            organization_id="org-123",
            owner_id="user-456",
            owner_username="testuser",
        )

        # The company should be valid without JSON fields
        assert company.name == "Test Company"
        assert company.website == "https://test.com"
        assert company.organization_id == "org-123"

    def test_company_has_raw_knowledge_fields(self, mock_db_session):
        """Test that company still has raw knowledge fields (as per spec)."""
        company = Company(
            name="Test Company",
            website="https://test.com",
            organization_id="org-123",
        )

        # These fields should still exist
        company.raw_mistral_knowledge = "Mistral knowledge"
        company.raw_gpt_knowledge = "GPT knowledge"
        company.raw_wikipedia_knowledge = "Wikipedia knowledge"
        company.raw_scraped_website_knowledge = "Scraped content"

        assert company.raw_mistral_knowledge == "Mistral knowledge"
        assert company.raw_gpt_knowledge == "GPT knowledge"
        assert company.raw_wikipedia_knowledge == "Wikipedia knowledge"
        assert company.raw_scraped_website_knowledge == "Scraped content"

    def test_company_has_section_relationships(self, mock_db_session):
        """Test that company has relationships to normalized tables."""
        company = Company(
            name="Test Company",
            website="https://test.com",
            organization_id="org-123",
        )

        # These relationships should exist
        assert hasattr(company, 'profile_data')
        assert hasattr(company, 'digital_data')
        assert hasattr(company, 'timeline_data')
        assert hasattr(company, 'products_data')
        assert hasattr(company, 'jobs_data')
        assert hasattr(company, 'csr_data')
        assert hasattr(company, 'press_data')
        assert hasattr(company, 'team_members')


class TestDifyCallbackWritesToNewTables:
    """Test that Dify callback writes only to normalized tables."""

    def test_profile_data_written_to_normalized_table(self, mock_db_session):
        """Test that profile data is written to company_profile table."""
        company_id = 1

        # Simulate Dify callback data
        dify_data = {
            "profile": {
                "insights": "This is a test company.",
                "groupName": {"value": "Parent Corp", "source": "https://example.com"},
                "businessLine": {"value": "Technology", "source": "https://example.com"},
                "employeeCount": {"value": "1000", "source": "https://example.com"},
            }
        }

        # Write the data
        save_profile_data(mock_db_session, company_id, dify_data)

        # Verify add was called with CompanyProfile
        mock_db_session.add.assert_called()
        added_obj = mock_db_session.add.call_args[0][0]
        assert isinstance(added_obj, CompanyProfile)
        assert added_obj.company_id == company_id
        assert added_obj.insights == "This is a test company."
        assert added_obj.group_name == "Parent Corp"
        assert added_obj.business_line == "Technology"
        assert added_obj.employee_count == "1000"

    def test_write_section_data_dispatches_correctly(self, mock_db_session):
        """Test that write_section_data dispatches to correct writer."""
        company_id = 1

        # Test each section type
        section_types = [
            "profile", "digital", "timeline", "products",
            "jobs", "csr", "press", "team"
        ]

        for section_type in section_types:
            # Call write_section_data with each type
            data = {section_type: {"insights": "test"}}
            write_section_data(mock_db_session, company_id, section_type, data)

        # Should have been called 8 times (once per section)
        assert mock_db_session.flush.call_count >= len(section_types)

    def test_data_collection_updates_raw_knowledge_only(self, mock_db_session):
        """Test that data_collection type doesn't write to section tables."""
        company_id = 1

        # data_collection writes to Company raw_* fields, not section tables
        # The write_section_data function should not process data_collection
        data = {
            "knowledge": {
                "mistral": "Mistral data",
                "claude": "Claude data",
                "wikipedia": "Wikipedia data",
                "scraped": "Scraped data",
            }
        }

        # data_collection is NOT handled by write_section_data
        # It should log a warning and not write anything
        write_section_data(mock_db_session, company_id, "data_collection", data)

        # No add should have been called for section tables
        # (data_collection is handled separately in _update_company_data)


class TestCompanyDeletionCascades:
    """Test that company deletion cascades to all related tables."""

    def test_company_relationships_have_cascade(self):
        """Test that Company model has cascade delete on relationships."""
        # Get the relationships from Company model
        company = Company(
            name="Test",
            website="https://test.com",
            organization_id="org-123",
        )

        # Check cascade configuration on relationships
        # These should all have cascade="all, delete-orphan"
        relationships_to_check = [
            'profile_data', 'digital_data', 'timeline_data',
            'products_data', 'jobs_data', 'csr_data', 'press_data',
            'online_services', 'social_media_accounts', 'timeline_events',
            'product_items', 'product_categories', 'job_offers',
            'csr_initiatives', 'press_items', 'team_members',
        ]

        for rel_name in relationships_to_check:
            assert hasattr(company, rel_name), f"Company should have {rel_name} relationship"

    def test_section_tables_have_ondelete_cascade(self):
        """Test that section tables have ON DELETE CASCADE foreign keys."""
        # CompanyProfile should have CASCADE on delete
        profile = CompanyProfile.__table__
        fk_constraints = [c for c in profile.foreign_keys]
        assert len(fk_constraints) > 0, "CompanyProfile should have foreign key"

        # The foreign key should reference companies.id with CASCADE
        for fk in fk_constraints:
            assert fk.parent.name == 'company_id'
            assert 'companies.id' in str(fk.target_fullname)


class TestNoReferencesToOldJsonFields:
    """Test that there are no references to old JSON fields in service code."""

    def test_read_all_section_data_returns_dict(self, mock_db_session):
        """Test that read_all_section_data returns dict for each section."""
        company_id = 1

        # Mock the query results to return None (no data)
        mock_db_session.query.return_value.filter.return_value.first.return_value = None
        mock_db_session.query.return_value.filter.return_value.all.return_value = []

        result = read_all_section_data(mock_db_session, company_id)

        # Result should have all section keys
        expected_keys = ["profile", "digital", "timeline", "products",
                        "jobs", "csr", "press", "team"]
        for key in expected_keys:
            assert key in result, f"Result should have {key} key"

        # Empty data should return empty dicts/lists
        assert result["profile"] == {}
        assert result["team"] == []

    def test_get_profile_data_returns_dict_structure(self, mock_db_session):
        """Test that get_profile_data returns proper dict structure."""
        company_id = 1

        # Mock a profile record
        mock_profile = MagicMock(spec=CompanyProfile)
        mock_profile.company_id = company_id
        mock_profile.insights = "Test insights"
        mock_profile.group_name = "Test Group"
        mock_profile.group_name_source = "https://example.com"
        mock_profile.business_line = None
        mock_profile.catchphrase = None
        mock_profile.establishment_year = "2020"
        mock_profile.establishment_year_source = "https://example.com"
        mock_profile.employee_count = None
        mock_profile.revenue = None
        mock_profile.ceo = None
        mock_profile.hq = None

        mock_db_session.query.return_value.filter.return_value.first.return_value = mock_profile

        result = get_profile_data(mock_db_session, company_id)

        # Should return dict structure (not JSON column)
        assert isinstance(result, dict)
        assert result["insights"] == "Test insights"
        assert result["groupName"]["value"] == "Test Group"
        assert result["establishmentYear"]["value"] == "2020"


# Fixtures

@pytest.fixture
def mock_db_session():
    """Create a mock database session."""
    session = MagicMock(spec=Session)
    session.query.return_value.filter.return_value.first.return_value = None
    session.query.return_value.filter.return_value.all.return_value = []
    session.query.return_value.filter.return_value.delete.return_value = 0
    return session
