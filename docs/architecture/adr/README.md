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

| ID                                                             | Title                                          | Status   | Tags                                                                  |
| -------------------------------------------------------------- | ---------------------------------------------- | -------- | --------------------------------------------------------------------- |
| [ADR-0001](./0001-vue3-composition-api.md)                     | Vue.js 3 with Composition API                  | Accepted | frontend, framework                                                   |
| [ADR-0002](./0002-fastapi-backend.md)                          | FastAPI Backend Framework                      | Accepted | backend, framework                                                    |
| [ADR-0003](./0003-keycloak-authentication.md)                  | Keycloak for Authentication                    | Accepted | security, authentication                                              |
| [ADR-0004](./0004-dify-ai-orchestration.md)                    | Dify for AI Orchestration                      | Accepted | ai, orchestration                                                     |
| [ADR-0005](./0005-multi-tenancy-keycloak-organizations.md)     | Multi-Tenancy with Keycloak Organizations      | Accepted | security, multi-tenancy                                               |
| [ADR-0006](./0006-pinia-colada-data-fetching.md)               | Pinia Colada for Data Fetching                 | Accepted | frontend, state-management                                            |
| [ADR-0007](./0007-keycloak-user-org-identification.md)         | User and Organization Identity in Keycloak     | Accepted | security, architecture                                                |
| [ADR-0008](./0008-translation-i18n.md)                         | vue-i18n for Internationalization              | Accepted | frontend, i18n                                                        |
| [ADR-0009](./0009-global-service-architecture.md)              | Global Service Architecture                    | Accepted | backend, architecture                                                 |
| [ADR-0010](./0010-translation-background-tasks.md)             | Translation Processing with BackgroundTasks    | Accepted | backend, performance                                                  |
| [ADR-0011](./0011-monorepo-vs-submodules.md)                   | Monorepo vs Git Submodules                     | Accepted | architecture, git, monorepo, dx                                       |
| [ADR-0012](./0012-langgraph-agent-system.md)                   | LangGraph Agent System                         | Accepted | backend, ai, orchestration, langgraph, agents                         |
| [ADR-0013](./0013-chat-service-replacement.md)                 | Chat Service Replacement                       | Proposed | backend, ai, chat, azure-openai                                       |
| [ADR-0014](./0014-frontend-translation-management-strategy.md) | Frontend Translation Management Strategy       | Proposed | frontend, i18n, internationalization, vue, ci, architecture, monorepo |
| [ADR-0015](./0015-multi-module-gateway.md)                     | Multi-Module API Gateway                       | Accepted | backend, architecture, api-gateway, global-service, multi-module      |
| [ADR-0016](./0016-documentation-strategy.md)                   | Centralized Documentation and Tooling Strategy | Proposed | documentation, architecture, migration, adr, onboarding, tooling      |
| [ADR-0017](./0017-promptfoo-llm-evaluation.md)                 | Promptfoo for LLM Prompt Evaluation            | Proposed | backend, ai, testing, evaluation, promptfoo                           |
| [ADR-0018](./0018-role-and-permission-model.md)                | Role and Permission Model                      | Proposed | security, keycloak, rbac, permissions, multi-tenancy                  |
| [ADR-0019](./0019-security-audit-trail.md)                     | Security Audit Trail                           | Proposed | security, audit, compliance, iso27001, observability                  |

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
