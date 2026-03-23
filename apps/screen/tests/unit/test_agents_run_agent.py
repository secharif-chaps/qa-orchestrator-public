"""Tests for run_agent and helper functions in nodes/base.py.

Covers: run_agent, _extract_domain, _infer_country_code, _build_allowed_domains.
"""

from unittest.mock import AsyncMock, patch

import pytest

from app.agents.config import AGENT_PROMPTS, AGENT_TIMEOUT_SECONDS
from app.agents.nodes.base import (
    _build_allowed_domains,
    _extract_domain,
    _infer_country_code,
    run_agent,
)

# ---------------------------------------------------------------------------
# TestExtractDomain
# ---------------------------------------------------------------------------


class TestExtractDomain:
    """Tests for _extract_domain."""

    def test_strips_www(self):
        assert _extract_domain("https://www.example.com") == "example.com"

    def test_no_scheme(self):
        assert _extract_domain("example.com") == "example.com"

    def test_path_stripped(self):
        assert _extract_domain("https://example.com/path/page") == "example.com"

    def test_subdomain_preserved(self):
        assert _extract_domain("https://blog.example.com") == "blog.example.com"

    def test_www_with_subdomain(self):
        assert _extract_domain("https://www.example.co.uk") == "example.co.uk"

    def test_lowercase(self):
        # .lower() is applied before www. stripping, so WWW is also stripped
        assert _extract_domain("https://WWW.EXAMPLE.COM") == "example.com"
        assert _extract_domain("https://www.EXAMPLE.COM") == "example.com"


# ---------------------------------------------------------------------------
# TestInferCountryCode
# ---------------------------------------------------------------------------


class TestInferCountryCode:
    """Tests for _infer_country_code."""

    def test_fr_tld(self):
        assert _infer_country_code("https://example.fr") == "FR"

    def test_co_uk_tld(self):
        assert _infer_country_code("https://example.co.uk") == "GB"

    def test_com_tld_returns_none(self):
        assert _infer_country_code("https://example.com") is None

    def test_de_tld(self):
        assert _infer_country_code("https://example.de") == "DE"

    def test_jp_tld(self):
        assert _infer_country_code("https://example.jp") == "JP"

    def test_unknown_tld_returns_none(self):
        assert _infer_country_code("https://example.xyz") is None


# ---------------------------------------------------------------------------
# TestBuildAllowedDomains
# ---------------------------------------------------------------------------


class TestBuildAllowedDomains:
    """Tests for _build_allowed_domains."""

    def test_placeholder_replacement(self):
        result = _build_allowed_domains("profile", "acme.com")
        assert "acme.com" in result
        assert "{company_domain}" not in str(result)

    def test_empty_list_returns_none(self):
        result = _build_allowed_domains("press", "acme.com")
        assert result is None

    def test_unknown_agent_returns_none(self):
        result = _build_allowed_domains("nonexistent", "acme.com")
        assert result is None


# ---------------------------------------------------------------------------
# TestRunAgent
# ---------------------------------------------------------------------------


