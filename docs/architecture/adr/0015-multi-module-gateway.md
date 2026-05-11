# ADR-0015: Multi-Module API Gateway

## Status

**Status:** Accepted

**Date:** 2026-03-24

**Decision Makers:** ChapsMind Engineering Team

**Tags:** backend, architecture, api-gateway, global-service, multi-module

**Supersedes:** Partially extends [ADR-0009](./0009-global-service-architecture.md)

---

## Context

ADR-0009 established global-service as the single entry point (API Gateway + shared services). The current gateway is a catch-all proxy that blindly forwards every unmatched request to Screen. With Target (PHP/API Platform) arriving and Stream planned, this approach breaks down:

1. **No multi-backend routing** — all proxied requests go to Screen
2. **No module gate** — disabled modules are still reachable
3. **No permission pre-check** — the gateway relies entirely on backend checks
4. **No token reservation** — consumption is fire-and-forget
5. **No observability** — no correlation ID propagated across services
6. **OpenAPI merge is broken** — dead code with missing config

---

## Decision

Evolve global-service into a **schema-driven multi-module API gateway** that:

1. **Auto-discovers** routes by fetching each module's OpenAPI schema
2. **Routes** requests to the correct backend via a ModuleRegistry
3. **Enforces gates** in a fixed pipeline: Correlation ID → Auth → Module → Permission → Token lock
4. **Settles tokens** after backend response (confirm/release/override)
5. **Handles overlaps** via auto-prefixing with transparent rewrite
6. **Stays in sync** via self-announce + healthcheck hash fallback

Backends declare metadata via OpenAPI extensions:

| Extension       | Type     | Default | Meaning             |
| --------------- | -------- | ------- | ------------------- |
| `x-public`      | bool     | false   | No auth required    |
| `x-permissions` | string[] | []      | Required roles (OR) |
| `x-token-cost`  | int      | 0       | Tokens to reserve   |

---

## Options Considered

### Option 1: Schema-driven gateway with OpenAPI extensions (Chosen)

Backends declare metadata in their OpenAPI schema. The gateway fetches schemas and enforces gates.

**Pros:** Single source of truth, no gateway code change for new endpoints, framework-agnostic
**Cons:** Requires backend annotation, schema fetch latency at startup

### Option 2: Centralized route configuration

All routes in a central YAML/DB.

**Rejected** — duplicates backend info, prone to drift.

### Option 3: Dedicated API gateway (Kong/Traefik)

**Rejected** — cannot implement token lock/unlock natively, adds operational complexity.

---

## Consequences

### Positive

- True multi-module routing
- Defense in depth (auth + module + permission gates before proxy)
- Token integrity via lock/unlock pattern
- End-to-end observability via correlation ID
- Backend autonomy (self-declaring metadata)

### Negative

- Screen endpoints need `openapi_extra` annotations
- Schema correctness is critical (wrong schema = wrong behavior)

---

## References

- [ADR-0009: Global Service Architecture](./0009-global-service-architecture.md)
- [OpenAPI Specification Extensions](https://spec.openapis.org/oas/v3.1.0#specification-extensions)
