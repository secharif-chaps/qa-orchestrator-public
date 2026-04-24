"""Tests for agent output schemas.

Covers: schema validation, round-trip serialization, enum alignment with DB,
and JSON schema generation for OpenAI structured outputs.
"""

import json

import pytest

from app.agents.schemas import (
    AGENT_OUTPUT_SCHEMAS,
    CsrAgentOutput,
    CsrInitiative,
    CsrInitiativeTypeEnum,
    DigitalAgentOutput,
    FinancialAgentOutput,
    FinancialMetric,
    FundingRound,
    PressAgentOutput,
    PressItem,
    PressItemTypeEnum,
    ProductsAgentOutput,
    ProfileAgentOutput,
    SourcedValue,
    TeamAgentOutput,
)
from app.models.company_children import (
    CsrInitiativeType,
    PressItemType,
)

# ---------------------------------------------------------------------------
# Registry
# ---------------------------------------------------------------------------


class TestAgentOutputSchemas:
    """Tests for AGENT_OUTPUT_SCHEMAS registry."""

    def test_all_nine_agents_registered(self):
        expected = {"profile", "digital", "press", "jobs", "products", "timeline", "csr", "team", "financial"}
        assert set(AGENT_OUTPUT_SCHEMAS.keys()) == expected

    def test_all_schemas_are_pydantic_models(self):
        from pydantic import BaseModel

        for name, schema in AGENT_OUTPUT_SCHEMAS.items():
            assert issubclass(schema, BaseModel), f"{name} is not a Pydantic model"


# ---------------------------------------------------------------------------
# SourcedValue
# ---------------------------------------------------------------------------


class TestSourcedValue:
    """Tests for SourcedValue model."""

    def test_full_value(self):
        sv = SourcedValue(value="test", source="https://example.com")
        assert sv.value == "test"
        assert sv.source == "https://example.com"

    def test_optional_fields(self):
        sv = SourcedValue()
        assert sv.value is None
        assert sv.source is None

    def test_extra_fields_forbidden(self):
        with pytest.raises(ValueError):
            SourcedValue(value="test", extra_field="bad")


# ---------------------------------------------------------------------------
# Profile Schema
# ---------------------------------------------------------------------------


class TestProfileSchema:
    """Tests for ProfileAgentOutput."""

    def test_valid_full_output(self):
        data = {
            "insights": "Company analysis...",
            "groupName": {"value": "Acme Group", "source": "https://acme.com"},
            "businessLine": {"value": "Technology", "source": "https://acme.com/about"},
            "establishmentYear": {"value": "2010", "source": "https://acme.com"},
            "employeeCount": {"value": "500-1000", "source": "https://acme.com"},
        }
        result = ProfileAgentOutput.model_validate(data)
        assert result.groupName.value == "Acme Group"
        assert result.insights == "Company analysis..."

    def test_empty_output(self):
        result = ProfileAgentOutput.model_validate({})
        assert result.insights is None
        assert result.groupName is None

    def test_round_trip(self):
        data = {
            "insights": "Test",
            "groupName": {"value": "Group", "source": "https://example.com"},
        }
        result = ProfileAgentOutput.model_validate(data)
        dumped = result.model_dump(exclude_none=True)
        assert dumped["insights"] == "Test"
        assert dumped["groupName"]["value"] == "Group"


# ---------------------------------------------------------------------------
# Digital Schema
# ---------------------------------------------------------------------------


class TestDigitalSchema:
    """Tests for DigitalAgentOutput."""

    def test_social_media_accounts(self):
        data = {
            "social_media_accounts": [
                {"platform": "linkedin", "url": "https://linkedin.com/company/acme", "source": "https://acme.com"},
            ]
        }
        result = DigitalAgentOutput.model_validate(data)
        assert len(result.social_media_accounts) == 1
        assert result.social_media_accounts[0].platform == "linkedin"

    def test_online_services(self):
        data = {
            "online_services": [
                {"name": "Virtual Try-On", "description": "AR product preview", "source": "https://acme.com"},
            ]
        }
        result = DigitalAgentOutput.model_validate(data)
        assert result.online_services[0].name == "Virtual Try-On"

    def test_digital_strategy(self):
        data = {
            "digital_strategy": {
                "overall_strategy": {"value": "Cloud-first", "source": "https://acme.com"},
            }
        }
        result = DigitalAgentOutput.model_validate(data)
        assert result.digital_strategy.overall_strategy.value == "Cloud-first"


