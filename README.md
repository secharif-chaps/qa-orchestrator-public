# MINT - Market Intelligence Platform

<div align="center">

**AI-Powered Company Screening & Market Intelligence**

_The next generation of market intelligence tools - built from the ground up with AI_

[![Vue 3](https://img.shields.io/badge/Vue-3.5-4FC08D?style=for-the-badge&logo=vue.js&logoColor=white)](https://vuejs.org/)
[![TypeScript](https://img.shields.io/badge/TypeScript-5.8-3178C6?style=for-the-badge&logo=typescript&logoColor=white)](https://www.typescriptlang.org/)
[![Tailwind CSS](https://img.shields.io/badge/Tailwind-4.1-38B2AC?style=for-the-badge&logo=tailwind-css&logoColor=white)](https://tailwindcss.com/)
[![Vite](https://img.shields.io/badge/Vite-7.0-646CFF?style=for-the-badge&logo=vite&logoColor=white)](https://vitejs.dev/)
[![Pinia](https://img.shields.io/badge/Pinia-3.0-FFD859?style=for-the-badge&logo=vue.js&logoColor=black)](https://pinia.vuejs.org/)

</div>

---

## What is MINT?

**MINT** (**M**arket **INT**elligence) is the first module of a modular web application platform designed to revolutionize market intelligence. The **Screen** module enables comprehensive company screening by gathering and analyzing all publicly available information about companies online.

### Key Differentiators

- **AI-First Architecture**: Built from the ground up with AI, not legacy software with AI features bolted on
- **Superior Speed**: Faster data collection and analysis than traditional competitors
- **BAKUS Integration**: Connected to our proprietary data server for unmatched data collection capabilities
- **Modular Design**: Part of a future ecosystem where clients can select specific modules based on their needs

---

## Features

### Private Folders with Sharing

- Create private folders to organize companies
- Share folders with team members as **Reader** (view only) or **Writer** (can add companies)
- Owner-based access control with granular permissions
- Folder favorites for quick access

### Company Intelligence (Screen Module)

- Real-time company monitoring and screening
- Comprehensive company profiles with multi-source data validation
- Task-based workflow orchestration for data collection
- Multiple data views:
  - **Profile**: Business lines, revenue, employee count, leadership
  - **Team**: Organizational hierarchy and key personnel
  - **Jobs**: Current openings, hiring trends, department insights
  - **Products**: Product ranges, partnerships, private labels
  - **CSR**: Corporate responsibility initiatives
  - **Press**: Media coverage and news
  - **Timeline**: Key events and milestones

### Global Token System

- Unified token balance per organization
- Token consumption tracking (35 tokens per company)
- Transaction history and audit trail
- Admin token management

### Team & Organization Management

- Keycloak Organizations for multi-tenancy
- Role-based access control with permissions
- Team member management within organizations
- User invitation and role assignment

### ChapsE AI Assistant

- AI-powered assistant for market intelligence queries
- Contextual help and guidance
- Natural language interactions

### Admin Features

- Organization management (super-admin)
- Token allocation and monitoring
- Workflow configuration
- Task monitoring and management

---

## Tech Stack

### Frontend Framework

- **Vue 3** - Progressive JavaScript framework with Composition API
- **TypeScript** - Type-safe development
- **Tailwind CSS v4** - Utility-first CSS framework
- **Vite** - Lightning-fast build tool

### UI Components

- **Vuellar UI** (`@owlint/feathers-vue`) - ChapsVision Design System implementation
- **Reka UI** - Headless UI primitives for accessibility
- Custom components in `/components/ui/`

### State Management & Data Fetching

- **Pinia** - Global state management
- **Pinia Colada** - Async state management and data fetching with caching

### Routing & Internationalization

- **Vue Router** - Client-side routing with file-based routing (`unplugin-vue-router`)
- **Vue I18n** - Internationalization (English/French)

### Authentication & Authorization

- **Keycloak** - Identity and access management
- **Keycloak Organizations** - Multi-tenant organization support
- **OIDC Client** - OpenID Connect authentication
- Role-based permissions extracted from JWT tokens

---

## Permission System

MINT uses a simplified permission model integrated with Keycloak:

### Base Permissions

- `organization.read` - Base read-only access (default for all org users)
- `organization.write` - Can create folders and manage owned content

### Module Permissions

- `screen.create` - Can create companies (Screen module)
- `target.create` - Can create watchfiles (Target module - future)

### Admin Permissions

- `admin.organizations` - Super-admin access for organization management

### Folder Access Control

| Action             | Owner | Writer | Reader |
| ------------------ | ----- | ------ | ------ |
| View folder/items  | Yes   | Yes    | Yes    |
| Edit folder        | Yes   | No     | No     |
| Delete folder      | Yes   | No     | No     |
| Manage sharing     | Yes   | No     | No     |
| Create companies\* | Yes   | Yes    | No     |
| Delete companies   | Yes   | No     | No     |

\*Requires `screen.create` permission

---

## Project Structure

```
src/
├── api/              # API functions and HTTP client
├── components/       # Vue components
│   ├── ui/          # Base UI components (Card, Tag, Pagination, etc.)
│   ├── admin/       # Admin panel components
│   ├── chapse/      # ChapsE AI assistant
│   ├── companies/   # Company list components
│   ├── company/     # Company detail components
│   ├── dashboard/   # Dashboard widgets
│   ├── features/    # Feature-specific components
│   ├── folders/     # Folder management
│   ├── global/      # Layout (sidebar, appbar)
│   ├── home/        # Home page components
│   ├── settings/    # Settings components
│   ├── sidebar/     # Sidebar navigation
│   ├── team/        # Team management
│   ├── tokens/      # Token management UI
│   └── user/        # User components
├── composables/      # Composition functions
├── stores/          # Pinia stores for global state
├── queries/         # Pinia Colada queries for data fetching
├── mutations/       # Data mutation logic
├── pages/           # Page components (file-based routing)
├── types/           # TypeScript type definitions
├── i18n/            # Internationalization files
├── assets/          # Static assets
└── router/          # Vue Router configuration
```

---

## Quick Start

### Prerequisites

- **Node.js** `^20.19.0 || >=22.12.0`
- **Yarn 4** (via corepack) or npm

### Installation

```bash
# Clone the repository
git clone <repository-url>
cd mint-front

# Enable corepack (provides Yarn 4)
corepack enable

# Install dependencies
yarn install

# Start development server
yarn dev
```

The application will be available at `http://localhost:3000` with hot module replacement enabled.

### Available Scripts

```bash
# Development
yarn dev              # Start development server

# Building
yarn build            # Build for production
yarn build-only       # Build without type checking
yarn preview          # Preview production build

# Code Quality
yarn type-check       # Run TypeScript type checking
yarn lint             # Run ESLint with auto-fix
yarn format           # Format code with Prettier
```

---

## Configuration

### Environment Variables

Create a `.env.local` file in the root directory:

```env
# API Configuration
VITE_API_BASE_URL=your_api_url
VITE_OIDC_AUTHORITY=your_keycloak_url
VITE_OIDC_CLIENT_ID=your_client_id

# Feature Flags
VITE_ENABLE_DEVTOOLS=true
```

### Authentication Setup

MINT uses Keycloak for authentication with Organizations support. Configure your Keycloak realm with:

- **Redirect URIs**: `http://localhost:3000/auth/callback`
- **Silent Refresh URI**: `http://localhost:3000/auth/silent-callback`
- **Post Logout URI**: `http://localhost:3000/login`
- **Organizations**: Enable Keycloak Organizations for multi-tenancy

---

## Architecture

### Data Flow Pattern

MINT follows a structured data flow pattern using Pinia Colada:

1. **API Layer** (`/api`): Pure functions for HTTP requests
2. **Query Layer** (`/queries`): Reactive data fetching with caching
3. **Mutation Layer** (`/mutations`): Data modifications with cache invalidation
4. **Store Layer** (`/stores`): Global state management
5. **Component Layer**: UI components with reactive data

### Key Architectural Decisions

- **Composition API Only**: No Options API usage
- **File-based Routing**: Automatic route generation from `/pages` directory
- **Type-first Development**: TypeScript interfaces for all data structures
- **Vuellar Design System**: ChapsVision design tokens and components
- **Organization-based Multi-tenancy**: Keycloak Organizations for isolation

---

## Development Guidelines

### Code Standards

- Always use Composition API with `<script setup lang="ts">`
- Always use TypeScript with proper typing
- Prefer `interface` over `type` for object definitions
- Use Vuellar components from `@owlint/feathers-vue` for UI
- Use semantic color tokens (never hardcode colors)
- Use flexbox with `gap` for spacing (never margin between siblings)
- Follow the established project structure

### Code Quality

#### Prerequisites

Install [Task](https://taskfile.dev/) (task runner):

```bash
# macOS
brew install go-task

# Linux (snap)
sudo snap install task --classic

# Or see https://taskfile.dev/installation/
```

#### Available Commands

| Command               | Description                                   |
| --------------------- | --------------------------------------------- |
| `task lint`           | Run all linters (ESLint, Prettier, Stylelint) |
| `task lint:eslint`    | ESLint only                                   |
| `task lint:prettier`  | Prettier only (check mode)                    |
| `task lint:stylelint` | Stylelint only                                |
| `task lint:fix`       | Auto-fix all linting issues                   |
| `task lint:staged`    | Lint only staged files (via lint-staged)      |
| `task hook:install`   | Install git hooks (pre-commit)                |

#### Recommended Workflow

```bash
# 1. Install git hooks (once after cloning)
task hook:install

# 2. Work on your code...

# 3. Before committing, fix all issues
task lint:fix

# 4. Verify everything passes
task lint

# 5. Commit (lint-staged runs automatically via pre-commit hook)
git commit -m "your message"
```

The pre-commit hook automatically runs ESLint, Prettier and Stylelint on staged files and fixes what it can. If an error can't be auto-fixed, the commit is blocked.

#### CI Pipeline

The GitLab CI runs the same 3 linters on every push and every MR. A failing linter blocks the pipeline.

### Git Commit Convention

MINT uses Conventional Commits with Gitmoji:

```bash
# Format: <gitmoji> <type>(scope): <description>

✨ feat(folders): add folder sharing modal
🐛 fix(auth): resolve redirect loop issue
💄 style(ui): improve button hover states
♻️ refactor(api): optimize query performance
📝 docs: update README with new features
```

---

## Roadmap

### Current: Screen Module

- [x] Company screening and profiling
- [x] Private folders with sharing
- [x] Global token system
- [x] Team management
- [x] AI-powered data collection
- [x] Real-time task monitoring

### Upcoming: Target Module

- [ ] Watchfile creation and management
- [ ] Continuous company monitoring
- [ ] Alert notifications

### Future: Global Service Architecture

- [ ] Centralized organization service
- [ ] Cross-module folder support
- [ ] Unified token management across modules

---

## License

This is proprietary software. All rights reserved.

---

## Support

For support and inquiries about MINT:

- **Email**: nmercier@chapsvision.com

---

<div align="center">

**Built with care for the future of Market Intelligence**

_MINT - Where AI meets Market Intelligence_

</div>
