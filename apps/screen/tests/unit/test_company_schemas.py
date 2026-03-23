"""Unit tests for company Pydantic schemas.

Tests the SourcedValue generic schema, section schemas, and validation
logic for the normalized company data structure.
"""

from app.schemas.company_schemas import (
    # Complete
    CompanySectionsResponse,
    CsrCreate,
    CsrInitiativeResponse,
    CsrInitiativeTypeEnum,
    CsrResponse,
    # Digital
    DigitalResponse,
    DigitalStrategyResponse,
    # Jobs
    JobsResponse,
    PressItemResponse,
    PressItemTypeEnum,
    PressResponse,
    ProductItemTypeEnum,
    ProductsResponse,
    ProfileCreate,
    # Profile
    ProfileResponse,
    # Core generic
    SourcedValue,
    TeamCreate,
    TeamMemberResponse,
    TimelineCreate,
    TimelineEventResponse,
    TimelineResponse,
)


class TestSourcedValueGeneric:
    """Tests for SourcedValue[T] generic schema."""

    def test_sourced_value_with_string(self):
        """Test SourcedValue works with string type."""
        sv = SourcedValue[str](value="LVMH", source="https://wikipedia.org/wiki/LVMH")
        assert sv.value == "LVMH"
        assert sv.source == "https://wikipedia.org/wiki/LVMH"
        assert sv.favicon is None

    def test_sourced_value_with_int(self):
        """Test SourcedValue works with int type."""
        sv = SourcedValue[int](value=250, source="https://careers.company.com")
        assert sv.value == 250
        assert sv.source == "https://careers.company.com"

    def test_sourced_value_with_list(self):
        """Test SourcedValue works with list type."""
        departments = ["Retail", "Digital", "Marketing"]
        sv = SourcedValue[list[str]](value=departments, source="https://careers.company.com")
        assert sv.value == departments
        assert len(sv.value) == 3

    def test_sourced_value_with_optional_fields(self):
        """Test SourcedValue with all optional fields populated."""
        sv = SourcedValue[str](
            value="Luxury goods",
            source="https://company.com/about",
            favicon="https://company.com/favicon.ico",
        )
        assert sv.value == "Luxury goods"
        assert sv.favicon == "https://company.com/favicon.ico"


class TestSourceValidation:
    """Tests for source field validation."""

    def test_source_accepts_https_url(self):
        """Test source accepts HTTPS URLs."""
        sv = SourcedValue[str](value="test", source="https://example.com/page")
        assert sv.source == "https://example.com/page"

    def test_source_accepts_http_url(self):
        """Test source accepts HTTP URLs."""
        sv = SourcedValue[str](value="test", source="http://example.com/page")
        assert sv.source == "http://example.com/page"

    def test_source_accepts_chaps_e(self):
        """Test source accepts Chaps-e (AI-generated marker)."""
        sv = SourcedValue[str](value="AI insight", source="Chaps-e")
        assert sv.source == "Chaps-e"

    def test_source_accepts_known_tools(self):
        """Test source accepts known tool names."""
        known_tools = ["linkedin", "glassdoor", "mistral", "claude", "perplexity", "wikipedia"]
        for tool in known_tools:
            sv = SourcedValue[str](value="test", source=tool)
            assert sv.source == tool

    def test_source_lenient_accepts_unknown_string(self):
        """Test source is lenient and accepts unknown strings."""
        sv = SourcedValue[str](value="test", source="unknown_source")
        assert sv.source == "unknown_source"

    def test_source_preserves_case(self):
        """Test source preserves original case."""
        sv = SourcedValue[str](value="test", source="LinkedIn")
        assert sv.source == "LinkedIn"