# ---------------------------------------------------------------------------
# Press Schema
# ---------------------------------------------------------------------------


class TestPressSchema:
    """Tests for PressAgentOutput with unified items list."""

    def test_unified_items(self):
        data = {
            "insights": "Press coverage analysis...",
            "items": [
                {"type": "article", "value": "Acme raises $50M", "source": "https://news.com/acme"},
                {"type": "partnership", "value": "Acme partners with BigCo", "source": "https://bigco.com"},
            ],
        }
        result = PressAgentOutput.model_validate(data)
        assert len(result.items) == 2
        assert result.items[0].type == PressItemTypeEnum.article
        assert result.items[1].type == PressItemTypeEnum.partnership

    def test_invalid_type_rejected(self):
        data = {"items": [{"type": "invalid_type", "value": "test"}]}
        with pytest.raises(ValueError):
            PressAgentOutput.model_validate(data)


# ---------------------------------------------------------------------------
# Products Schema
# ---------------------------------------------------------------------------


class TestProductsSchema:
    """Tests for ProductsAgentOutput."""

    def test_typed_arrays(self):
        data = {
            "range": [{"name": {"value": "Widget Pro", "source": "https://acme.com"}}],
            "partner_brands": [{"name": {"value": "BrandX", "source": "https://brandx.com"}}],
            "private_labels": [],
        }
        result = ProductsAgentOutput.model_validate(data)
        assert len(result.range) == 1
        assert result.range[0].name.value == "Widget Pro"

    def test_categories_list_format(self):
        data = {
            "categories": [
                {"name": "Electronics", "items": ["Phone", "Tablet", "Laptop"]},
                {"name": "Software", "items": ["CRM", "ERP"]},
            ]
        }
        result = ProductsAgentOutput.model_validate(data)
        assert len(result.categories) == 2
        assert result.categories[0].name == "Electronics"
        assert "Phone" in result.categories[0].items


# ---------------------------------------------------------------------------
# CSR Schema
# ---------------------------------------------------------------------------


class TestCsrSchema:
    """Tests for CsrAgentOutput."""

    def test_initiatives_with_db_enum_types(self):
        data = {
            "insights": "CSR analysis...",
            "items": [
                {"type": "sustainability", "value": "Carbon neutral by 2030", "source": "https://acme.com/csr"},
                {"type": "diversity", "value": "50% women in leadership", "source": "https://acme.com/dei"},
            ],
        }
        result = CsrAgentOutput.model_validate(data)
        assert len(result.items) == 2
        assert result.items[0].type == CsrInitiativeTypeEnum.sustainability

    def test_invalid_type_rejected(self):
        data = {"items": [{"type": "environmental", "value": "test"}]}
        with pytest.raises(ValueError):
            CsrAgentOutput.model_validate(data)


# ---------------------------------------------------------------------------
# Team Schema
# ---------------------------------------------------------------------------


class TestTeamSchema:
    """Tests for TeamAgentOutput."""

    def test_flat_team(self):
        data = {
            "team": [
                {"first_name": "John", "last_name": "Doe", "position": "CEO"},
                {"first_name": "Jane", "last_name": "Smith", "position": "CTO"},
            ]
        }
        result = TeamAgentOutput.model_validate(data)
        assert len(result.team) == 2
        assert result.team[0].first_name == "John"
        assert result.team[0].position == "CEO"

    def test_hierarchical_team(self):
        data = {
            "team": [
                {
                    "first_name": "John",
                    "last_name": "Doe",
                    "position": "CEO",
                    "subordinates": [
                        {"first_name": "Jane", "last_name": "Smith", "position": "CTO"},
                        {"first_name": "Bob", "last_name": "Jones", "position": "CFO"},
                    ],
                }
            ]
        }
        result = TeamAgentOutput.model_validate(data)
        assert len(result.team) == 1
        assert len(result.team[0].subordinates) == 2

    def test_linkedin_url_field_name(self):
        data = {"team": [{"first_name": "John", "last_name": "Doe", "linkedin_url": "https://linkedin.com/in/jdoe"}]}
        result = TeamAgentOutput.model_validate(data)
        assert result.team[0].linkedin_url == "https://linkedin.com/in/jdoe"


