---
name: backend-api
description: API Platform best practices for REST API development in PHP/Symfony. Use when letting API Platform handle deserialization/validation with DTOs, adding security rules with `security` attribute on operations, using EnumConstraint for DTO choices, implementing processor-level authorization checks with `Security::isGranted()` + `AccessDeniedHttpException`, or handling error payloads with consistent `detail` keys. Activates when working on API response handling, HTTP status codes, RESTful endpoint design, or OpenAPI documentation.
allowed-tools: Read, Write, Edit, Glob, Grep, Bash
metadata:
  author: chaps-e
  version: "1.0"
---

## When to use this skill

- When letting API Platform deserialize and validate input with DTOs (`#[Post(input: CreateResourceDto::class)]`)
- When using `#[EnumConstraint(enumClass: Status::class)]` for DTO choice validation
- When implementing processor-level authorization with `$this->denyAccessUnlessGranted('EDIT', $data)`
- When using `Security::isGrantedForUser()` for verifying another user's permissions
- When structuring error responses with `['detail' => 'message']` format
- When including identifiers in not-found errors (`Resource {$id} not found`)
- When relying on Symfony's locale negotiation instead of manual header parsing
- When documenting API endpoints with OpenAPI descriptions and security rules
- When using correct HTTP methods (GET, POST, PUT, PATCH, DELETE)
- When returning appropriate HTTP status codes (200, 201, 204, 400, 403, 404, 422)
- When designing RESTful endpoints with plural nouns and consistent naming

# Backend API

## Documentation

For detailed patterns, see:

- [API standards](references/api.md) - API Platform best practices, authorization, error handling, HTTP methods, RESTful design

## ISO 27001 Compliance

This skill touches security-sensitive areas (A.8.26, A.8.12). Consult the `security-iso27001` skill for applicable controls on input validation, response filtering, and data leakage prevention.
