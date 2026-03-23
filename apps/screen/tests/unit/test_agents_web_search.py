"""Tests for Azure AI Foundry Responses API wrapper (web_search.py).

Covers: web_search_query, _parse_json_response, _extract_urls_from_data.
"""

import asyncio
from unittest.mock import AsyncMock, MagicMock, patch

import pytest
from openai import RateLimitError

from app.agents.tools.web_search import (
    _extract_urls_from_data,
    _parse_json_response,
    web_search_query,
)

# ---------------------------------------------------------------------------
# Fixtures
# ---------------------------------------------------------------------------


@pytest.fixture
def mock_response():
    """Build a mock Azure OpenAI responses.create() return value."""

    def _build(text="", annotations=None, input_tokens=100, output_tokens=50):
        content = MagicMock()
        content.type = "output_text"
        content.text = text
        content.annotations = annotations or []

        message = MagicMock()
        message.type = "message"
        message.content = [content]

        usage = MagicMock()
        usage.input_tokens = input_tokens
        usage.output_tokens = output_tokens

        response = MagicMock()
        response.output = [message]
        response.usage = usage
        return response

    return _build


@pytest.fixture
def mock_client(mock_response):
    """Mock AsyncOpenAI client with responses.create."""
    client = AsyncMock()
    client.responses.create = AsyncMock(return_value=mock_response(text='{"key": "value"}'))
    return client


# ---------------------------------------------------------------------------
# TestParseJsonResponse
# ---------------------------------------------------------------------------


class TestParseJsonResponse:
    """Tests for _parse_json_response."""

    def test_valid_json(self):
        result = _parse_json_response('{"name": "test"}')
        assert result == {"name": "test"}

    def test_markdown_code_block(self):
        text = '```json\n{"name": "test"}\n```'
        result = _parse_json_response(text)
        assert result == {"name": "test"}

    def test_json_surrounded_by_text(self):
        text = 'Here is the result: {"name": "test"} end of response'
        result = _parse_json_response(text)
        assert result == {"name": "test"}

    def test_invalid_string_returns_empty_dict(self):
        result = _parse_json_response("not json at all")
        assert result == {}

    def test_empty_string_returns_empty_dict(self):
        result = _parse_json_response("")
        assert result == {}

    def test_whitespace_handling(self):
        result = _parse_json_response('  {"key": "value"}  ')
        assert result == {"key": "value"}

    def test_nested_json(self):
        text = '{"outer": {"inner": [1, 2, 3]}}'
        result = _parse_json_response(text)
        assert result == {"outer": {"inner": [1, 2, 3]}}


# ---------------------------------------------------------------------------
# TestExtractUrlsFromData
# ---------------------------------------------------------------------------


class TestExtractUrlsFromData:
    """Tests for _extract_urls_from_data."""

    def test_nested_dicts(self):
        data = {"source": "https://example.com", "nested": {"url": "https://test.com"}}
        urls = _extract_urls_from_data(data)
        assert urls == {"https://example.com", "https://test.com"}

    def test_nested_lists(self):
        data = {"sources": ["https://a.com", "https://b.com"]}
        urls = _extract_urls_from_data(data)
        assert urls == {"https://a.com", "https://b.com"}

    def test_deduplication(self):
        data = {"a": "https://dup.com", "b": "https://dup.com"}
        urls = _extract_urls_from_data(data)
        assert len(urls) == 1
        assert "https://dup.com" in urls

    def test_non_url_strings_ignored(self):
        data = {"name": "Acme Corp", "url": "https://acme.com"}
        urls = _extract_urls_from_data(data)
        assert urls == {"https://acme.com"}

    def test_empty_data(self):
        assert _extract_urls_from_data({}) == set()
        assert _extract_urls_from_data([]) == set()
        assert _extract_urls_from_data("not a url") == set()

    def test_http_and_https(self):
        data = {"a": "http://plain.com", "b": "https://secure.com"}
        urls = _extract_urls_from_data(data)
        assert "http://plain.com" in urls
        assert "https://secure.com" in urls


# ---------------------------------------------------------------------------
# TestWebSearchQuery
# ---------------------------------------------------------------------------


