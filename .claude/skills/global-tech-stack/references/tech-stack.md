# Tech Stack

## Overview

Basil (Target module of Chapsmind) is built as a modern, AI-first market intelligence platform focused on automated monitoring and watchfile management. It features a clear separation between a PHP/Symfony backend, a Vue/Nuxt frontend, AI/workflow orchestration via N8N, and containerized infrastructure.

---

## Frontend

### Current Stack

| Category                 | Technology             | Version             | Notes                                      |
| ------------------------ | ---------------------- | ------------------- | ------------------------------------------ |
| **Framework**            | Nuxt.js                | ^4.0.0              | Vue 3 meta-framework with SSR capabilities |
| **Language**             | TypeScript             | ^5.9.3              | Strict mode enabled                        |
| **Build Tool**           | Vite                   | ^7.2.6              | Fast HMR, optimized builds                 |
| **Styling**              | Tailwind CSS           | ^4.1.17             | Utility-first, semantic color tokens       |
| **UI Components**        | Reka UI + Feathers Vue | ^2.0.2 / ^0.3.15    | Accessible component libraries             |
| **State Management**     | Pinia                  | ^3.0.4              | Global state with persistence plugin       |
| **Data Fetching**        | Pinia Colada           | ^0.17.9             | Queries and mutations with caching         |
| **Routing**              | Vue Router             | File-based via Nuxt | Automatic route generation                 |
| **Internationalization** | @nuxtjs/i18n           | ^10.0.0             | Multi-language support (EN/FR)             |
| **Charts**               | Chart.js               | ^4.5.1              | Data visualization                         |
| **Flow Diagrams**        | Vue Flow               | ^1.48.0             | Graph-based visualizations                 |
| **Utilities**            | VueUse                 | ^14.0.0             | Composition utilities                      |
| **Validation**           | Zod                    | ^3.25.76            | Runtime type validation                    |

### Package Manager

- **Yarn** 4.12.0 for dependency management

### Code Quality

- **ESLint 9** with Vue and TypeScript plugins
- **Prettier** for code formatting with Tailwind plugin
- **vue-tsc** for type checking

---

## Backend

### Core Framework

| Category       | Technology          | Version | Notes                               |
| -------------- | ------------------- | ------- | ----------------------------------- |
| **Framework**  | Symfony             | 7.3.\*  | PHP web framework                   |
| **Language**   | PHP                 | ~8.4.0  | Type hints throughout, strict mode  |
| **API Layer**  | API Platform        | ^4.1.0  | REST API with OpenAPI documentation |
| **ORM**        | Doctrine ORM        | ^3.0    | Database abstraction                |
| **Migrations** | Doctrine Migrations | ^3.2    | Database schema versioning          |

### Database & Storage

| Category             | Technology    | Version           | Notes                        |
| -------------------- | ------------- | ----------------- | ---------------------------- |
| **Primary Database** | PostgreSQL    | 17                | Main data store              |
| **Full-Text Search** | Elasticsearch | ^8.19             | Document indexing and search |
| **Caching**          | Valkey        | 8 via Predis ^3.0 | Caching, sessions            |

### Authentication

| Category                 | Technology                 | Notes                           |
| ------------------------ | -------------------------- | ------------------------------- |
| **Identity Provider**    | Keycloak 26.1              | Self-hosted OIDC/SSO            |
| **Frontend Integration** | keycloak-js 26.2.1         | Client-side auth                |
| **JWT Handling**         | firebase/php-jwt ^6.11.1   | Token validation                |
| **Test Auth**            | X-Test-Auth-User-ID header | Integration test authentication |

### Message Queue

| Category           | Technology      | Notes                                                          |
| ------------------ | --------------- | -------------------------------------------------------------- |
| **Message Broker** | RabbitMQ 4      | Via Symfony Messenger AMQP                                     |
| **Queue Types**    | Priority queues | `async_priority_high`, `async_priority_low`, `agent_responses` |

### Real-Time

| Category        | Technology                    | Notes                    |
| --------------- | ----------------------------- | ------------------------ |
| **Protocol**    | Mercure                       | Server-Sent Events (SSE) |
| **Integration** | symfony/mercure-bundle ^0.3.9 | Real-time updates        |

### HTTP & Templating

| Category        | Technology         | Notes                                   |
| --------------- | ------------------ | --------------------------------------- |
| **Templating**  | Twig               | With Markdown, Inky, CSS Inliner extras |
| **HTTP Client** | Symfony HttpClient | Async support                           |

### Architecture Pattern

The backend follows **Domain-Driven Design (DDD)** with clear separation:

