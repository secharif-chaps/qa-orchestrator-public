# Architecture Decision Records (ADRs)

## Overview

Architecture Decision Records (ADRs) document significant architectural decisions made during the development of ChapsMind. They capture the context, the decision itself, the options considered, and the consequences of each choice.

ADRs serve as a historical record that helps current and future team members understand:

- **Why** certain technologies or patterns were chosen
- **What alternatives** were evaluated
- **What trade-offs** were accepted
- **When** decisions were made and by whom

## ADR Template

All ADRs follow a consistent format. See [template.md](./template.md) for the standard structure.

## ADR Index

| ID                                                                 | Title                                                      | Status   | Tags                                                                   |
| ------------------------------------------------------------------ | ---------------------------------------------------------- | -------- | ---------------------------------------------------------------------- |
| [ADR-0001](./0001-vue3-composition-api.md)                         | Vue.js 3 with Composition API                              | Accepted | frontend, framework                                                    |
| [ADR-0002](./0002-fastapi-backend.md)                              | FastAPI Backend Framework                                  | Accepted | backend, framework                                                     |
| [ADR-0003](./0003-keycloak-authentication.md)                      | Keycloak for Authentication                                | Accepted | security, authentication                                               |
| [ADR-0004](./0004-dify-ai-orchestration.md)                        | Dify for AI Orchestration                                  | Accepted | ai, orchestration                                                      |
| [ADR-0005](./0005-multi-tenancy-keycloak-organizations.md)         | Multi-Tenancy with Keycloak Organizations                  | Accepted | security, multi-tenancy                                                |
| [ADR-0006](./0006-pinia-colada-data-fetching.md)                   | Pinia Colada for Data Fetching                             | Accepted | frontend, state-management                                             |
| [ADR-0007](./0007-keycloak-user-org-identification.md)             | User and Organization Identity in Keycloak                 | Accepted | security, architecture                                                 |
| [ADR-0008](./0008-translation-i18n.md)                             | vue-i18n for Internationalization                          | Accepted | frontend, i18n                                                         |
| [ADR-0009](./0009-global-service-architecture.md)                  | Global Service Architecture                                | Accepted | backend, architecture                                                  |
| [ADR-0010](./0010-translation-background-tasks.md)                 | Translation Processing with BackgroundTasks                | Accepted | backend, performance                                                   |
| [ADR-0011](./0011-monorepo-vs-submodules.md)                       | Monorepo vs Git Submodules                                 | Accepted | architecture, git, monorepo, dx                                        |
| [ADR-0012](./0012-langgraph-agent-system.md)                       | LangGraph Agent System                                     | Accepted | backend, ai, orchestration, langgraph, agents                          |
| [ADR-0013](./0013-chat-service-replacement.md)                     | Chat Service Replacement                                   | Proposed | backend, ai, chat, azure-openai                                        |
| [ADR-0014](./0014-frontend-translation-management-strategy.md)     | Frontend Translation Management Strategy                   | Proposed | frontend, i18n, internationalization, vue, ci, architecture, monorepo  |
| [ADR-0015](./0015-multi-module-gateway.md)                         | Multi-Module API Gateway                                   | Accepted | backend, architecture, api-gateway, global-service, multi-module       |
| [ADR-0016](./0016-documentation-strategy.md)                       | Centralized Documentation and Tooling Strategy             | Proposed | documentation, architecture, migration, adr, onboarding, tooling       |
| [ADR-0017](./0017-promptfoo-llm-evaluation.md)                     | Promptfoo for LLM Prompt Evaluation                        | Proposed | backend, ai, testing, evaluation, promptfoo                            |
| [ADR-0018](./0018-role-and-permission-model.md)                    | Role and Permission Model                                  | Accepted | security, keycloak, rbac, permissions, multi-tenancy                   |
| [ADR-0019](./0019-security-audit-trail.md)                         | Security Audit Trail                                       | Proposed | security, audit, compliance, iso27001, observability                   |
| [ADR-0020](./0020-stream-module-architecture.md)                   | Stream Module Architecture                                 | Proposed | backend, architecture, stream, distribution                            |
| [ADR-0021](./0021-stream-newsletter-channel.md)                    | Stream Newsletter Channel                                  | Proposed | backend, frontend, stream, newsletter, email, mjml, np6                |
| [ADR-0022](./0022-uv-package-manager.md)                           | Migrate from Poetry to uv for Python Services              | Proposed | backend, tooling, python, dependencies, devex                          |
| [ADR-0023](./0023-mercure-scalable-topics-and-tokens.md)           | Scalable Mercure Topics and Token Management               | Accepted | backend, mercure, real-time, jwt, performance, scalability             |
| [ADR-0024](./0024-multi-tenant-architecture.md)                    | Multi-Tenant Architecture for ChapsMind                    | Proposed | architecture, multi-tenancy, keycloak, jwt, security, organization     |
| [ADR-0025](./0025-document-processing-pipeline-architecture.md)    | Document Processing Pipeline Architecture                  | Accepted | backend, pipeline, document-processing, quality-scoring, enrichment    |
| [ADR-0026](./0026-document-quality-scoring-processors-phase-1.md)  | Document Quality Scoring Processors Phase 1                | Accepted | backend, quality-scoring, document-processing, pipeline, processors    |
| [ADR-0027](./0027-document-quality-scoring-processors-phase-2.md)  | Document Quality Scoring Processors Phase 2                | Accepted | backend, quality-scoring, document-processing, pipeline, external-apis |
| [ADR-0028](./0028-document-deduplication-strategy.md)              | Document Deduplication Strategy                            | Accepted | backend, deduplication, document-processing, fingerprinting, simhash   |
| [ADR-0029](./0029-migration-target-to-chapsmind.md)                | Migration from Nuxt 4 to Vue 3                             | Accepted | frontend, vue, nuxt, migration, routing, unplugin-vue-router           |
| [ADR-0030](./0030-multi-provider-collection-architecture.md)       | Multi-Provider Collection Architecture                     | Proposed | backend, collection, provider, architecture, vendor-independence       |
| [ADR-0031](./0031-provider-agnostic-post-collection-processing.md) | Provider-Agnostic Post-Collection Processing               | Proposed | backend, collection, provider, post-processing, document-enrichment    |
| [ADR-0032](./0032-migration-backend-target-api-vers-chapsmind.md)  | Migration Backend Target API to ChapsMind                  | Proposed | backend, migration, target, chapsmind, global-service, monorepo        |
| [ADR-0033](./0033-elasticsearch-to-opensearch-migration.md)        | Elasticsearch to OpenSearch Migration                      | Accepted | backend, opensearch, elasticsearch, migration, api-platform            |
| [ADR-0034](./0034-apify-provider-implementation.md)                | Implementation of Apify Provider                           | Accepted | backend, collection, apify, provider, social-media, linkedin           |
| [ADR-0035](./0035-document-pipeline-pre-post-save-split.md)        | Split Document Pipeline into Pre-Save and Post-Save Phases | Accepted | backend, pipeline, document-processing, enrichment, deduplication      |