class TestWebSearchQuery:
    """Tests for web_search_query."""

    @pytest.mark.asyncio
    @patch("app.agents.tools.web_search._get_semaphore", return_value=asyncio.Semaphore(5))
    @patch("app.agents.tools.web_search.get_responses_client")
    async def test_successful_query_returns_expected_keys(self, mock_get_client, mock_sem, mock_client, mock_response):
        mock_get_client.return_value = mock_client
        mock_client.responses.create = AsyncMock(
            return_value=mock_response(text='{"data": "ok"}', input_tokens=100, output_tokens=50)
        )

        result = await web_search_query(system_prompt="test", user_query="query", agent_name="profile")

        assert "data" in result
        assert "sources" in result
        assert "input_tokens" in result
        assert "output_tokens" in result
        assert "duration_ms" in result
        assert result["input_tokens"] == 100
        assert result["output_tokens"] == 50

    @pytest.mark.asyncio
    @patch("app.agents.tools.web_search._get_semaphore", return_value=asyncio.Semaphore(5))
    @patch("app.agents.tools.web_search.get_responses_client")
    async def test_country_code_sets_user_location(self, mock_get_client, mock_sem, mock_client, mock_response):
        mock_get_client.return_value = mock_client
        mock_client.responses.create = AsyncMock(return_value=mock_response(text="{}"))

        await web_search_query(system_prompt="test", user_query="query", agent_name="profile", country_code="FR")

        call_kwargs = mock_client.responses.create.call_args[1]
        tool_config = call_kwargs["tools"][0]
        assert tool_config["user_location"] == {"type": "approximate", "country": "FR"}

    @pytest.mark.asyncio
    @patch("app.agents.tools.web_search._get_semaphore", return_value=asyncio.Semaphore(5))
    @patch("app.agents.tools.web_search.get_responses_client")
    async def test_no_country_code_no_user_location(self, mock_get_client, mock_sem, mock_client, mock_response):
        mock_get_client.return_value = mock_client
        mock_client.responses.create = AsyncMock(return_value=mock_response(text="{}"))

        await web_search_query(system_prompt="test", user_query="query", agent_name="profile")

        call_kwargs = mock_client.responses.create.call_args[1]
        tool_config = call_kwargs["tools"][0]
        assert "user_location" not in tool_config

    @pytest.mark.asyncio
    @patch("app.agents.tools.web_search._get_semaphore", return_value=asyncio.Semaphore(5))
    @patch("app.agents.tools.web_search.get_responses_client")
    async def test_allowed_domains_sets_filters(self, mock_get_client, mock_sem, mock_client, mock_response):
        mock_get_client.return_value = mock_client
        mock_client.responses.create = AsyncMock(return_value=mock_response(text="{}"))

        await web_search_query(
            system_prompt="test", user_query="query", agent_name="profile", allowed_domains=["example.com"]
        )

        call_kwargs = mock_client.responses.create.call_args[1]
        tool_config = call_kwargs["tools"][0]
        assert tool_config["filters"]["allowed_domains"] == ["example.com"]

    @pytest.mark.asyncio
    @patch("app.agents.tools.web_search._get_semaphore", return_value=asyncio.Semaphore(5))
    @patch("app.agents.tools.web_search.get_responses_client")
    async def test_usage_none_returns_zeros(self, mock_get_client, mock_sem, mock_client, mock_response):
        mock_get_client.return_value = mock_client
        resp = mock_response(text="{}")
        resp.usage = None
        mock_client.responses.create = AsyncMock(return_value=resp)

        result = await web_search_query(system_prompt="test", user_query="query", agent_name="profile")

        assert result["input_tokens"] == 0
        assert result["output_tokens"] == 0

    @pytest.mark.asyncio
    @patch("app.agents.tools.web_search._get_semaphore", return_value=asyncio.Semaphore(5))
    @patch("app.agents.tools.web_search.get_responses_client")
    async def test_duration_ms_is_positive(self, mock_get_client, mock_sem, mock_client, mock_response):
        mock_get_client.return_value = mock_client
        mock_client.responses.create = AsyncMock(return_value=mock_response(text="{}"))

        result = await web_search_query(system_prompt="test", user_query="query", agent_name="profile")

        assert isinstance(result["duration_ms"], int)
        assert result["duration_ms"] >= 0

    @pytest.mark.asyncio
    @patch("app.agents.tools.web_search._get_semaphore", return_value=asyncio.Semaphore(5))
    @patch("app.agents.tools.web_search.get_responses_client")
    async def test_rate_limit_retries(self, mock_get_client, mock_sem, mock_client, mock_response):
        """Rate limit (429) retries with backoff."""
        mock_get_client.return_value = mock_client

        # Build a proper RateLimitError
        error_response = MagicMock()
        error_response.status_code = 429
        error_response.headers = {"retry-after": "1"}
        error_response.json.return_value = {"error": {"message": "rate limited"}}

        rate_error = RateLimitError(
            message="rate limited",
            response=error_response,
            body={"error": {"message": "rate limited"}},
        )

        mock_client.responses.create = AsyncMock(
            side_effect=[rate_error, rate_error, mock_response(text='{"ok": true}')]
        )

        with patch("app.agents.tools.web_search.asyncio.sleep", new_callable=AsyncMock):
            result = await web_search_query(system_prompt="test", user_query="query", agent_name="profile")

        assert result["data"] == {"ok": True}
        assert mock_client.responses.create.call_count == 3

    @pytest.mark.asyncio
    @patch("app.agents.tools.web_search._get_semaphore", return_value=asyncio.Semaphore(5))
    @patch("app.agents.tools.web_search.get_responses_client")
    async def test_fallback_to_web_search_preview(self, mock_get_client, mock_sem, mock_client, mock_response):
        """Fallback to web_search_preview when web_search error."""
        mock_get_client.return_value = mock_client

        success_resp = mock_response(text='{"fallback": true}')

        call_count = 0

        async def side_effect(**kwargs):
            nonlocal call_count
            call_count += 1
            if call_count == 1:
                raise Exception("web_search tool is not available")
            return success_resp

        mock_client.responses.create = AsyncMock(side_effect=side_effect)

        result = await web_search_query(system_prompt="test", user_query="query", agent_name="profile")

        assert result["data"] == {"fallback": True}

    @pytest.mark.asyncio
    @patch("app.agents.tools.web_search._get_semaphore", return_value=asyncio.Semaphore(5))
    @patch("app.agents.tools.web_search.get_responses_client")
    async def test_non_web_search_error_propagates(self, mock_get_client, mock_sem, mock_client):
        """Non-web_search errors propagate without fallback."""
        mock_get_client.return_value = mock_client
        mock_client.responses.create = AsyncMock(side_effect=Exception("Connection timeout"))

        with pytest.raises(Exception, match="Connection timeout"):
            await web_search_query(system_prompt="test", user_query="query", agent_name="profile")