class TestNestedSchemaSerialization:
    """Tests for nested schema serialization."""

    def test_profile_response_nested_sourced_values(self):
        """Test ProfileResponse with nested SourcedValue fields."""
        profile = ProfileResponse(
            groupName=SourcedValue[str](value="LVMH", source="https://wikipedia.org"),
            businessLine=SourcedValue[str](value="Luxury goods", source="https://company.com"),
            ceo=SourcedValue[str](value="Bernard Arnault", source="https://wikipedia.org"),
        )
        assert profile.groupName.value == "LVMH"

    def test_profile_response_serialization(self):
        """Test ProfileResponse serializes to dict correctly."""
        profile = ProfileResponse(
            groupName=SourcedValue[str](value="LVMH", source="https://wiki.org"),
            employeeCount=SourcedValue[str](value="196,000", source="https://company.com"),
        )
        data = profile.model_dump()
        assert data["groupName"]["value"] == "LVMH"
        assert data["employeeCount"]["value"] == "196,000"

    def test_team_member_recursive_structure(self):
        """Test TeamMemberResponse handles recursive subordinates."""
        ceo = TeamMemberResponse(
            position="CEO",
            firstName="Bernard",
            lastName="Arnault",
            linkedinUrl="https://linkedin.com/in/bernard",
            subordinates=[
                TeamMemberResponse(
                    position="CFO",
                    firstName="Jean-Jacques",
                    lastName="Guiony",
                    subordinates=[TeamMemberResponse(position="VP Finance", firstName="Marie", lastName="Dupont")],
                ),
                TeamMemberResponse(position="COO", firstName="Antonio", lastName="Belloni"),
            ],
        )
        assert ceo.position == "CEO"
        assert len(ceo.subordinates) == 2
        assert ceo.subordinates[0].position == "CFO"
        assert len(ceo.subordinates[0].subordinates) == 1
        assert ceo.subordinates[0].subordinates[0].position == "VP Finance"

    def test_digital_response_nested_structure(self):
        """Test DigitalResponse with complex nested structure."""
        digital = DigitalResponse(
            insights="Strong digital presence",
            digitalStrategy=SourcedValue[DigitalStrategyResponse](
                value=DigitalStrategyResponse(
                    overallStrategy="Omnichannel approach", eCommerceCapabilities="Full platform"
                ),
                source="https://company.com/digital",
            ),
            loyaltyProgram=SourcedValue[str](value="VIP membership", source="https://company.com/vip"),
        )
        assert digital.digitalStrategy.value.overallStrategy == "Omnichannel approach"


class TestOptionalFieldsHandling:
    """Tests for optional fields handling."""

    def test_profile_response_all_optional_fields_none(self):
        """Test ProfileResponse works with all fields as None."""
        profile = ProfileResponse()
        assert profile.groupName is None
        assert profile.businessLine is None
        assert profile.ceo is None

    def test_profile_response_partial_fields(self):
        """Test ProfileResponse with partial fields populated."""
        profile = ProfileResponse(groupName=SourcedValue[str](value="Test", source="https://test.com"))
        assert profile.groupName is not None
        assert profile.businessLine is None

    def test_timeline_response_empty_events(self):
        """Test TimelineResponse with empty events list."""
        timeline = TimelineResponse(insights="Summary", events=[])
        assert timeline.events == []

    def test_products_response_empty_arrays(self):
        """Test ProductsResponse with empty product arrays."""
        products = ProductsResponse()
        assert products.range == []
        assert products.partnerBrands == []
        assert products.privateLabels == []
        assert products.categories == {}

    def test_jobs_response_empty_offers(self):
        """Test JobsResponse with None insights and empty offers."""
        jobs = JobsResponse(insights=None, offers=[])
        assert jobs.insights is None
        assert jobs.offers == []

    def test_team_member_no_subordinates(self):
        """Test TeamMemberResponse without subordinates."""
        member = TeamMemberResponse(position="Developer", firstName="John", lastName="Doe")
        assert member.subordinates is None

    def test_csr_response_empty_initiatives(self):
        """Test CsrResponse with all initiative lists empty."""
        csr = CsrResponse()
        assert csr.responsibility_initiatives == []
        assert csr.charity_actions == []
        assert csr.sustainability_programs == []

    def test_press_response_empty_items(self):
        """Test PressResponse with all item lists empty."""
        press = PressResponse()
        assert press.articles == []
        assert press.press_releases == []
        assert press.media_mentions == []


