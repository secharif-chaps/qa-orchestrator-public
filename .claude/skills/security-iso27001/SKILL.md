---
name: security-iso27001
description: >
  ISO 27001 security controls applicable to software development in ChapsMind.
  Use when writing code that handles authentication, authorization, sensitive data,
  logging, error handling, input validation, or API security. Activates when working
  on security-sensitive areas: Keycloak integration, API endpoints, data models,
  search indexing, or code review. ChapsMind is undergoing ISO 27001 certification.
license: MIT
metadata:
  author: owlint
  version: "1.0"
  stack: global
allowed-tools: Read, Grep, Glob
---

## When to use this skill

- When writing code that handles authentication or authorization
- When processing, storing, or transmitting sensitive data
- When implementing logging or audit trails
- When reviewing code for security compliance
- When designing API endpoints or data models
- When handling errors that could leak information
- When implementing input validation

# ISO 27001 Security Controls for Development

ChapsMind is undergoing **ISO 27001 certification**. Every developer must apply the relevant controls below when working on security-sensitive code. This is not optional guidance — it is a compliance requirement.

## Applicable Controls

### A.8 — Access Control

**Applies to:** Keycloak integration, permissions, API endpoints

- **A.8.2 — Privileged access rights**: Minimize privileged roles. Never grant admin when a narrower role suffices. Check permissions at both route-level and component-level (see `useCompanyPermissions`, `useAuthStore`).
- **A.8.3 — Information access restriction**: API endpoints MUST verify permissions BEFORE executing business logic. Use `required_roles` on FastAPI endpoints, `security` attribute on API Platform operations.
- **A.8.5 — Secure authentication**: Tokens must be validated server-side. Never trust client-side token claims without server verification. Token expiry must be enforced.
- **A.8.9 — Configuration management**: Never hardcode credentials, tokens, or secrets in code. Use environment variables. Never commit `.env` files.

### A.8.24-28 — Secure Development

**Applies to:** All code changes

- **A.8.24 — Use of cryptography**: Use established libraries for encryption (never roll your own). Encrypt sensitive data at rest. Note: password storage is handled entirely by Keycloak — never implement custom password hashing in application code.
- **A.8.25 — Secure development lifecycle**: Security review is part of every MR. Code review must check for OWASP Top 10 vulnerabilities.
- **A.8.26 — Application security requirements**: Input validation on all external inputs (see `global-validation` skill). Output encoding to prevent XSS. Parameterized queries to prevent SQL injection.
- **A.8.27 — Secure system architecture**: Enforce the principle of least privilege. Screen backend is never exposed directly — all traffic goes through the API gateway (global-service).
- **A.8.28 — Secure coding**: Follow secure coding practices. No `eval()`, no dynamic SQL, no deserialization of untrusted data.

### A.8.15-17 — Logging & Monitoring

**Applies to:** Backend services, API endpoints, error handling

- **A.8.15 — Logging**: Log authentication events (login, logout, failures), authorization failures (403), data access to sensitive resources, and admin actions. Never log passwords, tokens, or PII in plain text.
- **A.8.16 — Monitoring**: Security-relevant events must be detectable. Failed login attempts, permission escalation attempts, and unusual data access patterns should trigger alerts.
- **A.8.17 — Clock synchronization**: All services must use synchronized time sources (NTP) for log correlation.

### A.8.10-12 — Data Protection

**Applies to:** Data models, API responses, search indexing

- **A.8.10 — Data deletion**: Implement data retention policies. Soft deletes must still respect retention periods. User data deletion must cascade correctly.
- **A.8.11 — Data masking**: API responses must not expose internal IDs, stack traces, or system paths. Mask sensitive fields (email, phone) in logs and non-privileged API responses where applicable.
- **A.8.12 — Data leakage prevention**: API responses must return only the fields the consumer needs (use DTOs/serialization groups). Never expose database internals. Search indices must not index sensitive fields without access control.

### A.5.31-36 — Legal & Compliance

**Applies to:** Data handling, third-party integrations

- **A.5.34 — Privacy and protection of PII**: Personal data requires explicit purpose. Minimize data collection. Apply data minimization to database models — don't store what you don't need.
- **A.5.36 — Compliance with policies**: All code must comply with the project's permission system (see CLAUDE.md). Never bypass authentication or authorization.

## Quick Checklist for Code Review

When reviewing or writing security-sensitive code, check:

- [ ] **Auth**: Permissions verified server-side before business logic?
- [ ] **Input**: All external inputs validated and sanitized?
- [ ] **Output**: No stack traces, internal paths, or system info in error responses?
- [ ] **Secrets**: No hardcoded credentials, tokens, or API keys?
- [ ] **Logging**: Security events logged? No PII/tokens in logs?
- [ ] **Data**: API returns only necessary fields? Sensitive data masked?
- [ ] **Crypto**: Standard libraries used? No custom crypto?
- [ ] **Access**: Principle of least privilege applied?

## How Other Skills Reference This

The following skills have security-sensitive surface areas and include a reminder to consult this skill:

- `keycloak` — Authentication, authorization, token handling (A.8.2, A.8.3, A.8.5)
- `backend-api` — API security, input validation, response filtering (A.8.26, A.8.12)
- `backend-models` — Data protection, PII, retention (A.8.10, A.8.11, A.5.34)
- `global-validation` — Input sanitization, injection prevention (A.8.26)
- `global-error-handling` — Information leakage in errors (A.8.11, A.8.15)
- `opensearch` — Data indexing, access control on search (A.8.12)
- `code-review` — Security review checklist (A.8.25)
