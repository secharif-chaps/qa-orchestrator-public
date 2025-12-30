"""Tests for Company child models (1:N relationships).

These tests verify the core functionality of the company child models:
1. Child model creation with company_id
2. ENUM field validation for type columns
3. Cascade delete from company
4. Team member parent_id hierarchy (self-referential)
"""

import pytest
from datetime import datetime, timezone

from app.models.company import Company
from app.models.company_children import (
    ProductItemType,
    CsrInitiativeType,
    PressItemType,
    CompanyOnlineService,
    CompanySocialMediaAccount,
    CompanyTimelineEvent,
    CompanyProductItem,
    CompanyProductCategory,
    CompanyJobOffer,
    CompanyCsrInitiative,
    CompanyPressItem,
    CompanyTeamMember,
)


@pytest.fixture
def sample_company(db_session):
    """Create a sample company for testing."""
    company = Company(
        name="Test Company",
        website="https://testcompany.com",
        organization_id="org-uuid-123",
        owner_id="owner-uuid-456",
        owner_username="testuser"
    )
    db_session.add(company)
    db_session.commit()
    db_session.refresh(company)
    return company


class TestChildModelCreationWithCompanyId:
    """Test child model creation with company_id foreign key."""

    def test_create_online_service_with_company_id(self, db_session, sample_company):
        """Test creating CompanyOnlineService with all SourcedValue fields."""
        service = CompanyOnlineService(
            company_id=sample_company.id,
            name="Virtual Try-On",
            name_source="https://testcompany.com/features",
            name_value_fr=None,
            description="AR-powered virtual try-on for accessories",
            description_source="https://testcompany.com/features",
            description_value_fr=None
        )
        db_session.add(service)
        db_session.commit()
        db_session.refresh(service)

        assert service.id is not None
        assert service.company_id == sample_company.id
        assert service.name == "Virtual Try-On"
        assert service.description == "AR-powered virtual try-on for accessories"
        assert service.created_at is not None

    def test_create_social_media_account(self, db_session, sample_company):
        """Test creating CompanySocialMediaAccount."""
        account = CompanySocialMediaAccount(
            company_id=sample_company.id,
            platform="Instagram",
            platform_source="https://testcompany.com",
            url="https://instagram.com/testcompany",
            url_source="https://testcompany.com/contact"
        )
        db_session.add(account)
        db_session.commit()
        db_session.refresh(account)

        assert account.id is not None
        assert account.company_id == sample_company.id
        assert account.platform == "Instagram"
        assert account.url == "https://instagram.com/testcompany"

    def test_create_timeline_event(self, db_session, sample_company):
        """Test creating CompanyTimelineEvent with all fields."""
        event = CompanyTimelineEvent(
            company_id=sample_company.id,
            date="1987",
            date_source="https://wikipedia.org/wiki/TestCompany",
            title="Company Founded",
            title_source="https://wikipedia.org/wiki/TestCompany",
            title_value_fr=None,
            description="The company was established in Paris",
            description_source="https://wikipedia.org/wiki/TestCompany",
            description_value_fr=None,
            category="Foundation",
            category_source="Chaps-e",
            category_value_fr=None,
            location="Paris, France",
            location_source="https://wikipedia.org/wiki/TestCompany",
            impact="Became a leading company in the industry",
            impact_source="Chaps-e",
            impact_value_fr=None
        )
        db_session.add(event)
        db_session.commit()
        db_session.refresh(event)

        assert event.id is not None
        assert event.company_id == sample_company.id
        assert event.date == "1987"
        assert event.title == "Company Founded"
        assert event.category == "Foundation"
        assert event.location == "Paris, France"
        assert event.impact == "Became a leading company in the industry"

    def test_create_job_offer(self, db_session, sample_company):
        """Test creating CompanyJobOffer with all fields."""
        offer = CompanyJobOffer(
            company_id=sample_company.id,
            title="Senior Software Engineer",
            title_source="https://careers.testcompany.com/job/123",
            title_value_fr=None,
            location="Paris, France",
            location_source="https://careers.testcompany.com/job/123",
            department="Digital Technology",
            department_source="https://careers.testcompany.com/job/123",
            department_value_fr=None,
            description="Lead the development of e-commerce platform",
            description_source="https://careers.testcompany.com/job/123",
            description_value_fr=None,
            requirements="5+ years experience in Python, cloud architecture",
            requirements_source="https://careers.testcompany.com/job/123",
            requirements_value_fr=None,
            posted_date="2024-12-15",
            posted_date_source="https://careers.testcompany.com/job/123"
        )
        db_session.add(offer)
        db_session.commit()
        db_session.refresh(offer)

        assert offer.id is not None
        assert offer.company_id == sample_company.id
        assert offer.title == "Senior Software Engineer"
        assert offer.location == "Paris, France"
        assert offer.department == "Digital Technology"


