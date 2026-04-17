"""OpenAI Responses API wrapper with web_search tool and function tool support."""

import asyncio
import json
import random
import re
import time
from collections.abc import Callable
from typing import Any

from openai import APIError, RateLimitError
from pydantic import BaseModel

from app.core.config import settings
from app.core.llm import get_responses_client
from app.core.logging_config import get_logger

logger = get_logger(__name__)

# Maximum iterations for function tool call loop
MAX_FUNCTION_CALL_ITERATIONS = 3

_semaphore: asyncio.Semaphore | None = None


def _get_semaphore() -> asyncio.Semaphore:
    """Get or create the concurrency semaphore."""
    global _semaphore
    if _semaphore is None:
        from app.agents.config import MAX_CONCURRENT_API_CALLS

        _semaphore = asyncio.Semaphore(MAX_CONCURRENT_API_CALLS)
    return _semaphore


def _parse_json_response(text: str) -> dict:
    """Parse JSON from response text, handling markdown code blocks."""
    # Try direct JSON parse first
    text = text.strip()
    try:
        return json.loads(text)
    except json.JSONDecodeError:
        pass

    # Try extracting from markdown code block
    match = re.search(r"```(?:json)?\s*\n?(.*?)\n?```", text, re.DOTALL)
    if match:
        try:
            return json.loads(match.group(1).strip())
        except json.JSONDecodeError:
            pass

    # Try finding first { to last }
    start = text.find("{")
    end = text.rfind("}")
    if start != -1 and end != -1 and end > start:
        try:
            return json.loads(text[start : end + 1])
        except json.JSONDecodeError:
            pass

    logger.warning(f"Failed to parse JSON from response: {text[:500]!r}")
    return {}


def _extract_urls_from_data(data: Any, urls: set[str] | None = None) -> set[str]:
    """Recursively extract URLs from data structure."""
    if urls is None:
        urls = set()

    if isinstance(data, str):
        if data.startswith(("http://", "https://")):
            urls.add(data)
    elif isinstance(data, dict):
        for value in data.values():
            _extract_urls_from_data(value, urls)
    elif isinstance(data, list):
        for item in data:
            _extract_urls_from_data(item, urls)

    return urls


def _extract_function_calls(response) -> list[dict]:
    """Extract function call items from a Responses API response.

    Returns:
        List of dicts with keys: call_id, name, arguments
    """
    calls = []
    for item in response.output:
        if item.type == "function_call":
            try:
                arguments = json.loads(item.arguments) if isinstance(item.arguments, str) else item.arguments
            except (json.JSONDecodeError, TypeError):
                arguments = {}
            calls.append(
                {
                    "call_id": item.call_id,
                    "name": item.name,
                    "arguments": arguments,
                }
            )
    return calls


def _make_strict_compatible(schema: dict[str, Any]) -> dict[str, Any]:
    """Recursively patch a JSON schema for OpenAI strict mode.

    OpenAI strict structured outputs require:
    - Every object must have "required" listing ALL properties
    - Every object must have "additionalProperties": false
    - No "default" or "title" keys (unsupported by strict mode)
    These rules apply at every nesting level, including $defs.
    """
    if isinstance(schema, dict):
        # Strip keys unsupported by OpenAI strict mode
        for unsupported in ("default", "title"):
            schema.pop(unsupported, None)

        if schema.get("type") == "object" and "properties" in schema:
            schema["required"] = list(schema["properties"].keys())
            schema["additionalProperties"] = False
            for prop in schema["properties"].values():
                _make_strict_compatible(prop)
        # Handle anyOf / oneOf (Pydantic uses these for Optional types)
        for key in ("anyOf", "oneOf", "allOf"):
            if key in schema:
                for variant in schema[key]:
                    _make_strict_compatible(variant)
        # Handle items in arrays
        if "items" in schema:
            _make_strict_compatible(schema["items"])
        # Handle $defs (shared model definitions)
        if "$defs" in schema:
            for definition in schema["$defs"].values():
                _make_strict_compatible(definition)
    return schema


def _build_text_format(output_schema: type[BaseModel] | None) -> dict[str, Any] | None:
    """Build OpenAI text.format config from a Pydantic schema.

    Returns None if no schema provided.
    """
    if output_schema is None:
        return None

    json_schema = output_schema.model_json_schema()
    _make_strict_compatible(json_schema)
    return {
        "format": {
            "type": "json_schema",
            "name": output_schema.__name__,
            "schema": json_schema,
            "strict": True,
        }
    }


def _validate_with_schema(data: dict, output_schema: type[BaseModel] | None, agent_name: str) -> dict:
    """Validate parsed data against Pydantic schema and return clean dict.

    Raises ValidationError on failure — caught by run_agent() in base.py.
    """
    if output_schema is None:
        return data

    validated = output_schema.model_validate(data)
    return validated.model_dump(exclude_none=True)