# ---------------------------------------------------------------------------
# Financial Schema
# ---------------------------------------------------------------------------


class TestFinancialSchema:
    """Tests for FinancialAgentOutput, FinancialMetric, and FundingRound."""

    def test_public_company_output(self):
        data = {
            "insights": "Strong performer in the semiconductor space.",
            "companyType": {"value": "public", "source": "https://nasdaq.com"},
            "tickerSymbol": {"value": "AAPL", "source": "https://finance.yahoo.com/quote/AAPL"},
            "stockExchange": {"value": "NASDAQ", "source": "https://nasdaq.com"},
            "revenue": {"value": "$394.3B", "source": "https://finance.yahoo.com/quote/AAPL"},
            "marketCap": {"value": "$3.0T", "source": "https://finance.yahoo.com/quote/AAPL"},
            "peRatio": {"value": "29.5", "source": "https://finance.yahoo.com/quote/AAPL"},
        }
        result = FinancialAgentOutput.model_validate(data)
        assert result.insights == "Strong performer in the semiconductor space."
        assert result.companyType.value == "public"
        assert result.tickerSymbol.value == "AAPL"
        assert result.revenue.value == "$394.3B"
        assert result.marketCap.value == "$3.0T"
        assert result.fundingRounds is None

    def test_private_company_output(self):
        data = {
            "insights": "Fast-growing SaaS startup.",
            "companyType": {"value": "private", "source": "https://crunchbase.com/acme"},
            "totalFunding": {"value": "$150M", "source": "https://crunchbase.com/acme"},
            "lastValuation": {"value": "$1.2B", "source": "https://techcrunch.com/acme"},
            "fundingRounds": [
                {
                    "roundType": "Series B",
                    "amount": "$80M",
                    "date": "2023-06-15",
                    "leadInvestor": "Sequoia Capital",
                    "valuation": "$800M",
                    "source": "https://techcrunch.com/acme-series-b",
                }
            ],
        }
        result = FinancialAgentOutput.model_validate(data)
        assert result.companyType.value == "private"
        assert result.totalFunding.value == "$150M"
        assert result.marketCap is None
        assert len(result.fundingRounds) == 1
        assert result.fundingRounds[0].roundType == "Series B"
        assert result.fundingRounds[0].leadInvestor == "Sequoia Capital"

    def test_metrics_list(self):
        data = {
            "metrics": [
                {
                    "metricName": "revenue",
                    "period": "FY2023",
                    "value": "$394.3B",
                    "unit": "USD",
                    "source": "https://sec.gov",
                },
                {
                    "metricName": "netIncome",
                    "period": "FY2023",
                    "value": "$96.9B",
                    "unit": "USD",
                    "source": "https://sec.gov",
                },
            ]
        }
        result = FinancialAgentOutput.model_validate(data)
        assert len(result.metrics) == 2
        assert result.metrics[0].metricName == "revenue"
        assert result.metrics[0].period == "FY2023"
        assert result.metrics[1].metricName == "netIncome"

    def test_empty_output(self):
        result = FinancialAgentOutput.model_validate({})
        assert result.insights is None
        assert result.companyType is None
        assert result.metrics is None
        assert result.fundingRounds is None

    def test_extra_fields_forbidden(self):
        with pytest.raises(ValueError):
            FinancialAgentOutput.model_validate({"unknown_field": "value"})

    def test_financial_metric_extra_forbidden(self):
        with pytest.raises(ValueError):
            FinancialMetric(metricName="revenue", bad_field="x")

    def test_funding_round_extra_forbidden(self):
        with pytest.raises(ValueError):
            FundingRound(roundType="Series A", bad_field="x")

    def test_round_trip_serialization(self):
        data = {
            "insights": "Test",
            "revenue": {"value": "$1B", "source": "https://example.com"},
            "metrics": [{"metricName": "revenue", "period": "FY2023", "value": "$1B"}],
        }
        result = FinancialAgentOutput.model_validate(data)
        dumped = result.model_dump(exclude_none=True)
        assert dumped["insights"] == "Test"
        assert dumped["revenue"]["value"] == "$1B"
        assert dumped["metrics"][0]["metricName"] == "revenue"

    def test_registered_in_agent_output_schemas(self):
        assert "financial" in AGENT_OUTPUT_SCHEMAS
        assert AGENT_OUTPUT_SCHEMAS["financial"] is FinancialAgentOutput