class TestEnumFieldValidation:
    """Test ENUM field validation for type columns."""

    def test_create_product_item_with_range_type(self, db_session, sample_company):
        """Test creating CompanyProductItem with 'range' enum type."""
        item = CompanyProductItem(
            company_id=sample_company.id,
            type=ProductItemType.range,
            value="Leather Goods Collection",
            value_source="https://testcompany.com/products",
            value_value_fr=None
        )
        db_session.add(item)
        db_session.commit()
        db_session.refresh(item)

        assert item.id is not None
        assert item.type == ProductItemType.range
        assert item.type.value == "range"
        assert item.value == "Leather Goods Collection"

    def test_create_product_item_with_partner_brand_type(self, db_session, sample_company):
        """Test creating CompanyProductItem with 'partner_brand' enum type."""
        item = CompanyProductItem(
            company_id=sample_company.id,
            type=ProductItemType.partner_brand,
            value="Tiffany & Co.",
            value_source="https://testcompany.com/brands"
        )
        db_session.add(item)
        db_session.commit()
        db_session.refresh(item)

        assert item.type == ProductItemType.partner_brand
        assert item.type.value == "partner_brand"

    def test_create_product_item_with_private_label_type(self, db_session, sample_company):
        """Test creating CompanyProductItem with 'private_label' enum type."""
        item = CompanyProductItem(
            company_id=sample_company.id,
            type=ProductItemType.private_label,
            value="Maison Francis Kurkdjian",
            value_source="https://testcompany.com/brands"
        )
        db_session.add(item)
        db_session.commit()
        db_session.refresh(item)

        assert item.type == ProductItemType.private_label
        assert item.type.value == "private_label"

    def test_create_csr_initiative_with_sustainability_type(self, db_session, sample_company):
        """Test creating CompanyCsrInitiative with 'sustainability' enum type."""
        initiative = CompanyCsrInitiative(
            company_id=sample_company.id,
            type=CsrInitiativeType.sustainability,
            value="100% renewable energy in all stores by 2025",
            value_source="https://testcompany.com/sustainability",
            value_value_fr=None
        )
        db_session.add(initiative)
        db_session.commit()
        db_session.refresh(initiative)

        assert initiative.id is not None
        assert initiative.type == CsrInitiativeType.sustainability
        assert initiative.type.value == "sustainability"
        assert initiative.value == "100% renewable energy in all stores by 2025"

    def test_create_csr_initiative_with_all_types(self, db_session, sample_company):
        """Test creating CSR initiatives with all enum types."""
        types_to_test = [
            (CsrInitiativeType.responsibility, "Responsible business practices"),
            (CsrInitiativeType.charity, "Annual donation to foundations"),
            (CsrInitiativeType.community, "Local community programs"),
            (CsrInitiativeType.diversity, "50% women in leadership"),
            (CsrInitiativeType.ethics, "Strong ethical governance"),
            (CsrInitiativeType.awards, "CSR Excellence Award 2024"),
        ]

        for init_type, value in types_to_test:
            initiative = CompanyCsrInitiative(
                company_id=sample_company.id,
                type=init_type,
                value=value,
                value_source="Chaps-e"
            )
            db_session.add(initiative)

        db_session.commit()

        # Verify all were created
        initiatives = db_session.query(CompanyCsrInitiative).filter(
            CompanyCsrInitiative.company_id == sample_company.id
        ).all()
        assert len(initiatives) == 6

    def test_create_press_item_with_article_type(self, db_session, sample_company):
        """Test creating CompanyPressItem with 'article' enum type."""
        press = CompanyPressItem(
            company_id=sample_company.id,
            type=PressItemType.article,
            value="Company reports record Q4 earnings",
            value_source="https://reuters.com/article/company-earnings",
            value_value_fr=None
        )
        db_session.add(press)
        db_session.commit()
        db_session.refresh(press)

        assert press.id is not None
        assert press.type == PressItemType.article
        assert press.type.value == "article"

    def test_create_press_item_with_all_types(self, db_session, sample_company):
        """Test creating press items with all enum types."""
        types_to_test = [
            (PressItemType.press_release, "Official company statement"),
            (PressItemType.media_mention, "Featured in business magazine"),
            (PressItemType.award, "Best Company Award 2024"),
            (PressItemType.product_launch, "New product line announced"),
            (PressItemType.interview, "CEO interview on CNBC"),
            (PressItemType.financial, "Q3 financial results"),
            (PressItemType.partnership, "Strategic partnership with TechCo"),
        ]

        for press_type, value in types_to_test:
            press = CompanyPressItem(
                company_id=sample_company.id,
                type=press_type,
                value=value,
                value_source="https://news.com"
            )
            db_session.add(press)

        db_session.commit()

        # Verify all were created
        items = db_session.query(CompanyPressItem).filter(
            CompanyPressItem.company_id == sample_company.id
        ).all()
        assert len(items) == 7


