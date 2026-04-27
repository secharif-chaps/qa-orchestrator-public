# ChapsMind

A modern platform for monitoring companies online. Built as a monorepo with a Vue 3 frontend, FastAPI backend, and infrastructure-as-code.

## Project Structure

```
chapsmind/
├── apps/
│   ├── front/              # Vue 3 frontend (TypeScript, Tailwind CSS v4)
│   ├── screen/             # FastAPI backend (Python, SQLAlchemy)
│   └── global-service/     # API gateway
├── infra/                  # Docker Compose, Keycloak realm, nginx, scripts
├── docs/                   # Documentation (getting started, ADRs, architecture)
├── agent-os/               # Product docs, specs, standards
├── .claude/                # Claude AI agents, commands, skills
├── Taskfile.yml            # Task runner (run `task` to list commands)
└── CLAUDE.md               # AI assistant instructions
```

## Quick Start

```bash
# 1. Clone
git clone ssh://git@git.mediaspeech.com:17890/chapsmind/chapsmind.git
cd chapsmind

# 2. Check prerequisites
task doctor

# 3. One-shot setup (env, deps, services, keycloak, migrations)
task init
```

Open [http://localhost](http://localhost) in your browser.

See [docs/getting-started.md](docs/getting-started.md) for detailed setup instructions, prerequisites, test users, and troubleshooting.

## Daily Usage

```bash
task up          # Start all services
task down        # Stop all services
task restart     # Restart all services
task logs        # Tail all logs
task lint        # Lint all projects (front + screen)
task test        # Run all tests
```

Run `task` with no arguments to see all available commands.

## Git Workflow

Branch naming: `feat/TAR-xxx-description`, `fix/TAR-xxx-description`, etc.

Commit format: [Gitmoji](https://gitmoji.dev/) + [Conventional Commits](https://www.conventionalcommits.org/)

```
<gitmoji> <type>(scope): TAR-xxx description
```

Examples:

```bash
✨ feat(front): TAR-42 add company search filters
🐛 fix(screen): TAR-15 resolve pagination offset error
```

A pre-commit hook enforces the format and runs linters on staged files.

**Rules**: never commit to `main`, always use feature branches and Merge Requests.
