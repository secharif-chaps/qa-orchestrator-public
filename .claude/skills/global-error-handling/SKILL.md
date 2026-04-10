---
name: global-error-handling
description: Error handling best practices for PHP/Symfony and Vue/Nuxt. Use when providing user-friendly error messages without exposing technical details, validating input early and failing fast, using specific exception types (WatchFileNotFoundException vs generic Exception), or implementing centralized error handling at API boundaries. Activates when designing graceful degradation, implementing retry strategies with exponential backoff, or ensuring resources are cleaned up in finally blocks.
allowed-tools: Read, Write, Edit, Glob, Grep, Bash
metadata:
  author: chaps-e
  version: "1.0"
---

## When to use this skill

- When providing user-friendly error messages (actionable, no technical details)
- When validating input and checking preconditions early (fail fast)
- When using specific exception types (`WatchFileNotFoundException`, not generic `Exception`)
- When handling errors at appropriate boundaries (controllers, API layers)
- When NOT scattering try-catch blocks throughout the codebase
- When implementing graceful degradation for non-critical service failures
- When using exponential backoff for transient failures in external service calls
- When cleaning up resources (file handles, connections) in finally blocks
- When structuring API error responses with consistent `detail` keys
- When logging errors with appropriate context for debugging

# Global Error Handling

## Rules

- **User-Friendly Messages**: Provide clear, actionable error messages without exposing technical details or security information
- **Fail Fast**: Validate input and check preconditions early; fail with clear error messages rather than allowing invalid state
- **Specific Exception Types**: Use specific exceptions (`WatchFileNotFoundException`) rather than generic `Exception`
- **Centralized Error Handling**: Handle errors at appropriate boundaries (controllers, API layers) rather than scattering try-catch blocks
- **Graceful Degradation**: Design systems to degrade gracefully when non-critical services fail
- **Retry Strategies**: Implement exponential backoff for transient failures in external service calls
- **Clean Up Resources**: Always clean up resources (file handles, connections) in finally blocks

## ISO 27001 Compliance

This skill touches security-sensitive areas (A.8.11, A.8.15). Consult the `security-iso27001` skill for applicable controls on information leakage in error responses and security event logging.