## Basil ADR Migration — Mapping Table

The 13 ADRs originally numbered `YYYY-NNN` in the basil repository were migrated into the unified ChapsMind sequence as part of ADR-0016 Phase 1.

| ChapsMind ID | Original Basil ID                                    | Title                                                      |
| ------------ | ---------------------------------------------------- | ---------------------------------------------------------- |
| ADR-0023     | ADR-2025-001                                         | Scalable Mercure Topics and Token Management               |
| ADR-0024     | ADR-2025-002                                         | Multi-Tenant Architecture for ChapsMind Integration        |
| ADR-0025     | ADR-2026-003                                         | Document Processing Pipeline Architecture                  |
| ADR-0026     | ADR-2026-004                                         | Document Quality Scoring Processors Phase 1                |
| ADR-0027     | ADR-2026-005                                         | Document Quality Scoring Processors Phase 2                |
| ADR-0028     | ADR-2026-006                                         | Document Deduplication Strategy                            |
| ADR-0029     | ADR-2026-007                                         | Migration from Nuxt 4 to Vue 3                             |
| ADR-0030     | ADR-2026-008                                         | Multi-Provider Collection Architecture                     |
| ADR-0031     | ADR-2026-009                                         | Provider-Agnostic Post-Collection Processing               |
| ADR-0032     | ADR-2026-010                                         | Migration Backend Target API to ChapsMind                  |
| ADR-0033     | ADR-2026-011 (elasticsearch-to-opensearch-migration) | Elasticsearch to OpenSearch Migration                      |
| ADR-0034     | ADR-2026-012                                         | Implementation of Apify Provider for Data Collection       |
| ADR-0035     | ADR-2026-011 (document-pipeline-pre-post-save-split) | Split Document Pipeline into Pre-Save and Post-Save Phases |

> **Note:** The basil repository contained two ADRs sharing the `2026-011` prefix (a numbering collision in the original repo). They are renumbered separately above.

## Status Definitions

- **Proposed**: Decision is under discussion
- **Accepted**: Decision has been made and is in effect
- **Deprecated**: Decision is no longer relevant
- **Superseded**: Decision has been replaced by a newer ADR

## Creating New ADRs

1. Copy `template.md` to a new file with the next sequential number
2. Follow the naming convention: `NNNN-short-descriptive-name.md`
3. Fill in all sections of the template
4. Update this README to include the new ADR in the index
5. Submit for review through the standard merge request process

## Related Documentation

- [Technical Architecture Document](../README.md)
- [Development Standards](../../../agent-os/standards/)
- [Product Mission](../../../agent-os/product/mission.md)