# ---------------------------------------------------------------------------
# Enum Alignment with DB
# ---------------------------------------------------------------------------


class TestEnumAlignment:
    """Verify schema enum values match DB enum values exactly."""

    def test_press_item_types_match_db(self):
        schema_values = {e.value for e in PressItemTypeEnum}
        db_values = {e.value for e in PressItemType}
        assert schema_values == db_values

    def test_csr_initiative_types_match_db(self):
        schema_values = {e.value for e in CsrInitiativeTypeEnum}
        db_values = {e.value for e in CsrInitiativeType}
        assert schema_values == db_values


# ---------------------------------------------------------------------------
# JSON Schema Generation
# ---------------------------------------------------------------------------


class TestJsonSchemaGeneration:
    """Tests for JSON schema generation used by OpenAI structured outputs."""

    def test_all_schemas_produce_valid_json_schema(self):
        for name, schema in AGENT_OUTPUT_SCHEMAS.items():
            json_schema = schema.model_json_schema()
            assert isinstance(json_schema, dict), f"{name} schema is not a dict"
            assert "properties" in json_schema, f"{name} schema has no properties"

    def test_all_schemas_serializable_to_json(self):
        for name, schema in AGENT_OUTPUT_SCHEMAS.items():
            json_schema = schema.model_json_schema()
            # Must be JSON-serializable for OpenAI API
            serialized = json.dumps(json_schema)
            assert isinstance(serialized, str), f"{name} schema not JSON-serializable"

    def test_extra_forbidden_generates_additional_properties_false(self):
        for name, schema in AGENT_OUTPUT_SCHEMAS.items():
            json_schema = schema.model_json_schema()
            assert json_schema.get("additionalProperties") is False, f"{name} missing additionalProperties: false"


# ---------------------------------------------------------------------------
# Output Formats Auto-Generation
# ---------------------------------------------------------------------------


class TestOutputFormatsAutoGeneration:
    """Tests for auto-generated AGENT_OUTPUT_FORMATS."""

    def test_all_agents_have_output_formats(self):
        from app.agents.prompts.shared.output_formats import AGENT_OUTPUT_FORMATS

        expected = {"profile", "digital", "press", "jobs", "products", "timeline", "csr", "team", "financial"}
        assert set(AGENT_OUTPUT_FORMATS.keys()) == expected

    def test_output_formats_are_valid_json(self):
        from app.agents.prompts.shared.output_formats import AGENT_OUTPUT_FORMATS

        for name, fmt in AGENT_OUTPUT_FORMATS.items():
            parsed = json.loads(fmt)
            assert isinstance(parsed, dict), f"{name} output format is not a dict"

    def test_output_formats_contain_no_title_keys(self):
        from app.agents.prompts.shared.output_formats import AGENT_OUTPUT_FORMATS

        def _find_title_keys(obj, path=""):
            """Recursively check for 'title' keys in a JSON structure."""
            found = []
            if isinstance(obj, dict):
                if "title" in obj:
                    found.append(path or "root")
                for key, value in obj.items():
                    found.extend(_find_title_keys(value, f"{path}.{key}"))
            elif isinstance(obj, list):
                for i, item in enumerate(obj):
                    found.extend(_find_title_keys(item, f"{path}[{i}]"))
            return found

        for name, fmt in AGENT_OUTPUT_FORMATS.items():
            parsed = json.loads(fmt)
            title_locations = _find_title_keys(parsed)
            assert not title_locations, f"{name} output format contains 'title' keys at: {title_locations}"


# ---------------------------------------------------------------------------
# Optional Type Fields
# ---------------------------------------------------------------------------


