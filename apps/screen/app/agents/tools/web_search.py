"""OpenAI Responses API wrapper with web_search tool."""

import asyncio
import json
import random
import re
import time
from typing import Any

from openai import AsyncOpenAI, RateLimitError

from app.core.config import settings
from app.core.logging_config import get_logger

logger = get_logger(__name__)

# Lazy-initialized module-level client singleton
_client: AsyncOpenAI | None = None
_semaphore: asyncio.Semaphore | None = None


def _get_client() -> AsyncOpenAI:
    """Get or create the AsyncOpenAI client for Responses API."""
    global _client
    if _client is None:
        _client = AsyncOpenAI(
            api_key=settings.LLM_API_KEY,
            base_url=settings.LLM_BASE_URL,
        )
    return _client


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

    logger.warning("Failed to parse JSON from response", extra={"text_preview": text[:200]})
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


async def web_search_query(
    system_prompt: str,
    user_query: str,
    agent_name: str,
    country_code: str | None = None,
    allowed_domains: list[str] | None = None,
    search_context_size: str = "medium",
) -> dict:
    """Execute a web search query via Azure AI Foundry Responses API.

    Args:
        system_prompt: System instructions for the model
        user_query: User query to process
        agent_name: Name of the calling agent (for logging)
        country_code: Optional country code for search localization
        allowed_domains: Optional domain whitelist for filtering results
        search_context_size: Size of search context: "low", "medium", "high"

    Returns:
        dict with keys: data, sources, input_tokens, output_tokens, duration_ms
    """
    client = _get_client()
    semaphore = _get_semaphore()

    # Build tool config
    tool_config: dict[str, Any] = {
        "type": "web_search",
        "search_context_size": search_context_size,
    }
    if country_code:
        tool_config["user_location"] = {"type": "approximate", "country": country_code}
    if allowed_domains:
        tool_config["filters"] = {"allowed_domains": allowed_domains}

    start_time = time.monotonic()

    # Exponential backoff with jitter on 429
    max_retries = 5
    base_delay = 2.0
    max_delay = 60.0

    for attempt in range(max_retries + 1):
        try:
            async with semaphore:
                try:
                    response = await client.responses.create(
                        model=settings.LLM_MODEL,
                        instructions=system_prompt,
                        tools=[tool_config],
                        input=user_query,
                    )
                except Exception as e:
                    # Fall back to web_search_preview if web_search unavailable
                    if "web_search" in str(e).lower() and attempt == 0:
                        tool_config["type"] = "web_search_preview"
                        logger.info(f"Falling back to web_search_preview for {agent_name}")
                        response = await client.responses.create(
                            model=settings.LLM_MODEL,
                            instructions=system_prompt,
                            tools=[tool_config],
                            input=user_query,
                        )
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

    duration_ms = int((time.monotonic() - start_time) * 1000)

    # Extract text and sources from response
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

    # Extract additional URLs from the data itself
    data_urls = _extract_urls_from_data(data)
    sources.update(data_urls)

    # Get token usage
    input_tokens = getattr(response.usage, "input_tokens", 0) if response.usage else 0
    output_tokens = getattr(response.usage, "output_tokens", 0) if response.usage else 0

    logger.info(
        f"Web search completed for {agent_name}",
        extra={
            "agent_name": agent_name,
            "input_tokens": input_tokens,
            "output_tokens": output_tokens,
            "duration_ms": duration_ms,
            "sources_count": len(sources),
        },
    )

    return {
        "data": data,
        "sources": sorted(sources),
        "input_tokens": input_tokens,
        "output_tokens": output_tokens,
        "duration_ms": duration_ms,
    }