class TestCascadeDeleteFromCompany:
    """Test that child records are deleted when company is deleted."""

    def test_cascade_delete_removes_online_services(self, db_session, sample_company):
        """Test that deleting company cascades to online services."""
        # Create online services
        services = [
            CompanyOnlineService(
                company_id=sample_company.id,
                name=f"Service {i}",
                name_source="https://example.com"
            )
            for i in range(3)
        ]
        db_session.add_all(services)
        db_session.commit()

        # Verify services exist
        count_before = db_session.query(CompanyOnlineService).filter(
            CompanyOnlineService.company_id == sample_company.id
        ).count()
        assert count_before == 3

        # Delete company
        db_session.delete(sample_company)
        db_session.commit()

        # Verify services are deleted
        count_after = db_session.query(CompanyOnlineService).filter(
            CompanyOnlineService.company_id == sample_company.id
        ).count()
        assert count_after == 0

    def test_cascade_delete_removes_all_child_types(self, db_session, sample_company):
        """Test that deleting company cascades to all child record types."""
        # Create one of each child type
        db_session.add(CompanyOnlineService(
            company_id=sample_company.id,
            name="Service",
            name_source="https://example.com"
        ))
        db_session.add(CompanySocialMediaAccount(
            company_id=sample_company.id,
            platform="Instagram",
            platform_source="https://example.com"
        ))
        db_session.add(CompanyTimelineEvent(
            company_id=sample_company.id,
            title="Event",
            title_source="https://example.com"
        ))
        db_session.add(CompanyProductItem(
            company_id=sample_company.id,
            type=ProductItemType.range,
            value="Product",
            value_source="https://example.com"
        ))
        db_session.add(CompanyProductCategory(
            company_id=sample_company.id,
            category_name="Category",
            items=["Item 1", "Item 2"]
        ))
        db_session.add(CompanyJobOffer(
            company_id=sample_company.id,
            title="Job",
            title_source="https://example.com"
        ))
        db_session.add(CompanyCsrInitiative(
            company_id=sample_company.id,
            type=CsrInitiativeType.sustainability,
            value="Initiative",
            value_source="https://example.com"
        ))
        db_session.add(CompanyPressItem(
            company_id=sample_company.id,
            type=PressItemType.article,
            value="Article",
            value_source="https://example.com"
        ))
        db_session.add(CompanyTeamMember(
            company_id=sample_company.id,
            first_name="John",
            last_name="Doe",
            first_name_source="https://example.com",
            last_name_source="https://example.com"
        ))
        db_session.commit()

        company_id = sample_company.id

        # Delete company
        db_session.delete(sample_company)
        db_session.commit()

        # Verify all child records are deleted
        assert db_session.query(CompanyOnlineService).filter_by(company_id=company_id).count() == 0
        assert db_session.query(CompanySocialMediaAccount).filter_by(company_id=company_id).count() == 0
        assert db_session.query(CompanyTimelineEvent).filter_by(company_id=company_id).count() == 0
        assert db_session.query(CompanyProductItem).filter_by(company_id=company_id).count() == 0
        assert db_session.query(CompanyProductCategory).filter_by(company_id=company_id).count() == 0
        assert db_session.query(CompanyJobOffer).filter_by(company_id=company_id).count() == 0
        assert db_session.query(CompanyCsrInitiative).filter_by(company_id=company_id).count() == 0
        assert db_session.query(CompanyPressItem).filter_by(company_id=company_id).count() == 0
        assert db_session.query(CompanyTeamMember).filter_by(company_id=company_id).count() == 0