async def web_search_query(
    system_prompt: str,
    user_query: str,
    agent_name: str,
    country_code: str | None = None,
    allowed_domains: list[str] | None = None,
    search_context_size: str = "medium",
    function_tools: list[dict] | None = None,
    function_handler: Callable[[dict], str] | None = None,
    output_schema: type[BaseModel] | None = None,
) -> dict:
    """Execute a web search query via Azure AI Foundry Responses API.

    Args:
        system_prompt: System instructions for the model
        user_query: User query to process
        agent_name: Name of the calling agent (for logging)
        country_code: Optional country code for search localization
        allowed_domains: Optional domain whitelist for filtering results
        search_context_size: Size of search context: "low", "medium", "high"
        function_tools: Optional list of function tool definitions to provide to the model
        function_handler: Optional handler for function tool calls (receives arguments dict, returns string)
        output_schema: Optional Pydantic model to enforce structured output

    Returns:
        dict with keys: data, sources, input_tokens, output_tokens, duration_ms
    """
    client = get_responses_client()
    semaphore = _get_semaphore()

    # Build tool configs
    web_search_config: dict[str, Any] = {
        "type": "web_search",
        "search_context_size": search_context_size,
    }
    # Only pass country if it's a valid ISO 3166-1 alpha-2 code (e.g. "FR", "US").
    # The planner LLM can return arbitrary strings ("Unknown", full country names, etc.)
    # which the Azure API rejects with a 400. Skip user_location when invalid.
    if country_code and len(country_code) == 2 and country_code.isalpha():
        web_search_config["user_location"] = {"type": "approximate", "country": country_code.upper()}
    if allowed_domains:
        web_search_config["filters"] = {"allowed_domains": allowed_domains}

    tools: list[dict] = [web_search_config]
    if function_tools:
        tools.extend(function_tools)

    # Skip structured output when function tools present (OpenAI API limitation:
    # text.format json_schema conflicts with function tool definitions)
    if function_tools:
        text_format = None
    elif output_schema:
        text_format = _build_text_format(output_schema)
    else:
        text_format = None

    start_time = time.monotonic()

    # Exponential backoff with jitter on 429
    max_retries = 5
    base_delay = 2.0
    max_delay = 60.0

    for attempt in range(max_retries + 1):
        try:
            async with semaphore:
                try:
                    create_kwargs: dict[str, Any] = {
                        "model": settings.LLM_MODEL,
                        "instructions": system_prompt,
                        "tools": tools,
                        "input": user_query,
                    }
                    if text_format:
                        create_kwargs["text"] = text_format

                    response = await client.responses.create(**create_kwargs)
                except APIError as e:
                    # Fall back to web_search_preview if web_search unavailable
                    if "web_search" in str(e).lower() and attempt == 0:
                        web_search_config["type"] = "web_search_preview"
                        tools[0] = web_search_config
                        logger.info(f"Falling back to web_search_preview for {agent_name}")
                        create_kwargs["tools"] = tools
                        response = await client.responses.create(**create_kwargs)
                    elif text_format and "text" in str(e).lower() and attempt == 0:
                        # Safety net: structured output rejected by API, retry without it.
                        # Primary guard is the upfront skip when function_tools are present.
                        logger.info(f"Structured output not supported for {agent_name}, retrying without")
                        create_kwargs.pop("text", None)
                        text_format = None
                        response = await client.responses.create(**create_kwargs)
                    else:
                        raise

            break  # Success, exit retry loop

        except RateLimitError as e:
            if attempt == max_retries:
                raise

            # Exponential backoff with ±30% jitter
            delay = base_delay * (2**attempt)
            jitter = delay * random.uniform(-0.3, 0.3)
            delay = min(delay + jitter, max_delay)
            logger.warning(
                f"Rate limited for {agent_name}, retrying in {delay:.1f}s (attempt {attempt + 1}/{max_retries})",
                extra={"agent_name": agent_name, "error": str(e)},
            )
            await asyncio.sleep(delay)

    # Accumulate tokens across function call iterations
    total_input_tokens = getattr(response.usage, "input_tokens", 0) if response.usage else 0
    total_output_tokens = getattr(response.usage, "output_tokens", 0) if response.usage else 0

    # Track sources from intermediate responses (tool loop) so they aren't lost
    # when the reformatting pass replaces the final response object
    intermediate_sources: set[str] = set()

    # Handle function tool calls in a conversation loop
    if function_handler:
        for iteration in range(MAX_FUNCTION_CALL_ITERATIONS):
            function_calls = _extract_function_calls(response)
            if not function_calls:
                break

            logger.info(
                f"Processing {len(function_calls)} function call(s) for {agent_name} (iteration {iteration + 1})",
                extra={"agent_name": agent_name, "calls": [fc["name"] for fc in function_calls]},
            )

            # Execute each function call and build output items
            function_outputs = []
            for fc in function_calls:
                try:
                    result_str = function_handler(fc["arguments"])
                except Exception as e:
                    logger.error(f"Function handler error for {fc['name']}: {e}", exc_info=True)
                    result_str = json.dumps({"error": str(e)})

                function_outputs.append(
                    {
                        "type": "function_call_output",
                        "call_id": fc["call_id"],
                        "output": result_str,
                    }
                )

            # Continue the conversation with function outputs
            async with semaphore:
                response = await client.responses.create(
                    model=settings.LLM_MODEL,
                    previous_response_id=response.id,
                    input=function_outputs,
                    tools=tools,
                )

            # Accumulate tokens
            if response.usage:
                total_input_tokens += getattr(response.usage, "input_tokens", 0)
                total_output_tokens += getattr(response.usage, "output_tokens", 0)

            # Collect source URLs from intermediate responses before they're replaced
            for item in response.output:
                if item.type == "message":
                    for content in item.content:
                        if content.type == "output_text" and hasattr(content, "annotations") and content.annotations:
                            for annotation in content.annotations:
                                if hasattr(annotation, "url") and annotation.url:
                                    intermediate_sources.add(annotation.url)

    # Reformatting pass: enforce structured output after function tool loop.
    # With no tools param, text.format works without conflict.
    if function_tools and output_schema:
        reformat_text = _build_text_format(output_schema)
        logger.info(
            f"Running reformatting pass for {agent_name} to enforce structured output",
            extra={"agent_name": agent_name},
        )
        try:
            async with semaphore:
                response = await client.responses.create(
                    model=settings.LLM_MODEL,
                    previous_response_id=response.id,
                    input=(
                        "Based on all the research you have gathered, produce your final answer "
                        "as a JSON object strictly matching the required schema. "
                        "Include only the JSON, no other text."
                    ),
                    text=reformat_text,
                )
            if response.usage:
                total_input_tokens += getattr(response.usage, "input_tokens", 0)
                total_output_tokens += getattr(response.usage, "output_tokens", 0)
        except APIError as e:
            logger.warning(
                f"Reformatting pass failed for {agent_name}: {e}",
                exc_info=True,
                extra={"agent_name": agent_name},
            )

    duration_ms = int((time.monotonic() - start_time) * 1000)

    # Extract text and sources from final response
    text_parts = []
    sources = set()

    for item in response.output:
        if item.type == "message":
            for content in item.content:
                if content.type == "output_text":
                    text_parts.append(content.text)
                    # Extract citation URLs from annotations
                    if hasattr(content, "annotations") and content.annotations:
                        for annotation in content.annotations:
                            if hasattr(annotation, "url") and annotation.url:
                                sources.add(annotation.url)

    full_text = "\n".join(text_parts)
    data = _parse_json_response(full_text)

    # Validate against schema if provided (belt and suspenders)
    data = _validate_with_schema(data, output_schema, agent_name)

    # JSON parse retry: if parsing failed and we have a schema, ask the model to
    # reformat its response. Handles cases where structured output enforcement was
    # skipped (function tools present) or silently ignored by the API.
    if not data and output_schema:
        logger.warning(
            f"JSON parse failed for {agent_name}, attempting reformatting retry. Response preview: {full_text[:200]!r}"
        )
        try:
            retry_text_format = _build_text_format(output_schema)
            async with semaphore:
                retry_response = await client.responses.create(
                    model=settings.LLM_MODEL,
                    previous_response_id=response.id,
                    input=(
                        "Your previous response could not be parsed as valid JSON. "
                        "Produce ONLY a valid JSON object matching the required schema. "
                        "No markdown, no explanations, no code blocks — just the raw JSON."
                    ),
                    text=retry_text_format,
                )
            if retry_response.usage:
                total_input_tokens += getattr(retry_response.usage, "input_tokens", 0)
                total_output_tokens += getattr(retry_response.usage, "output_tokens", 0)

            retry_text_parts = []
            for item in retry_response.output:
                if item.type == "message":
                    for content in item.content:
                        if content.type == "output_text":
                            retry_text_parts.append(content.text)
                            if hasattr(content, "annotations") and content.annotations:
                                for annotation in content.annotations:
                                    if hasattr(annotation, "url") and annotation.url:
                                        sources.add(annotation.url)

            retry_full_text = "\n".join(retry_text_parts)
            data = _parse_json_response(retry_full_text)
            data = _validate_with_schema(data, output_schema, agent_name)
        except APIError as e:
            logger.warning(
                f"JSON parse retry failed for {agent_name}: {e}",
                exc_info=True,
                extra={"agent_name": agent_name},
            )

    # Extract additional URLs from the data itself
    data_urls = _extract_urls_from_data(data)
    sources.update(data_urls)

    # Merge sources collected during the function tool loop
    sources.update(intermediate_sources)

    logger.info(
        f"Web search completed for {agent_name}",
        extra={
            "agent_name": agent_name,
            "input_tokens": total_input_tokens,
            "output_tokens": total_output_tokens,
            "duration_ms": duration_ms,
            "sources_count": len(sources),
        },
    )

    return {
        "data": data,
        "sources": sorted(sources),
        "input_tokens": total_input_tokens,
        "output_tokens": total_output_tokens,
        "duration_ms": duration_ms,
    }