class TestEnumValidation:
    """Tests for enum type validation."""

    def test_product_item_type_enum_valid(self):
        """Test valid ProductItemTypeEnum values."""
        assert ProductItemTypeEnum.range.value == "range"
        assert ProductItemTypeEnum.partner_brand.value == "partner_brand"
        assert ProductItemTypeEnum.private_label.value == "private_label"

    def test_csr_initiative_type_enum_valid(self):
        """Test valid CsrInitiativeTypeEnum values."""
        all_types = ["responsibility", "charity", "sustainability", "community", "diversity", "ethics", "awards"]
        for t in all_types:
            assert CsrInitiativeTypeEnum(t).value == t

    def test_press_item_type_enum_valid(self):
        """Test valid PressItemTypeEnum values."""
        all_types = [
            "article",
            "press_release",
            "media_mention",
            "award",
            "product_launch",
            "interview",
            "financial",
            "partnership",
        ]
        for t in all_types:
            assert PressItemTypeEnum(t).value == t

    def test_csr_initiative_response_with_enum(self):
        """Test CsrInitiativeResponse with enum type."""
        initiative = CsrInitiativeResponse(
            type=CsrInitiativeTypeEnum.sustainability,
            value="100% renewable energy by 2025",
            source="https://company.com/sustainability",
        )
        assert initiative.type == CsrInitiativeTypeEnum.sustainability

    def test_press_item_response_with_enum(self):
        """Test PressItemResponse with enum type."""
        item = PressItemResponse(
            type=PressItemTypeEnum.article,
            value="Company reports record earnings",
            source="https://reuters.com/article",
        )
        assert item.type == PressItemTypeEnum.article


class TestCreateSchemas:
    """Tests for *Create input schemas."""

    def test_profile_create_from_dify_json(self):
        """Test ProfileCreate parses Dify webhook JSON."""
        data = {
            "insights": "AI-generated summary",
            "groupName": {"value": "LVMH", "source": "https://wikipedia.org"},
            "businessLine": {"value": "Luxury goods", "source": "https://company.com"},
        }
        profile = ProfileCreate(**data)
        assert profile.insights == "AI-generated summary"
        assert profile.groupName.value == "LVMH"

    def test_timeline_create_with_events(self):
        """Test TimelineCreate with sourced events."""
        data = {
            "insights": "Rich history",
            "events": [
                {
                    "date": {"value": "1987", "source": "https://wiki.org"},
                    "title": {"value": "Founded", "source": "https://wiki.org"},
                    "description": {"value": "Company was founded", "source": "https://wiki.org"},
                }
            ],
        }
        timeline = TimelineCreate(**data)
        assert len(timeline.events) == 1
        assert timeline.events[0].date.value == "1987"

    def test_csr_create_with_initiatives(self):
        """Test CsrCreate with typed initiatives."""
        data = {
            "insights": "Strong CSR commitment",
            "responsibility": {"value": "Carbon neutral by 2030", "source": "https://company.com"},
            "initiatives": [
                {"type": "sustainability", "value": "100% renewable", "source": "https://company.com"},
                {"type": "charity", "value": "5M EUR donations", "source": "https://company.com"},
            ],
        }
        csr = CsrCreate(**data)
        assert len(csr.initiatives) == 2
        assert csr.initiatives[0].type == CsrInitiativeTypeEnum.sustainability

    def test_team_create_with_hierarchy(self):
        """Test TeamCreate parses hierarchical team data."""
        data = {
            "members": [
                {
                    "position": {"value": "CEO", "source": "https://company.com"},
                    "firstName": {"value": "John", "source": "https://company.com"},
                    "lastName": {"value": "Doe", "source": "https://company.com"},
                    "subordinates": [
                        {
                            "position": {"value": "CFO", "source": "https://company.com"},
                            "firstName": {"value": "Jane", "source": "https://company.com"},
                            "lastName": {"value": "Smith", "source": "https://company.com"},
                        }
                    ],
                }
            ]
        }
        team = TeamCreate(**data)
        assert len(team.members) == 1
        assert team.members[0].position.value == "CEO"
        assert len(team.members[0].subordinates) == 1
        assert team.members[0].subordinates[0].position.value == "CFO"


class TestCompleteSectionsResponse:
    """Tests for CompanySectionsResponse combining all sections."""

    def test_complete_response_defaults(self):
        """Test CompanySectionsResponse with all default values."""
        response = CompanySectionsResponse()
        assert response.profile is not None
        assert response.digital is not None
        assert response.team == []

    def test_complete_response_with_all_sections(self):
        """Test CompanySectionsResponse with all sections populated."""
        response = CompanySectionsResponse(
            profile=ProfileResponse(groupName=SourcedValue[str](value="Test", source="https://test.com")),
            timeline=TimelineResponse(
                insights="History summary", events=[TimelineEventResponse(date="2020", title="Founded")]
            ),
            team=[TeamMemberResponse(position="CEO", firstName="John", lastName="Doe")],
        )
        assert response.profile.groupName.value == "Test"
        assert response.timeline.events[0].title == "Founded"
        assert len(response.team) == 1
