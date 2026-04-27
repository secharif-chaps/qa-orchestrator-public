# Contributing to ChapsMind

## Prerequisites

- [Docker Desktop](https://www.docker.com/products/docker-desktop/)
- [VS Code](https://code.visualstudio.com/) + [Dev Containers extension](https://marketplace.visualstudio.com/items?itemName=ms-vscode-remote.remote-containers)
  - **OR** [JetBrains Gateway](https://www.jetbrains.com/remote-development/gateway/) for PHPStorm users

That's it. Everything else (Node, Python, Yarn, Poetry, Ruff, Lefthook, Taskfile) is installed automatically inside the Dev Container.

## Getting Started

### Option A: Dev Container (recommended)

1. Clone the repository:

   ```bash
   git clone <repo-url> && cd chapsmind-monorepo
   ```

2. Open in VS Code, accept "Reopen in Container" when prompted
   (or `Ctrl+Shift+P` → "Dev Containers: Reopen in Container")

3. Wait for the container to build (~5 min first time, cached after)

4. Start all services:

   ```bash
   task up
   ```

5. Open the app:
   - Frontend: http://localhost
   - Backend API: http://localhost/api
   - RabbitMQ: http://localhost:15672

### Option B: Local setup (without Dev Container)

1. Install: Node.js 20+, Yarn, Python 3.9+, Poetry, [Task](https://taskfile.dev/), Lefthook
2. Clone and run:
   ```bash
   git clone <repo-url> && cd chapsmind-monorepo
   task init
   ```

## Daily Workflow

```bash
task              # Show all available commands
task up           # Start all services
task front:dev    # Start frontend with HMR
task logs         # Tail service logs
task migrate      # Run database migrations
task screen:test  # Run screen backend tests
task lint         # Lint all projects
```

## Test Users

| User            | Password       | Permissions       |
| --------------- | -------------- | ----------------- |
| admin           | admin123       | Full access       |
| company_manager | manager123     | Company CRUD      |
| company_viewer  | viewer123      | Company read-only |
| team_viewer     | teamviewer123  | Team read-only    |
| team_manager    | teammanager123 | Team management   |
| no_access       | noaccess123    | No permissions    |

## Branch Naming

```
feat/TAR-xxx-description     # New feature
fix/TAR-xxx-description      # Bug fix
refactor/description         # Code refactoring
chore/description            # Maintenance
```

## Commit Format

```
<gitmoji> <type>: <description>

Examples:
  ✨ feat: add user authentication system
  🐛 fix: resolve validation error for company names
  ♻️ refactor: simplify task orchestration
  🗃️ feat: add translation column to companies table
```

## Code Standards

- **Frontend**: Vue 3 Composition API, TypeScript strict, ESLint + Prettier
- **Backend Python**: FastAPI, type hints, Ruff (lint + format)
- **All code, comments, logs, and variable names in English**
- See `CLAUDE.md` for detailed conventions

## Merge Request Guidelines

1. One branch per feature, one MR per feature (even if cross-stack)
2. Prefix branch with ticket number: `feat/TAR-42-translation`
3. CI must be green before merge
4. Reviewers are auto-assigned based on changed files (CODEOWNERS)
5. Keep MRs under ~500 lines. Split larger changes into sequential MRs.