class TestTeamMemberHierarchy:
    """Test team member parent_id hierarchy (self-referential relationship)."""

    def test_create_team_member_as_ceo_with_null_parent(self, db_session, sample_company):
        """Test creating CEO team member with parent_id=NULL."""
        ceo = CompanyTeamMember(
            company_id=sample_company.id,
            parent_id=None,
            position="Chairman and CEO",
            position_source="https://testcompany.com/leadership",
            position_value_fr=None,
            first_name="Bernard",
            first_name_source="https://testcompany.com/leadership",
            last_name="Arnault",
            last_name_source="https://testcompany.com/leadership",
            linkedin_url="https://linkedin.com/in/bernard-arnault",
            linkedin_url_source="https://linkedin.com"
        )
        db_session.add(ceo)
        db_session.commit()
        db_session.refresh(ceo)

        assert ceo.id is not None
        assert ceo.parent_id is None
        assert ceo.position == "Chairman and CEO"
        assert ceo.first_name == "Bernard"
        assert ceo.last_name == "Arnault"
        assert ceo.parent is None  # No parent relationship

    def test_create_team_member_with_parent_reference(self, db_session, sample_company):
        """Test creating team member that reports to CEO."""
        # Create CEO first
        ceo = CompanyTeamMember(
            company_id=sample_company.id,
            parent_id=None,
            position="CEO",
            position_source="https://testcompany.com/leadership",
            first_name="John",
            first_name_source="https://testcompany.com/leadership",
            last_name="Smith",
            last_name_source="https://testcompany.com/leadership"
        )
        db_session.add(ceo)
        db_session.commit()
        db_session.refresh(ceo)

        # Create direct report
        director = CompanyTeamMember(
            company_id=sample_company.id,
            parent_id=ceo.id,
            position="Managing Director",
            position_source="https://testcompany.com/leadership",
            first_name="Jane",
            first_name_source="https://testcompany.com/leadership",
            last_name="Doe",
            last_name_source="https://testcompany.com/leadership"
        )
        db_session.add(director)
        db_session.commit()
        db_session.refresh(director)

        assert director.parent_id == ceo.id
        assert director.parent is not None
        assert director.parent.id == ceo.id
        assert director.parent.first_name == "John"

    def test_team_hierarchy_three_levels(self, db_session, sample_company):
        """Test creating 3-level team hierarchy: CEO -> Director -> Manager."""
        # Level 1: CEO
        ceo = CompanyTeamMember(
            company_id=sample_company.id,
            parent_id=None,
            position="CEO",
            position_source="https://testcompany.com",
            first_name="CEO",
            first_name_source="https://testcompany.com",
            last_name="Person",
            last_name_source="https://testcompany.com"
        )
        db_session.add(ceo)
        db_session.commit()
        db_session.refresh(ceo)

        # Level 2: Director reports to CEO
        director = CompanyTeamMember(
            company_id=sample_company.id,
            parent_id=ceo.id,
            position="Director",
            position_source="https://testcompany.com",
            first_name="Director",
            first_name_source="https://testcompany.com",
            last_name="Person",
            last_name_source="https://testcompany.com"
        )
        db_session.add(director)
        db_session.commit()
        db_session.refresh(director)

        # Level 3: Manager reports to Director
        manager = CompanyTeamMember(
            company_id=sample_company.id,
            parent_id=director.id,
            position="Manager",
            position_source="https://testcompany.com",
            first_name="Manager",
            first_name_source="https://testcompany.com",
            last_name="Person",
            last_name_source="https://testcompany.com"
        )
        db_session.add(manager)
        db_session.commit()
        db_session.refresh(manager)

        # Verify hierarchy
        assert manager.parent_id == director.id
        assert manager.parent.parent_id == ceo.id
        assert manager.parent.parent.parent_id is None

        # Verify we can traverse up the hierarchy
        assert manager.parent.position == "Director"
        assert manager.parent.parent.position == "CEO"

    def test_subordinates_relationship(self, db_session, sample_company):
        """Test accessing subordinates through relationship."""
        # Create CEO
        ceo = CompanyTeamMember(
            company_id=sample_company.id,
            parent_id=None,
            position="CEO",
            position_source="https://testcompany.com",
            first_name="CEO",
            first_name_source="https://testcompany.com",
            last_name="Name",
            last_name_source="https://testcompany.com"
        )
        db_session.add(ceo)
        db_session.commit()
        db_session.refresh(ceo)

        # Create two direct reports
        for i in range(2):
            report = CompanyTeamMember(
                company_id=sample_company.id,
                parent_id=ceo.id,
                position=f"VP {i+1}",
                position_source="https://testcompany.com",
                first_name=f"VP{i+1}",
                first_name_source="https://testcompany.com",
                last_name="Name",
                last_name_source="https://testcompany.com"
            )
            db_session.add(report)

        db_session.commit()
        db_session.refresh(ceo)

        # Verify CEO has subordinates
        assert len(ceo.subordinates) == 2
        positions = [s.position for s in ceo.subordinates]
        assert "VP 1" in positions
        assert "VP 2" in positions

    def test_delete_parent_sets_null_for_subordinates(self, db_session, sample_company):
        """Test that deleting parent sets parent_id=NULL for subordinates (ON DELETE SET NULL)."""
        # Create CEO
        ceo = CompanyTeamMember(
            company_id=sample_company.id,
            parent_id=None,
            position="CEO",
            position_source="https://testcompany.com",
            first_name="CEO",
            first_name_source="https://testcompany.com",
            last_name="Name",
            last_name_source="https://testcompany.com"
        )
        db_session.add(ceo)
        db_session.commit()
        db_session.refresh(ceo)
        ceo_id = ceo.id

        # Create direct report
        director = CompanyTeamMember(
            company_id=sample_company.id,
            parent_id=ceo.id,
            position="Director",
            position_source="https://testcompany.com",
            first_name="Director",
            first_name_source="https://testcompany.com",
            last_name="Name",
            last_name_source="https://testcompany.com"
        )
        db_session.add(director)
        db_session.commit()
        db_session.refresh(director)
        director_id = director.id

        # Verify parent_id is set
        assert director.parent_id == ceo_id

        # Delete CEO directly (bypassing cascade)
        db_session.execute(
            CompanyTeamMember.__table__.delete().where(
                CompanyTeamMember.id == ceo_id
            )
        )
        db_session.commit()

        # Refresh director and verify parent_id is now NULL
        director_after = db_session.query(CompanyTeamMember).filter(
            CompanyTeamMember.id == director_id
        ).first()
        assert director_after is not None
        assert director_after.parent_id is None


