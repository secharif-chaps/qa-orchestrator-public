"""Tests for the enrichment lookup function tool."""

import json
from unittest.mock import MagicMock, patch

from app.agents.tools.enrichment_lookup import (
    ENRICHMENT_TOOL_DEFINITION,
    build_enrichment_tool,
)


class TestBuildEnrichmentTool:
    """Tests for build_enrichment_tool."""

    def test_returns_tool_def_and_handler(self):
        tool_def, handler = build_enrichment_tool(company_id=1, available_sources=["pappers"])

        assert tool_def["type"] == "function"
        assert tool_def["name"] == "get_enrichment_data"
        assert callable(handler)

    def test_customizes_enum_to_available_sources(self):
        tool_def, _ = build_enrichment_tool(company_id=1, available_sources=["pappers"])

        assert tool_def["parameters"]["properties"]["source"]["enum"] == ["pappers"]

    def test_does_not_mutate_original_definition(self):
        original_enum = ENRICHMENT_TOOL_DEFINITION["parameters"]["properties"]["source"]["enum"]
        build_enrichment_tool(company_id=1, available_sources=["pappers"])

        assert original_enum == [
            "pappers",
            "worldcheck",
            "epo_publications",
            "epo_families",
            "epo_legal",
        ]
class TestHandleCall:
    """Tests for the handler function returned by build_enrichment_tool."""

    def test_returns_error_for_unavailable_source(self):
        _, handler = build_enrichment_tool(company_id=1, available_sources=["pappers"])

        result = json.loads(handler({"source": "worldcheck"}))

        assert "error" in result
        assert "worldcheck" in result["error"]

    def test_returns_enrichment_data_on_success(self):
        mock_enrichment = MagicMock()
        mock_enrichment.data = {"siren": "123456789"}

        mock_query = MagicMock()
        mock_query.filter.return_value.first.return_value = mock_enrichment

        mock_db = MagicMock()
        mock_db.query.return_value = mock_query

        with patch("app.agents.tools.enrichment_lookup.SessionLocal", return_value=mock_db):
            _, handler = build_enrichment_tool(company_id=1, available_sources=["pappers"])
            result = json.loads(handler({"source": "pappers"}))

        assert result == {"siren": "123456789"}
        mock_db.close.assert_called_once()

    def test_returns_error_when_no_data_found(self):
        mock_query = MagicMock()
        mock_query.filter.return_value.first.return_value = None

        mock_db = MagicMock()
        mock_db.query.return_value = mock_query

        with patch("app.agents.tools.enrichment_lookup.SessionLocal", return_value=mock_db):
            _, handler = build_enrichment_tool(company_id=1, available_sources=["pappers"])
            result = json.loads(handler({"source": "pappers"}))

        assert "error" in result
        assert "No pappers data found" in result["error"]

    def test_returns_generic_error_on_exception(self):
        """Internal errors return a generic message, not exception details."""
        mock_db = MagicMock()
        mock_db.query.side_effect = RuntimeError("internal DB host: 10.0.0.1")

        with patch("app.agents.tools.enrichment_lookup.SessionLocal", return_value=mock_db):
            _, handler = build_enrichment_tool(company_id=1, available_sources=["pappers"])
            result = json.loads(handler({"source": "pappers"}))

        assert result["error"] == "Enrichment data temporarily unavailable"
        # Verify no internal details leaked
        assert "10.0.0.1" not in result["error"]
        mock_db.close.assert_called_once()

    def test_returns_error_when_enrichment_data_is_none(self):
        """Enrichment record exists but data field is None."""
        mock_enrichment = MagicMock()
        mock_enrichment.data = None

        mock_query = MagicMock()
        mock_query.filter.return_value.first.return_value = mock_enrichment

        mock_db = MagicMock()
        mock_db.query.return_value = mock_query

        with patch("app.agents.tools.enrichment_lookup.SessionLocal", return_value=mock_db):
            _, handler = build_enrichment_tool(company_id=1, available_sources=["pappers"])
            result = json.loads(handler({"source": "pappers"}))

        assert "error" in result

    def test_returns_error_for_empty_source_argument(self):
        _, handler = build_enrichment_tool(company_id=1, available_sources=["pappers"])

        result = json.loads(handler({}))

        assert "error" in result