```
api/src/
├── Domain/           # Core business logic, entities, value objects
├── Application/      # Use cases as Action/Handler pairs
├── Infrastructure/   # Doctrine repositories, external integrations
└── UserInterface/    # HTTP controllers, CLI commands
```

---

## AI & Workflow Orchestration

### AI Platform

| Category                | Technology | Notes                                            |
| ----------------------- | ---------- | ------------------------------------------------ |
| **Workflow Automation** | N8N        | Visual workflow builder for AI agents            |
| **LLM Gateway**         | LiteLLM    | Multi-provider support (Gemini, Claude, Mistral) |
| **Prompt Templates**    | Twig       | Versioned prompts in `api/templates/prompts/`    |

### Workflow Architecture

```
User Chat Message
       |
       v
+----------------------+
| 1-Orchestrator Router|  <-- Routes to appropriate tool
+----------------------+
       |
       +---> 2-Chat Session Message
       +---> 3-Tool WatchFile Builder (with sub-workflows 3a-3c)
       +---> 4-Tool Rename WatchFile
       +---> 5-Tool Classify WatchFile
       +---> 6-Tool DeepSearch Agent (with sub-workflows 6a-6e)
```

**Key Points:**

- N8N orchestrates AI agent operations
- Each tool has dedicated workflows for specific tasks
- RabbitMQ handles async message processing between API and N8N
- Prompts are versioned and templated for consistency

---

## Authentication & Authorization

### Identity Management

| Category              | Technology             | Notes            |
| --------------------- | ---------------------- | ---------------- |
| **Identity Provider** | Keycloak 26.1          | Self-hosted      |
| **Multi-Tenancy**     | Keycloak Organizations | Client isolation |
| **Frontend Auth**     | keycloak-js 26.2.1     | OIDC client      |

### Architecture Notes

- **No users table** in application database
- All user/organization data managed in Keycloak
- Database stores Keycloak IDs as UUID references
- TestAuthenticator for integration tests via `X-Test-Auth-User-ID` header

---

## Infrastructure

### Containerization

| Category          | Technology     | Notes                            |
| ----------------- | -------------- | -------------------------------- |
| **Containers**    | Docker         | Development and production       |
| **Compose**       | Docker Compose | Local development                |
| **Reverse Proxy** | Caddy          | HTTPS, Mercure hub               |
| **Task Runner**   | Taskfile       | `task -l` for available commands |

### Docker Services

| Service           | Port       | Description                    |
| ----------------- | ---------- | ------------------------------ |
| **PWA**           | 3000       | Nuxt.js application            |
| **API**           | 8000       | Symfony API server             |
| **Keycloak**      | 8080       | Authentication service         |
| **PostgreSQL**    | 5432       | Database                       |
| **Elasticsearch** | 9200       | Search engine                  |
| **Valkey**        | 6379       | Cache                          |
| **RabbitMQ**      | 5672/15672 | Message broker + Management UI |
| **N8N**           | 5678       | Workflow automation            |
| **Mercure**       | 3080       | Real-time hub                  |
| **Mailpit**       | 8025       | Email testing                  |
| **Kibana**        | 5601       | Log analysis                   |

### Access Points (Development)

| Service                 | URL                             |
| ----------------------- | ------------------------------- |
| **Main Application**    | https://basil.local             |
| **API Documentation**   | https://basil.local/api/docs    |
| **GraphQL Playground**  | https://basil.local/api/graphql |
| **Keycloak Admin**      | https://auth.basil.local/admin  |
| **N8N Workflows**       | https://n8n.basil.local         |
| **Kibana**              | https://kibana.basil.local      |
| **Mailpit**             | http://localhost:8025           |
| **RabbitMQ Management** | http://localhost:15672          |

---

## Testing

### Backend Testing (PHPUnit)

| Category      | Technology                | Version | Notes                       |
| ------------- | ------------------------- | ------- | --------------------------- |
| **Framework** | PHPUnit                   | ^12.1   | Test framework              |
| **Factories** | Zenstruck Foundry         | ^2.6    | Test data generation        |
| **Isolation** | DAMA Doctrine Test Bundle | ^8.3    | Transaction-based isolation |
| **Messenger** | zenstruck/messenger-test  | ^1.12   | Message queue testing       |

**Test Types:**

- **Unit Tests** (`tests/Units/`): Domain logic with NullGateway pattern
- **Integration Tests** (`tests/Integration/`): Full HTTP-to-database flow
- **RAM Database**: Test database runs in memory for speed (10x faster)

### Frontend Testing (Vitest)