class TestProductCategoryWithArrays:
    """Test product category with ARRAY fields."""

    def test_create_product_category_with_items_array(self, db_session, sample_company):
        """Test creating CompanyProductCategory with items TEXT[] array."""
        category = CompanyProductCategory(
            company_id=sample_company.id,
            category_name="Fashion",
            category_name_value_fr=None,
            items=["Clothing", "Accessories", "Footwear"],
            items_value_fr=None
        )
        db_session.add(category)
        db_session.commit()
        db_session.refresh(category)

        assert category.id is not None
        assert category.category_name == "Fashion"
        assert category.items == ["Clothing", "Accessories", "Footwear"]
        assert len(category.items) == 3

    def test_create_product_category_with_translations(self, db_session, sample_company):
        """Test creating CompanyProductCategory with both items and translations."""
        category = CompanyProductCategory(
            company_id=sample_company.id,
            category_name="Electronics",
            category_name_value_fr="Electronique",
            items=["Phones", "Laptops", "Tablets"],
            items_value_fr=["Telephones", "Ordinateurs portables", "Tablettes"]
        )
        db_session.add(category)
        db_session.commit()
        db_session.refresh(category)

        assert category.items == ["Phones", "Laptops", "Tablets"]
        assert category.items_value_fr == ["Telephones", "Ordinateurs portables", "Tablettes"]
        assert len(category.items) == len(category.items_value_fr)


