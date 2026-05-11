# ADR-0013: Chat Service Replacement

## Status

**Proposed** — 2026-03-16

**Related**: [ADR-0012 — LangGraph Agent System](./0012-langgraph-agent-system.md)

## Context

The current chat feature (`chapse.py`) relies on a Dify chat workflow to provide conversational AI over company data. As part of the Dify removal (ADR-0012), the chat functionality needs its own replacement path since it is architecturally separate from the agent/research pipeline.

The chat is a simple conversation: system prompt + company context injection + streaming responses. It does not require the multi-agent graph, fan-out, or quality gate provided by LangGraph.

## Decision

Replace the Dify chat workflow with a direct **Azure OpenAI chat completion** call via `AsyncOpenAI`, using the same Azure AI Foundry endpoint as the LangGraph agents (ADR-0012).

### Scope

- Extract chat logic from `chapse.py` → new `ChatService` class in `app/services/chat_service.py`
- System prompt: "You are Chaps-e, an AI assistant for ChapsMind..." with company context injection
- Conversation management: in-memory or DB-backed conversation history (max context window)
- Company context: inject relevant section data (max 3 companies) into system prompt
- Streaming: SSE format remains compatible with frontend (`{"event": "message", "answer": ...}`)
- Update chat API endpoints to use `ChatService` instead of `DifyService`

### Architecture

```text
Frontend (existing SSE consumer)
    │
    ▼
Chat API endpoints (existing routes)
    │
    ▼
ChatService (new)
    │ AsyncOpenAI.chat.completions.create(stream=True)
    ▼
Azure AI Foundry (same endpoint as agents)
```

### What Does NOT Change

- Frontend chat UI and SSE parsing
- Chat API route paths and request/response format
- Conversation ID management
- Company context selection UX

## Consequences

### Positive

- Removes last Dify dependency (completes Dify removal together with ADR-0012)
- Chat prompts version-controlled alongside agent prompts
- Same Azure credential and endpoint as agents — no extra infrastructure

### Negative

- Conversation history management moves to our code (previously handled by Dify)

## Tags

`backend`, `ai`, `chat`, `azure-openai`
