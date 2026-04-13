"""Tests for Azure AI Foundry Responses API wrapper (web_search.py).

Covers: web_search_query, _parse_json_response, _extract_urls_from_data.
"""

import asyncio
from unittest.mock import AsyncMock, MagicMock, patch

import pytest
from openai import APIError, RateLimitError

from app.agents.tools.web_search import (
    _extract_urls_from_data,
    _make_strict_compatible,
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
# TestMakeStrictCompatible
# ---------------------------------------------------------------------------


class TestMakeStrictCompatible:
    """Tests for _make_strict_compatible JSON schema patching."""

    def test_adds_required_for_all_properties(self):
        schema = {
            "type": "object",
            "properties": {"a": {"type": "string"}, "b": {"type": "integer"}},
        }
        result = _make_strict_compatible(schema)
        assert set(result["required"]) == {"a", "b"}
        assert result["additionalProperties"] is False

    def test_patches_nested_defs(self):
        schema = {
            "type": "object",
            "properties": {"x": {"$ref": "#/$defs/Inner"}},
            "$defs": {
                "Inner": {
                    "type": "object",
                    "properties": {"val": {"type": "string"}, "src": {"type": "string"}},
                }
            },
        }
        result = _make_strict_compatible(schema)
        inner = result["$defs"]["Inner"]
        assert set(inner["required"]) == {"val", "src"}
        assert inner["additionalProperties"] is False

    def test_patches_array_items(self):
        schema = {
            "type": "object",
            "properties": {
                "items": {
                    "type": "array",
                    "items": {
                        "type": "object",
                        "properties": {"name": {"type": "string"}},
                    },
                }
            },
        }
        result = _make_strict_compatible(schema)
        assert result["properties"]["items"]["items"]["required"] == ["name"]

    def test_strips_default_and_title(self):
        """OpenAI strict mode rejects 'default' and 'title' keys."""
        schema = {
            "title": "MyModel",
            "type": "object",
            "properties": {
                "name": {"type": "string", "default": "unknown", "title": "Name"},
                "nested": {
                    "type": "object",
                    "title": "Nested",
                    "properties": {
                        "val": {"type": "string", "default": None, "title": "Val"},
                    },
                },
            },
            "$defs": {
                "Inner": {
                    "title": "Inner",
                    "type": "object",
                    "properties": {"x": {"type": "string", "default": "", "title": "X"}},
                }
            },
        }
        result = _make_strict_compatible(schema)
        # Top-level
        assert "title" not in result
        assert "default" not in result
        # Properties
        assert "default" not in result["properties"]["name"]
        assert "title" not in result["properties"]["name"]
        # Nested object
        assert "title" not in result["properties"]["nested"]
        assert "default" not in result["properties"]["nested"]["properties"]["val"]
        assert "title" not in result["properties"]["nested"]["properties"]["val"]
        # $defs
        inner = result["$defs"]["Inner"]
        assert "title" not in inner
        assert "default" not in inner["properties"]["x"]
        assert "title" not in inner["properties"]["x"]

    def test_real_schema_all_required(self):
        """All agent schemas produce valid strict-mode output."""
        from app.agents.schemas import AGENT_OUTPUT_SCHEMAS
        from app.agents.tools.web_search import _build_text_format

        for name, schema_cls in AGENT_OUTPUT_SCHEMAS.items():
            fmt = _build_text_format(schema_cls)
            schema = fmt["format"]["schema"]
            # Check top-level
            if "properties" in schema:
                assert set(schema["required"]) == set(schema["properties"].keys()), (
                    f"{name}: top-level required mismatch"
                )
            # Check $defs
            for def_name, defn in schema.get("$defs", {}).items():
                if defn.get("type") == "object" and "properties" in defn:
                    assert set(defn["required"]) == set(defn["properties"].keys()), (
                        f"{name}.{def_name}: required mismatch"
                    )

    def test_real_schema_no_unsupported_keys(self):
        """No agent schema contains default/title after patching."""
        from app.agents.schemas import AGENT_OUTPUT_SCHEMAS
        from app.agents.tools.web_search import _build_text_format

        def _check_schema_node(node, path=""):
            """Check a single JSON-schema node for unsupported keys."""
            if not isinstance(node, dict):
                return
            for bad_key in ("default", "title"):
                assert bad_key not in node, f"{path}: found unsupported key '{bad_key}'"
            # Recurse into properties *values* (each value is a schema node)
            if "properties" in node:
                for prop_name, prop_schema in node["properties"].items():
                    _check_schema_node(prop_schema, f"{path}.properties.{prop_name}")
            # Recurse into $defs values
            for def_name, defn in node.get("$defs", {}).items():
                _check_schema_node(defn, f"{path}.$defs.{def_name}")
            # Recurse into anyOf/oneOf/allOf variants
            for key in ("anyOf", "oneOf", "allOf"):
                for i, variant in enumerate(node.get(key, [])):
                    _check_schema_node(variant, f"{path}.{key}[{i}]")
            # Recurse into array items
            if "items" in node and isinstance(node["items"], dict):
                _check_schema_node(node["items"], f"{path}.items")

        for name, schema_cls in AGENT_OUTPUT_SCHEMAS.items():
            fmt = _build_text_format(schema_cls)
            schema = fmt["format"]["schema"]
            _check_schema_node(schema, name)


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
                raise APIError("web_search tool is not available", MagicMock(), body=None)
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

    @pytest.mark.asyncio
    @patch("app.agents.tools.web_search._get_semaphore", return_value=asyncio.Semaphore(5))
    @patch("app.agents.tools.web_search.get_responses_client")
    async def test_json_parse_retry_triggered_on_prose_response(self, mock_get_client, mock_sem, mock_response):
        """JSON parse retry is triggered when initial response is unparseable prose."""
        from app.agents.schemas import ProfileAgentOutput

        client = AsyncMock()
        mock_get_client.return_value = client

        # First call: returns prose (not JSON)
        prose_resp = mock_response(text="Here is what I found about the company: it was founded in 1990.")
        prose_resp.id = "resp_prose"
        # Second call: reformatting retry returns valid JSON
        json_resp = mock_response(text='{"insights": "founded in 1990", "groupName": null}')
        json_resp.id = "resp_json"

        client.responses.create = AsyncMock(side_effect=[prose_resp, json_resp])

        result = await web_search_query(
            system_prompt="test",
            user_query="query",
            agent_name="profile",
            output_schema=ProfileAgentOutput,
        )

        assert client.responses.create.call_count == 2
        assert result["data"]["insights"] == "founded in 1990"

        # Second call should be the retry with text format and no tools
        retry_kwargs = client.responses.create.call_args_list[1][1]
        assert "text" in retry_kwargs
        assert "tools" not in retry_kwargs
        assert "previous_response_id" in retry_kwargs

    @pytest.mark.asyncio
    @patch("app.agents.tools.web_search._get_semaphore", return_value=asyncio.Semaphore(5))
    @patch("app.agents.tools.web_search.get_responses_client")
    async def test_json_parse_retry_failure_returns_empty_dict(self, mock_get_client, mock_sem, mock_response):
        """When JSON parse retry also fails, empty dict is returned gracefully."""
        from app.agents.schemas import ProfileAgentOutput

        client = AsyncMock()
        mock_get_client.return_value = client

        # First call: prose
        prose_resp = mock_response(text="Still just prose from the model.")
        prose_resp.id = "resp_prose"
        # Retry call: still prose — parse will fail again
        still_prose_resp = mock_response(text="Still not JSON.")
        still_prose_resp.id = "resp_prose2"

        client.responses.create = AsyncMock(side_effect=[prose_resp, still_prose_resp])

        result = await web_search_query(
            system_prompt="test",
            user_query="query",
            agent_name="profile",
            output_schema=ProfileAgentOutput,
        )

        assert result["data"] == {}
        assert client.responses.create.call_count == 2

    @pytest.mark.asyncio
    @patch("app.agents.tools.web_search._get_semaphore", return_value=asyncio.Semaphore(5))
    @patch("app.agents.tools.web_search.get_responses_client")
    async def test_json_parse_retry_exception_returns_empty_dict(self, mock_get_client, mock_sem, mock_response):
        """When retry API call throws, exception is caught and empty dict returned."""
        from app.agents.schemas import ProfileAgentOutput

        client = AsyncMock()
        mock_get_client.return_value = client

        prose_resp = mock_response(text="Some prose that is not JSON.")
        prose_resp.id = "resp_prose"

        client.responses.create = AsyncMock(side_effect=[prose_resp, APIError("API error during retry", MagicMock(), body=None)])

        result = await web_search_query(
            system_prompt="test",
            user_query="query",
            agent_name="profile",
            output_schema=ProfileAgentOutput,
        )

        assert result["data"] == {}

    @pytest.mark.asyncio
    @patch("app.agents.tools.web_search._get_semaphore", return_value=asyncio.Semaphore(5))
    @patch("app.agents.tools.web_search.get_responses_client")
    async def test_json_parse_retry_not_triggered_when_no_schema(self, mock_get_client, mock_sem, mock_response):
        """JSON parse retry is NOT triggered when output_schema is None."""
        client = AsyncMock()
        mock_get_client.return_value = client

        prose_resp = mock_response(text="Some prose that is not JSON.")
        prose_resp.id = "resp_prose"

        client.responses.create = AsyncMock(return_value=prose_resp)

        result = await web_search_query(
            system_prompt="test",
            user_query="query",
            agent_name="profile",
            output_schema=None,
        )

        # Only 1 call — no retry without a schema
        assert client.responses.create.call_count == 1
        assert result["data"] == {}

    @pytest.mark.asyncio
    @patch("app.agents.tools.web_search._get_semaphore", return_value=asyncio.Semaphore(5))
    @patch("app.agents.tools.web_search.get_responses_client")
    async def test_structured_output_fallback_retries_without_text(
        self, mock_get_client, mock_sem, mock_client, mock_response
    ):
        """When API rejects text.format, retries without it and succeeds."""
        from app.agents.schemas import ProfileAgentOutput

        mock_get_client.return_value = mock_client

        success_resp = mock_response(
            text='{"insights": "test", "groupName": {"value": "Acme", "source": "https://acme.com"}}'
        )

        call_count = 0

        async def side_effect(**kwargs):
            nonlocal call_count
            call_count += 1
            if call_count == 1:
                assert "text" in kwargs, "First call should include text format"
                raise APIError("text format is not supported for this model", MagicMock(), body=None)
            # Second call should NOT have text key
            assert "text" not in kwargs, "Second call should not include text format"
            return success_resp

        mock_client.responses.create = AsyncMock(side_effect=side_effect)

        result = await web_search_query(
            system_prompt="test",
            user_query="query",
            agent_name="profile",
            output_schema=ProfileAgentOutput,
        )

        assert result["data"]["insights"] == "test"
        assert mock_client.responses.create.call_count == 2


# ---------------------------------------------------------------------------
# TestWebSearchQueryWithFunctionTools
# ---------------------------------------------------------------------------


class TestWebSearchQueryWithFunctionTools:
    """Tests for function tool + structured output interaction."""

    SAMPLE_FUNCTION_TOOLS = [
        {
            "type": "function",
            "name": "get_local_data",
            "description": "Get local data",
            "parameters": {"type": "object", "properties": {"key": {"type": "string"}}},
        }
    ]

    @staticmethod
    def _make_function_call_response(call_id="call_1", name="get_local_data", arguments='{"key": "v"}', **usage_kw):
        """Build a mock response containing a function_call output item."""
        fc_item = MagicMock()
        fc_item.type = "function_call"
        fc_item.call_id = call_id
        fc_item.name = name
        fc_item.arguments = arguments

        usage = MagicMock()
        usage.input_tokens = usage_kw.get("input_tokens", 80)
        usage.output_tokens = usage_kw.get("output_tokens", 30)

        resp = MagicMock()
        resp.output = [fc_item]
        resp.usage = usage
        resp.id = f"resp_{call_id}"
        return resp

    @staticmethod
    def _make_message_response(text, annotations=None, input_tokens=60, output_tokens=40, resp_id="resp_msg"):
        """Build a mock response containing a message output item."""
        annotation_objs = []
        for url in annotations or []:
            ann = MagicMock()
            ann.url = url
            annotation_objs.append(ann)

        content = MagicMock()
        content.type = "output_text"
        content.text = text
        content.annotations = annotation_objs

        message = MagicMock()
        message.type = "message"
        message.content = [content]

        usage = MagicMock()
        usage.input_tokens = input_tokens
        usage.output_tokens = output_tokens

        resp = MagicMock()
        resp.output = [message]
        resp.usage = usage
        resp.id = resp_id
        return resp

    @pytest.mark.asyncio
    @patch("app.agents.tools.web_search._get_semaphore", return_value=asyncio.Semaphore(5))
    @patch("app.agents.tools.web_search.get_responses_client")
    async def test_function_tools_skips_text_format_upfront(self, mock_get_client, mock_sem):
        """Initial API call must NOT include 'text' key when function_tools are present."""
        from app.agents.schemas import ProfileAgentOutput

        client = AsyncMock()
        mock_get_client.return_value = client

        # Single message response (no function calls) so the loop exits immediately
        msg_resp = self._make_message_response(text='{"insights": "test", "groupName": null}')
        client.responses.create = AsyncMock(return_value=msg_resp)

        await web_search_query(
            system_prompt="test",
            user_query="query",
            agent_name="team",
            function_tools=self.SAMPLE_FUNCTION_TOOLS,
            function_handler=lambda args: '{"result": "ok"}',
            output_schema=ProfileAgentOutput,
        )

        first_call_kwargs = client.responses.create.call_args_list[0][1]
        assert "text" not in first_call_kwargs, "First call should NOT include text format with function tools"

    @pytest.mark.asyncio
    @patch("app.agents.tools.web_search._get_semaphore", return_value=asyncio.Semaphore(5))
    @patch("app.agents.tools.web_search.get_responses_client")
    async def test_reformatting_pass_called_after_tool_loop(self, mock_get_client, mock_sem):
        """After function tool loop, a reformatting call enforces structured output."""
        from app.agents.schemas import ProfileAgentOutput

        client = AsyncMock()
        mock_get_client.return_value = client

        # Call 1: initial → returns function call
        fc_resp = self._make_function_call_response()
        # Call 2: function output → returns message (no more function calls)
        msg_resp = self._make_message_response(text="Some prose about the company")
        # Call 3: reformatting pass → returns structured JSON
        reformat_resp = self._make_message_response(
            text='{"insights": "structured", "groupName": null}',
            resp_id="resp_reformat",
        )

        client.responses.create = AsyncMock(side_effect=[fc_resp, msg_resp, reformat_resp])

        result = await web_search_query(
            system_prompt="test",
            user_query="query",
            agent_name="team",
            function_tools=self.SAMPLE_FUNCTION_TOOLS,
            function_handler=lambda args: '{"result": "ok"}',
            output_schema=ProfileAgentOutput,
        )

        assert client.responses.create.call_count == 3

        # Reformatting call (3rd) should have text format but NO tools
        reformat_kwargs = client.responses.create.call_args_list[2][1]
        assert "text" in reformat_kwargs, "Reformatting call must include text format"
        assert "tools" not in reformat_kwargs, "Reformatting call must NOT include tools"

        assert result["data"]["insights"] == "structured"

    @pytest.mark.asyncio
    @patch("app.agents.tools.web_search._get_semaphore", return_value=asyncio.Semaphore(5))
    @patch("app.agents.tools.web_search.get_responses_client")
    async def test_reformatting_pass_token_accumulation(self, mock_get_client, mock_sem):
        """Tokens from the reformatting pass are accumulated in totals."""
        from app.agents.schemas import ProfileAgentOutput

        client = AsyncMock()
        mock_get_client.return_value = client

        # Initial: 100 in, 50 out
        fc_resp = self._make_function_call_response(input_tokens=100, output_tokens=50)
        # Tool loop response: 80 in, 30 out
        msg_resp = self._make_message_response(text="prose", input_tokens=80, output_tokens=30)
        # Reformatting: 60 in, 40 out
        reformat_resp = self._make_message_response(
            text='{"insights": "ok", "groupName": null}', input_tokens=60, output_tokens=40
        )

        client.responses.create = AsyncMock(side_effect=[fc_resp, msg_resp, reformat_resp])

        result = await web_search_query(
            system_prompt="test",
            user_query="query",
            agent_name="team",
            function_tools=self.SAMPLE_FUNCTION_TOOLS,
            function_handler=lambda args: '{"result": "ok"}',
            output_schema=ProfileAgentOutput,
        )

        assert result["input_tokens"] == 100 + 80 + 60
        assert result["output_tokens"] == 50 + 30 + 40

    @pytest.mark.asyncio
    @patch("app.agents.tools.web_search._get_semaphore", return_value=asyncio.Semaphore(5))
    @patch("app.agents.tools.web_search.get_responses_client")
    async def test_no_reformatting_without_output_schema(self, mock_get_client, mock_sem):
        """No reformatting pass when output_schema is None."""
        client = AsyncMock()
        mock_get_client.return_value = client

        fc_resp = self._make_function_call_response()
        msg_resp = self._make_message_response(text='{"key": "value"}')

        client.responses.create = AsyncMock(side_effect=[fc_resp, msg_resp])

        await web_search_query(
            system_prompt="test",
            user_query="query",
            agent_name="team",
            function_tools=self.SAMPLE_FUNCTION_TOOLS,
            function_handler=lambda args: '{"result": "ok"}',
            output_schema=None,
        )

        # Only 2 calls: initial + tool loop response, no reformatting
        assert client.responses.create.call_count == 2

    @pytest.mark.asyncio
    @patch("app.agents.tools.web_search._get_semaphore", return_value=asyncio.Semaphore(5))
    @patch("app.agents.tools.web_search.get_responses_client")
    async def test_reformatting_pass_exception_does_not_crash(self, mock_get_client, mock_sem):
        """Reformatting pass exception is caught; JSON parse retry then recovers."""
        from app.agents.schemas import ProfileAgentOutput

        client = AsyncMock()
        mock_get_client.return_value = client

        fc_resp = self._make_function_call_response()
        msg_resp = self._make_message_response(text="Prose about the company")
        # Reformatting pass throws — should be swallowed
        # Then JSON parse retry recovers with valid JSON
        recovery_resp = self._make_message_response(
            text='{"insights": "recovered", "groupName": null}',
            resp_id="resp_recovery",
        )

        call_count = 0

        async def side_effect(**kwargs):
            nonlocal call_count
            call_count += 1
            if call_count == 1:
                return fc_resp  # initial call
            elif call_count == 2:
                return msg_resp  # tool loop
            elif call_count == 3:
                raise APIError("Reformatting pass API error", MagicMock(), body=None)  # reformatting fails
            else:
                return recovery_resp  # JSON parse retry

        client.responses.create = AsyncMock(side_effect=side_effect)

        result = await web_search_query(
            system_prompt="test",
            user_query="query",
            agent_name="team",
            function_tools=self.SAMPLE_FUNCTION_TOOLS,
            function_handler=lambda args: '{"result": "ok"}',
            output_schema=ProfileAgentOutput,
        )

        # Should have recovered via JSON parse retry
        assert result["data"]["insights"] == "recovered"
        assert client.responses.create.call_count == 4

    @pytest.mark.asyncio
    @patch("app.agents.tools.web_search._get_semaphore", return_value=asyncio.Semaphore(5))
    @patch("app.agents.tools.web_search.get_responses_client")
    async def test_sources_accumulated_from_intermediate_responses(self, mock_get_client, mock_sem):
        """URLs from tool-loop intermediate responses are preserved in final sources."""
        from app.agents.schemas import ProfileAgentOutput

        client = AsyncMock()
        mock_get_client.return_value = client

        fc_resp = self._make_function_call_response()
        # Intermediate response has annotation URLs
        msg_resp = self._make_message_response(
            text="Company info from web",
            annotations=["https://intermediate-source.com/page"],
        )
        # Reformatting response has its own annotation
        reformat_resp = self._make_message_response(
            text='{"insights": "ok", "groupName": null}',
            annotations=["https://reformat-source.com/data"],
        )

        client.responses.create = AsyncMock(side_effect=[fc_resp, msg_resp, reformat_resp])

        result = await web_search_query(
            system_prompt="test",
            user_query="query",
            agent_name="team",
            function_tools=self.SAMPLE_FUNCTION_TOOLS,
            function_handler=lambda args: '{"result": "ok"}',
            output_schema=ProfileAgentOutput,
        )

        assert "https://intermediate-source.com/page" in result["sources"]
        assert "https://reformat-source.com/data" in result["sources"]