class TestRelationshipsFromCompany:
    """Test accessing child records from Company model."""

    def test_company_to_child_relationships(self, db_session, sample_company):
        """Test navigating from Company to all child collections."""
        # Add one of each child type
        db_session.add(CompanyOnlineService(
            company_id=sample_company.id,
            name="Service 1",
            name_source="https://example.com"
        ))
        db_session.add(CompanySocialMediaAccount(
            company_id=sample_company.id,
            platform="Twitter",
            platform_source="https://example.com"
        ))
        db_session.add(CompanyTimelineEvent(
            company_id=sample_company.id,
            title="Event 1",
            title_source="https://example.com"
        ))
        db_session.add(CompanyProductItem(
            company_id=sample_company.id,
            type=ProductItemType.range,
            value="Product 1",
            value_source="https://example.com"
        ))
        db_session.add(CompanyProductCategory(
            company_id=sample_company.id,
            category_name="Category 1",
            items=["Item 1"]
        ))
        db_session.add(CompanyJobOffer(
            company_id=sample_company.id,
            title="Job 1",
            title_source="https://example.com"
        ))
        db_session.add(CompanyCsrInitiative(
            company_id=sample_company.id,
            type=CsrInitiativeType.charity,
            value="Initiative 1",
            value_source="https://example.com"
        ))
        db_session.add(CompanyPressItem(
            company_id=sample_company.id,
            type=PressItemType.award,
            value="Award 1",
            value_source="https://example.com"
        ))
        db_session.add(CompanyTeamMember(
            company_id=sample_company.id,
            first_name="Member",
            last_name="One",
            first_name_source="https://example.com",
            last_name_source="https://example.com"
        ))
        db_session.commit()
        db_session.refresh(sample_company)

        # Verify all relationships are lists (not single objects)
        assert isinstance(sample_company.online_services, list)
        assert isinstance(sample_company.social_media_accounts, list)
        assert isinstance(sample_company.timeline_events, list)
        assert isinstance(sample_company.product_items, list)
        assert isinstance(sample_company.product_categories, list)
        assert isinstance(sample_company.job_offers, list)
        assert isinstance(sample_company.csr_initiatives, list)
        assert isinstance(sample_company.press_items, list)
        assert isinstance(sample_company.team_members, list)

        # Verify counts
        assert len(sample_company.online_services) == 1
        assert len(sample_company.social_media_accounts) == 1
        assert len(sample_company.timeline_events) == 1
        assert len(sample_company.product_items) == 1
        assert len(sample_company.product_categories) == 1
        assert len(sample_company.job_offers) == 1
        assert len(sample_company.csr_initiatives) == 1
        assert len(sample_company.press_items) == 1
        assert len(sample_company.team_members) == 1

    def test_empty_child_relationships_are_empty_lists(self, db_session, sample_company):
        """Test that empty 1:N relationships return empty lists, not None."""
        db_session.refresh(sample_company)

        # All relationships should be empty lists, not None
        assert sample_company.online_services == []
        assert sample_company.social_media_accounts == []
        assert sample_company.timeline_events == []
        assert sample_company.product_items == []
        assert sample_company.product_categories == []
        assert sample_company.job_offers == []
        assert sample_company.csr_initiatives == []
        assert sample_company.press_items == []
        assert sample_company.team_members == []