| Category      | Technology          | Version            | Notes             |
| ------------- | ------------------- | ------------------ | ----------------- |
| **Framework** | Vitest              | ^4.0.0             | Test runner       |
| **DOM**       | Happy-DOM / JSDOM   | ^20.0.11 / ^27.0.0 | DOM simulation    |
| **Utilities** | Vue Testing Library | ^8.1.0             | Component testing |
| **Coverage**  | @vitest/coverage-v8 | ^4.0.0             | Coverage reports  |

---

## Development Tools

### Version Control

| Category       | Technology  | Notes                   |
| -------------- | ----------- | ----------------------- |
| **VCS**        | Git         | Feature branch workflow |
| **CI/CD**      | GitLab CI   | .gitlab-ci.yml          |
| **Project ID** | basil/basil | GitLab repository       |

### Code Quality

| Category            | Frontend  | Backend                    |
| ------------------- | --------- | -------------------------- |
| **Linting**         | ESLint 9  | ECS (Easy Coding Standard) |
| **Formatting**      | Prettier  | ECS (PSR-12 based)         |
| **Static Analysis** | vue-tsc   | PHPStan (level 9)          |
| **CSS Linting**     | Stylelint | -                          |

### Task Commands

```bash
# Root level
task -l                    # List all tasks
task lint                  # Run all linters

# API
task api:test              # Run PHPUnit tests
task api:cs:fix            # Fix PHP code style
task api:phpstan:check     # Static analysis

# PWA
task pwa:eslint:fix        # Fix TypeScript/Vue linting

# N8N
task n8n:export-workflows  # Export workflows
task n8n:test -- <dataset> # Run workflow tests
```

---

## Third-Party Integrations

### Current

| Service      | Purpose                          |
| ------------ | -------------------------------- |
| **N8N**      | AI workflow automation           |
| **LiteLLM**  | Multi-provider LLM gateway       |
| **Keycloak** | Identity and access management   |
| **Mercure**  | Real-time updates                |
| **Sentry**   | Error tracking (production only) |

### AI Providers (via LiteLLM)

| Provider    | Use Case             |
| ----------- | -------------------- |
| **Gemini**  | Primary LLM provider |
| **Claude**  | Alternative provider |
| **Mistral** | Alternative provider |

---

## Key Domain Concepts

### WatchFile (Core Entity)

WatchFiles progress through states:

```
NEW → NEEDS_ANALYZED → QUESTIONS_GENERATED → SEARCH_QUERY_GENERATED
    → SEARCH_RESULTS_RETRIEVED → ACTORS_DETECTED → SOURCES_DETECTED
    → MONITORING_TYPE_DETECTED
```

### Related Entities

| Entity                | Purpose                                              |
| --------------------- | ---------------------------------------------------- |
| **Document**          | Documents linked to WatchFile, Elasticsearch indexed |
| **Actor**             | Tracked actors extracted from analysis               |
| **Source**            | Data sources for monitoring                          |
| **StrategicQuestion** | Questions generated for DeepSearch                   |
| **Chat**              | User conversations with AI agent                     |

---

## Project Structure

```
basil/
├── api/                          # Backend (Symfony 7.3 + API Platform)
│   ├── src/
│   │   ├── Domain/              # Core business logic (DDD)
│   │   ├── Application/         # Use cases (Action/Handler pattern)
│   │   ├── Infrastructure/      # External concerns (Doctrine, APIs)
│   │   └── UserInterface/       # HTTP controllers, CLI commands
│   ├── config/packages/         # Service configurations
│   ├── migrations/              # Doctrine migrations
│   ├── templates/prompts/       # AI prompt templates (Twig)
│   └── tests/
│       ├── Units/               # Unit tests
│       └── Integration/         # API integration tests
│
├── pwa/                         # Frontend (Nuxt 4 + Vue 3)
│   ├── components/              # Vue components
│   ├── composables/             # Reusable logic
│   ├── stores/                  # Pinia stores
│   ├── pages/                   # Nuxt pages
│   ├── locales/                 # i18n translations (en.json, fr.json)
│   └── tests/                   # Vitest tests
│
├── docker/
│   ├── n8n/
│   │   ├── workflows/           # N8N workflow definitions (JSON)
│   │   ├── credentials/         # N8N credentials
│   │   └── tests/datasets/      # Test datasets
│   └── keycloak/                # Keycloak realm configuration
│
├── agent-os/                    # AI agent configuration and standards
│   ├── product/                 # Product documentation
│   └── standards/               # Coding standards and guidelines
│
├── docs/                        # Project documentation
├── compose.yaml                 # Docker Compose configuration
├── compose.override.yaml        # Development overrides
└── Taskfile.yaml                # Root task definitions
```