class TestRunAgent:
    """Tests for run_agent."""

    @pytest.mark.asyncio
    @patch("app.agents.nodes.base.web_search_query", new_callable=AsyncMock)
    async def test_success_with_data(self, mock_ws):
        mock_ws.return_value = {
            "data": {"profile": {"name": "Acme"}},
            "sources": ["https://acme.com"],
            "input_tokens": 500,
            "output_tokens": 200,
            "duration_ms": 1000,
        }

        result = await run_agent("profile", "Acme Corp", "https://acme.com")

        assert result["status"] == "success"
        assert result["agent_name"] == "profile"
        assert result["data"] == {"profile": {"name": "Acme"}}

    @pytest.mark.asyncio
    @patch("app.agents.nodes.base.web_search_query", new_callable=AsyncMock)
    async def test_empty_data_returns_error(self, mock_ws):
        mock_ws.return_value = {
            "data": {},
            "sources": [],
            "input_tokens": 100,
            "output_tokens": 50,
            "duration_ms": 500,
        }

        result = await run_agent("profile", "Acme Corp", "https://acme.com")

        assert result["status"] == "error"
        assert "Empty response" in result["error"]

    @pytest.mark.asyncio
    @patch("app.agents.nodes.base.web_search_query", new_callable=AsyncMock)
    async def test_timeout_returns_error(self, mock_ws):
        mock_ws.side_effect = TimeoutError()

        result = await run_agent("profile", "Acme Corp", "https://acme.com")

        assert result["status"] == "error"
        assert "timed out" in result["error"]
        assert result["duration_ms"] == AGENT_TIMEOUT_SECONDS * 1000

    @pytest.mark.asyncio
    @patch("app.agents.nodes.base.web_search_query", new_callable=AsyncMock)
    async def test_general_exception_returns_error(self, mock_ws):
        mock_ws.side_effect = RuntimeError("API crash")

        result = await run_agent("profile", "Acme Corp", "https://acme.com")

        assert result["status"] == "error"
        assert "API crash" in result["error"]
        assert result["input_tokens"] == 0
        assert result["output_tokens"] == 0

    @pytest.mark.asyncio
    @patch("app.agents.nodes.base.web_search_query", new_callable=AsyncMock)
    async def test_system_prompt_from_agent_prompts(self, mock_ws):
        mock_ws.return_value = {
            "data": {"ok": True},
            "sources": [],
            "input_tokens": 10,
            "output_tokens": 5,
            "duration_ms": 100,
        }

        await run_agent("profile", "Acme Corp", "https://acme.com")

        call_kwargs = mock_ws.call_args[1]
        assert call_kwargs["system_prompt"] == AGENT_PROMPTS["profile"]

    @pytest.mark.asyncio
    @patch("app.agents.nodes.base.web_search_query", new_callable=AsyncMock)
    async def test_country_code_inferred_from_tld(self, mock_ws):
        mock_ws.return_value = {
            "data": {"ok": True},
            "sources": [],
            "input_tokens": 10,
            "output_tokens": 5,
            "duration_ms": 100,
        }

        await run_agent("profile", "Acme SA", "https://acme.fr")

        call_kwargs = mock_ws.call_args[1]
        assert call_kwargs["country_code"] == "FR"

    @pytest.mark.asyncio
    @patch("app.agents.nodes.base.web_search_query", new_callable=AsyncMock)
    async def test_explicit_country_code_overrides_tld(self, mock_ws):
        mock_ws.return_value = {
            "data": {"ok": True},
            "sources": [],
            "input_tokens": 10,
            "output_tokens": 5,
            "duration_ms": 100,
        }

        await run_agent("profile", "Acme SA", "https://acme.fr", country_code="DE")

        call_kwargs = mock_ws.call_args[1]
        assert call_kwargs["country_code"] == "DE"

    @pytest.mark.asyncio
    @patch("app.agents.nodes.base.web_search_query", new_callable=AsyncMock)
    async def test_query_includes_company_name_and_website(self, mock_ws):
        mock_ws.return_value = {
            "data": {"ok": True},
            "sources": [],
            "input_tokens": 10,
            "output_tokens": 5,
            "duration_ms": 100,
        }

        await run_agent("profile", "Acme Corp", "https://acme.com")

        call_kwargs = mock_ws.call_args[1]
        assert "Acme Corp" in call_kwargs["user_query"]
        assert "https://acme.com" in call_kwargs["user_query"]

    @pytest.mark.asyncio
    @patch("app.agents.nodes.base.web_search_query", new_callable=AsyncMock)
    async def test_brief_included_in_query(self, mock_ws):
        mock_ws.return_value = {
            "data": {"ok": True},
            "sources": [],
            "input_tokens": 10,
            "output_tokens": 5,
            "duration_ms": 100,
        }

        await run_agent("profile", "Acme Corp", "https://acme.com", company_brief="A tech company building widgets")

        call_kwargs = mock_ws.call_args[1]
        assert "A tech company building widgets" in call_kwargs["user_query"]

    @pytest.mark.asyncio
    @patch("app.agents.nodes.base.web_search_query", new_callable=AsyncMock)
    async def test_allowed_domains_with_company_domain(self, mock_ws):
        mock_ws.return_value = {
            "data": {"ok": True},
            "sources": [],
            "input_tokens": 10,
            "output_tokens": 5,
            "duration_ms": 100,
        }

        await run_agent("profile", "Acme Corp", "https://www.acme.com")

        call_kwargs = mock_ws.call_args[1]
        domains = call_kwargs["allowed_domains"]
        assert domains is not None
        assert "acme.com" in domains
