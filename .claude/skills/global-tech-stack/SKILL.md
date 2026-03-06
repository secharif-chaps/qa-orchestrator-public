---
name: global-tech-stack
description: Basil project technology stack reference. Use when understanding the full stack (PHP 8.4/Symfony 7.3 backend, Nuxt 4/Vue 3 frontend, N8N workflows, PostgreSQL, Redis, Elasticsearch), checking version requirements, or understanding how components integrate. Activates when onboarding to the project, selecting technologies for new features, understanding Docker services and ports, or referencing API endpoints and access points (basil.local, auth.basil.local, n8n.basil.local).
allowed-tools: Read, Write, Edit, Glob, Grep, Bash
metadata:
  author: chaps-e
  version: "1.0"
---

## When to use this skill

- When understanding the full technology stack (PHP 8.4, Symfony 7.3, Nuxt 4, Vue 3)
- When checking version requirements for dependencies
- When understanding how components integrate (API ↔ N8N ↔ RabbitMQ)
- When referencing Docker services and ports (PostgreSQL:5432, Redis:6379, etc.)
- When accessing development URLs (basil.local, auth.basil.local, n8n.basil.local)
- When understanding the DDD architecture (Domain, Application, Infrastructure layers)
- When working with AI integrations (N8N workflows, LiteLLM gateway, prompt templates)
- When understanding authentication flow (Keycloak OIDC)
- When using real-time features (Mercure SSE)
- When running task commands (`task -l`, `task api:test`, `task pwa:eslint:fix`)

# Global Tech Stack

## Documentation

For detailed stack reference, see:

- [Tech stack](references/tech-stack.md) - Frontend, backend, AI/workflow orchestration, authentication, infrastructure, testing, development tools, domain concepts, project structure