class TestOptionalTypeFields:
    """Tests for PressItem.type and CsrInitiative.type being optional."""

    def test_press_item_type_none(self):
        item = PressItem(value="Some press item", source="https://example.com")
        assert item.type is None
        assert item.value == "Some press item"

    def test_press_item_type_explicit_none(self):
        item = PressItem(type=None, value="test")
        assert item.type is None

    def test_press_item_type_valid_enum(self):
        item = PressItem(type="article", value="test")
        assert item.type == PressItemTypeEnum.article

    def test_csr_initiative_type_none(self):
        item = CsrInitiative(value="Some initiative", source="https://example.com")
        assert item.type is None
        assert item.value == "Some initiative"

    def test_csr_initiative_type_explicit_none(self):
        item = CsrInitiative(type=None, value="test")
        assert item.type is None

    def test_csr_initiative_type_valid_enum(self):
        item = CsrInitiative(type="sustainability", value="test")
        assert item.type == CsrInitiativeTypeEnum.sustainability

    def test_press_agent_output_with_none_type_items(self):
        data = {
            "items": [
                {"type": "article", "value": "Known type"},
                {"value": "Unknown type item", "source": "https://example.com"},
            ]
        }
        result = PressAgentOutput.model_validate(data)
        assert len(result.items) == 2
        assert result.items[0].type == PressItemTypeEnum.article
        assert result.items[1].type is None

    def test_csr_agent_output_with_none_type_initiatives(self):
        data = {
            "items": [
                {"type": "diversity", "value": "Known type"},
                {"value": "Unknown type initiative", "source": "https://example.com"},
            ]
        }
        result = CsrAgentOutput.model_validate(data)
        assert len(result.items) == 2
        assert result.items[0].type == CsrInitiativeTypeEnum.diversity
        assert result.items[1].type is None


# ---------------------------------------------------------------------------
# Clean Schema for Prompt
# ---------------------------------------------------------------------------


class TestCleanSchemaForPrompt:
    """Tests for _clean_schema_for_prompt helper."""

    def test_strips_title(self):
        from app.agents.prompts.shared.output_formats import _clean_schema_for_prompt

        schema = {"title": "MyModel", "type": "object", "properties": {}}
        result = _clean_schema_for_prompt(schema)
        assert "title" not in result
        assert result["type"] == "object"

    def test_strips_description(self):
        from app.agents.prompts.shared.output_formats import _clean_schema_for_prompt

        schema = {"description": "A model", "type": "object"}
        result = _clean_schema_for_prompt(schema)
        assert "description" not in result

    def test_recurses_into_properties(self):
        from app.agents.prompts.shared.output_formats import _clean_schema_for_prompt

        schema = {
            "title": "Root",
            "properties": {
                "name": {"title": "Name", "type": "string"},
                "age": {"title": "Age", "description": "User age", "type": "integer"},
            },
        }
        result = _clean_schema_for_prompt(schema)
        assert "title" not in result
        assert "title" not in result["properties"]["name"]
        assert "title" not in result["properties"]["age"]
        assert "description" not in result["properties"]["age"]
        assert result["properties"]["name"]["type"] == "string"

    def test_keeps_defs_but_cleans_inside(self):
        from app.agents.prompts.shared.output_formats import _clean_schema_for_prompt

        schema = {
            "$defs": {
                "SourcedValue": {
                    "title": "SourcedValue",
                    "description": "A value with source",
                    "type": "object",
                    "properties": {"value": {"title": "Value", "type": "string"}},
                }
            },
            "properties": {"name": {"$ref": "#/$defs/SourcedValue"}},
        }
        result = _clean_schema_for_prompt(schema)
        assert "$defs" in result
        assert "title" not in result["$defs"]["SourcedValue"]
        assert "description" not in result["$defs"]["SourcedValue"]
        assert "title" not in result["$defs"]["SourcedValue"]["properties"]["value"]
        assert result["$defs"]["SourcedValue"]["type"] == "object"

    def test_handles_lists(self):
        from app.agents.prompts.shared.output_formats import _clean_schema_for_prompt

        schema = {
            "anyOf": [
                {"title": "Option1", "type": "string"},
                {"title": "Option2", "type": "integer"},
            ]
        }
        result = _clean_schema_for_prompt(schema)
        assert len(result["anyOf"]) == 2
        assert "title" not in result["anyOf"][0]
        assert "title" not in result["anyOf"][1]

    def test_preserves_non_metadata_keys(self):
        from app.agents.prompts.shared.output_formats import _clean_schema_for_prompt

        schema = {"type": "object", "required": ["a", "b"], "additionalProperties": False}
        result = _clean_schema_for_prompt(schema)
        assert result == schema
