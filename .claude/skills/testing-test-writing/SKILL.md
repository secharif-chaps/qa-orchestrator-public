---
name: testing-test-writing
description: PHPUnit and Vitest test writing standards for the Basil project. Use when writing minimal tests during development (completing features first, then adding strategic tests), using Foundry factories (UserFactory, WatchFileFactory) instead of manual entity creation, implementing the NullGateway pattern for unit test isolation, or following the Arrange-Act-Assert pattern. Activates when running `task api:test`, using `#[CoversClass]` attributes, testing behavior not implementation, or writing integration tests with AbstractApiTestCase.
allowed-tools: Read, Write, Edit, Glob, Grep, Bash
metadata:
  author: chaps-e
  version: "1.0"
---

## When to use this skill

- When writing minimal tests during development (complete feature first, then strategic tests)
- When testing only core user flows (skip non-critical utilities unless requested)
- When deferring edge case testing to dedicated testing phases
- When using Foundry factories (`UserFactory::createOne()`, NOT manual entity creation)
- When using NullGateway pattern for unit test isolation
- When following Arrange-Act-Assert pattern in tests
- When naming tests with `test{Action}{Scenario}` convention
- When using `#[CoversClass(MyHandler::class)]` on unit tests
- When using `NullLogger` (never testing LoggerInterface unless business-critical)
- When storing entity data before API calls in integration tests
- When extending `AbstractApiTestCase` for integration tests
- When running `task api:test` or `docker compose exec pwa npm run test`

# Testing Test Writing

## Documentation

For detailed patterns, see:

- [Test writing standards](references/test-writing.md) - Philosophy, backend testing (unit/integration), frontend testing (component/store), N8N workflow testing, naming conventions, best practices, anti-patterns
