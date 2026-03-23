"""LLM client factory.

Centralizes OpenAI / Azure client creation so services
don't need provider-specific logic.
"""

from openai import AsyncAzureOpenAI, AsyncOpenAI

from app.core.config import LLMProvider, settings

# Lazy-initialized singletons
_responses_client: AsyncOpenAI | None = None
_chat_client: AsyncOpenAI | AsyncAzureOpenAI | None = None


def get_responses_client() -> AsyncOpenAI:
    """Get or create the client for the Responses API (web search agents)."""
    global _responses_client
    if _responses_client is None:
        if settings.LLM_PROVIDER == LLMProvider.AZURE:
            # Azure AI Foundry: Responses API at /openai/v1/ (no api-version)
            endpoint = settings.LLM_BASE_URL.rstrip("/")
            _responses_client = AsyncOpenAI(
                api_key=settings.LLM_API_KEY,
                base_url=f"{endpoint}/openai/v1/",
            )
        else:
            _responses_client = AsyncOpenAI(
                api_key=settings.LLM_API_KEY,
                base_url=settings.LLM_BASE_URL,
            )
    return _responses_client


def get_chat_client() -> AsyncOpenAI | AsyncAzureOpenAI:
    """Get or create the client for Chat Completions."""
    global _chat_client
    if _chat_client is None:
        if settings.LLM_PROVIDER == LLMProvider.AZURE:
            # Azure: SDK handles /openai/deployments/{model}/ internally
            _chat_client = AsyncAzureOpenAI(
                api_key=settings.LLM_API_KEY,
                azure_endpoint=settings.LLM_BASE_URL,
                api_version=settings.LLM_API_VERSION,
            )
        else:
            _chat_client = AsyncOpenAI(
                api_key=settings.LLM_API_KEY,
                base_url=settings.LLM_BASE_URL,
            )
    return _chat_client
