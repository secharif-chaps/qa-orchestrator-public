# ADR: Ameliorations Developer Experience (DX)

**Status**: Proposed
**Date**: 2026-02-25
**Author**: Engineering Team
**Context**: Complementaire a l'ADR monorepo-vs-submodules

---

## 1. Contexte

L'audit du workspace ChapsMind revele plusieurs lacunes DX independantes de la question monorepo vs submodules. Ces ameliorations sont applicables **des maintenant** sur la structure actuelle, et seront encore plus pertinentes apres une eventuelle migration monorepo.

### Equipe

|                  | Detail                                      |
| ---------------- | ------------------------------------------- |
| **Taille**       | 14 developpeurs                             |
| **OS**           | ~9 Windows, 2 macOS, 3 Linux                |
| **IDE**          | PHPStorm (devs PHP) + VS Code (le reste)    |
| **Organisation** | 2 equipes feature-oriented, touchent a tout |

### Constats

| Constat                                        | Impact                                                                                                                  |
| ---------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------- |
| Pas de Dev Container                           | 9 devs Windows doivent installer Node, Python, Poetry, pnpm manuellement. Problemes de paths, line endings, permissions |
| Aucun point d'entree unifie pour les commandes | Chaque dev memorise N commandes dans N dossiers                                                                         |
| Pas de pre-commit hooks                        | Du code qui ne lint pas arrive en CI, aller-retour de 5+ min                                                            |
| Pas de `.env.example`                          | Un nouveau dev ne sait pas quelles variables configurer                                                                 |
| Pas de doc d'onboarding humain                 | `CLAUDE.md` existe (pour l'IA), rien pour les humains                                                                   |
| Pas de formatter configure dans le backend     | Pas de ruff/black/isort dans `pyproject.toml`                                                                           |
| Pas de `.editorconfig`                         | Formatage inconsistant entre devs front (2 spaces) et back (4 spaces)                                                   |

---

## 2. Ameliorations proposees

### 2.1. Makefile racine — point d'entree unique

**Probleme** : pour lancer le projet, un dev doit connaitre :

```bash
cd infra && docker compose up -d --build          # demarrer
cd infra && docker compose exec backend alembic upgrade head  # migrer
cd front && pnpm install && pnpm dev               # front
cd infra && docker compose logs -f backend         # logs
```

Aucune decouverte possible. Il faut lire le CLAUDE.md ou demander a un collegue.

**Solution** : un Makefile a la racine du projet. Chaque dev tape `make` et voit toutes les commandes disponibles.

```makefile
# Makefile

COMPOSE = docker compose -f infra/docker-compose.yml

.PHONY: help up down restart logs migrate seed front-dev front-lint front-build back-lint back-test back-shell db-shell

# ─── Infrastructure ────────────────────────────────
up:                          ## Start all services (docker compose up)
	$(COMPOSE) up -d --build

down:                        ## Stop all services
	$(COMPOSE) down

restart:                     ## Restart all services
	$(COMPOSE) restart

logs:                        ## Tail all service logs
	$(COMPOSE) logs -f

# ─── Database ──────────────────────────────────────
migrate:                     ## Run Alembic migrations
	$(COMPOSE) exec backend alembic upgrade head

migrate-status:              ## Show current migration version
	$(COMPOSE) exec backend alembic current

seed:                        ## Seed sample data
	$(COMPOSE) exec backend python seed-sample-data.py

db-shell:                    ## Open psql shell
	$(COMPOSE) exec db psql -U postgres -d mint_db

# ─── Frontend ──────────────────────────────────────
front-dev:                   ## Start frontend dev server (HMR)
	cd front && pnpm dev

front-lint:                  ## Lint + fix frontend code
	cd front && pnpm lint

front-typecheck:             ## Run TypeScript type checking
	cd front && pnpm run type-check

front-build:                 ## Build frontend for production
	cd front && pnpm build

# ─── Backend ──────────────────────────────────────
back-lint:                   ## Lint backend code (ruff)
	$(COMPOSE) exec backend ruff check .

back-format:                 ## Format backend code (ruff)
	$(COMPOSE) exec backend ruff format .

back-test:                   ## Run backend tests
	$(COMPOSE) exec backend pytest

back-shell:                  ## Open bash shell in backend container
	$(COMPOSE) exec backend bash

# ─── Help ──────────────────────────────────────────
help:                        ## Show this help
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-20s\033[0m %s\n", $$1, $$2}'

.DEFAULT_GOAL := help
```

**Resultat** : `make` affiche une aide coloree. `make up` lance tout. `make migrate` migre. Zero memorisation.

> **Alternative** : [Taskfile](https://taskfile.dev/) (syntaxe YAML, cross-platform, auto-completion). Le Makefile a l'avantage de ne necessiter aucune installation supplementaire.

---

### 2.2. Pre-commit hooks — qualite avant le push

**Probleme** : pas de Husky (front), pas de pre-commit (back). Un dev peut push du code qui ne lint pas. La CI le rejettera, mais apres un cycle de build complet.

**Solution** : [Lefthook](https://github.com/evilmartians/lefthook) — un outil de git hooks polyglot (Go binary, pas de dependance Node ou Python). Un seul fichier de config pour front et back.

```yaml
# lefthook.yml (racine du projet)

pre-commit:
  parallel: true
  commands:
    front-lint:
      glob: 'apps/front/**/*.{ts,vue,js}'
      run: cd apps/front && pnpm lint {staged_files}

    front-typecheck:
      glob: 'apps/front/**/*.{ts,vue}'
      run: cd apps/front && pnpm vue-tsc --noEmit

    back-lint:
      glob: 'apps/back/**/*.py'
      run: cd apps/back && ruff check {staged_files}

    back-format:
      glob: 'apps/back/**/*.py'
      run: cd apps/back && ruff format --check {staged_files}

pre-push:
  commands:
    front-build:
      glob: 'apps/front/**/*'
      run: cd apps/front && pnpm build
```

**Installation** :

```bash
# macOS
brew install lefthook

# Linux (binary, pas de runtime)
curl -1sLf 'https://dl.cloudsmith.io/public/evilmartians/lefthook/setup.deb.sh' | sudo -E bash
sudo apt install lefthook

# Activation dans le repo
lefthook install
```

**Pourquoi Lefthook et pas Husky + pre-commit** :

| Critere      | Husky + pre-commit       | Lefthook                   |
| ------------ | ------------------------ | -------------------------- |
| Installation | 2 outils (Node + Python) | 1 binary Go                |
| Config       | 2 fichiers dans 2 repos  | 1 fichier YAML a la racine |
| Polyglot     | Chacun sa stack          | Natif                      |
| Vitesse      | Moyenne (Node startup)   | Rapide (binary compile)    |
| Parallel     | Non natif                | Oui (`parallel: true`)     |

> **Doc** : https://github.com/evilmartians/lefthook

---

### 2.3. `.env.example` — configuration documentee

**Probleme** : le `.gitignore` exclut `.env` (correct), mais aucun `.env.example` n'existe. Un nouveau dev ne sait pas quelles variables sont necessaires, quelles valeurs par defaut utiliser, ni quels secrets demander.

**Solution** : un `.env.example` a la racine, versionne, avec des valeurs par defaut fonctionnelles pour le dev local.

```env
# .env.example
# Copy to .env and adjust values as needed
# cp .env.example .env

# ─── Database ──────────────────────────────────────
POSTGRES_HOST=db
POSTGRES_PORT=5432
POSTGRES_DB=mint_db
POSTGRES_USER=postgres
POSTGRES_PASSWORD=postgres

# ─── Keycloak ─────────────────────────────────────
KEYCLOAK_URL=https://sso.dwcode.team/auth
KEYCLOAK_REALM=mint
KEYCLOAK_CLIENT_ID=mint-front
KEYCLOAK_ADMIN_CLIENT_ID=admin-cli
KEYCLOAK_ADMIN_CLIENT_SECRET=changeme       # Ask a team member

# ─── RabbitMQ ─────────────────────────────────────
RABBITMQ_HOST=target-rabbitmq
RABBITMQ_PORT=5672
RABBITMQ_USER=guest
RABBITMQ_PASSWORD=guest

# ─── Dify (AI Platform) ──────────────────────────
DIFY_API_URL=https://dify.example.com/v1
DIFY_API_KEY=changeme                        # Ask a team member

# ─── Backend ─────────────────────────────────────
BACKEND_SECRET_KEY=dev-secret-key-change-in-prod
BACKEND_CORS_ORIGINS=http://localhost:3000

# ─── Frontend ────────────────────────────────────
VITE_API_BASE_URL=http://localhost:8000/api
VITE_KEYCLOAK_URL=https://sso.dwcode.team/auth
VITE_KEYCLOAK_REALM=mint
VITE_KEYCLOAK_CLIENT_ID=mint-front
```

Ajouter au Makefile :

```makefile
init:                        ## First-time setup: copy env, install deps
	@test -f .env || cp .env.example .env && echo "Created .env from .env.example"
	cd front && pnpm install
	$(COMPOSE) up -d --build
	$(COMPOSE) exec backend alembic upgrade head
	@echo "Ready! Run 'make' for available commands."
```

Un nouveau dev fait `make init` et tout est pret.

---

### 2.4. `.editorconfig` — formatage consistant entre IDE

**Probleme** : pas de `.editorconfig`. Chaque dev utilise ses propres settings. Le front utilise 2 spaces (convention JS/TS), le back utilise 4 spaces (convention Python). Sans config partagee, les diffs sont pollues par des changements de whitespace.

**Solution** : un `.editorconfig` a la racine, respecte automatiquement par la plupart des IDE (VS Code, IntelliJ, Vim).

```ini
# .editorconfig
root = true

[*]
charset = utf-8
end_of_line = lf
insert_final_newline = true
trim_trailing_whitespace = true

# Frontend (JS, TS, Vue, CSS, JSON, YAML)
[*.{js,ts,vue,jsx,tsx,css,scss,json,yaml,yml,html}]
indent_style = space
indent_size = 2

# Backend (Python)
[*.py]
indent_style = space
indent_size = 4

# Makefile (tabs obligatoires)
[Makefile]
indent_style = tab

# Markdown
[*.md]
trim_trailing_whitespace = false

# Docker
[Dockerfile*]
indent_style = space
indent_size = 4

[docker-compose*.yml]
indent_style = space
indent_size = 2
```

> **Doc** : https://editorconfig.org/

---

### 2.5. Configuration Ruff pour le backend

**Probleme** : le `pyproject.toml` du back n'a aucune config de linter/formatter. Pas de ruff, pas de black, pas d'isort. Le code Python n'a pas de standard de formatage enforce.

**Solution** : ajouter une section ruff dans `pyproject.toml`. Ruff remplace black + isort + flake8 + pyupgrade en un seul outil, ecrit en Rust (extremement rapide).

```toml
# Ajouter dans back/pyproject.toml

[tool.ruff]
target-version = "py39"
line-length = 120

[tool.ruff.lint]
select = [
    "E",    # pycodestyle errors
    "F",    # pyflakes
    "I",    # isort (import sorting)
    "UP",   # pyupgrade (modern Python syntax)
    "B",    # bugbear (common pitfalls)
    "SIM",  # simplify (code simplification)
    "ASYNC", # async best practices
]
ignore = [
    "E501",  # line too long (handled by formatter)
]

[tool.ruff.lint.isort]
known-first-party = ["app"]

[tool.ruff.format]
quote-style = "double"
indent-style = "space"
```

Ajouter `ruff` aux dev dependencies :

```toml
[tool.poetry.group.dev.dependencies]
ruff = "^0.9"
# ... existing deps
```

---

### 2.6. Doc d'onboarding — Getting Started pour les humains

**Probleme** : il existe un `CLAUDE.md` de 36 Ko (excellent pour l'IA), un `README.md` generique, mais rien pour guider un nouveau dev humain du clone au premier commit.

**Solution** : un `CONTRIBUTING.md` a la racine, concis et actionable.

```markdown
# Contributing to ChapsMind

## Prerequisites

- Docker & Docker Compose
- Node.js 20+ & pnpm
- Python 3.9+ & Poetry (for local backend dev)
- Lefthook (git hooks)

## Getting Started

1. Clone the repository:
   git clone <repo-url> && cd chapsmind

2. Setup environment:
   make init

3. Start all services:
   make up

4. Open the app:
   - Frontend: http://localhost:3000
   - Backend API: http://localhost:8000/api
   - Flower (Celery): http://localhost:5555
   - RabbitMQ: http://localhost:15672

5. Login with test credentials:
   - Admin: admin / admin123
   - Viewer: company_viewer / viewer123
     (See CLAUDE.md for full list of test users)

## Daily Workflow

- make up — start everything
- make front-dev — start frontend with HMR
- make logs — tail service logs
- make migrate — run database migrations
- make back-test — run backend tests
- make help — see all commands

## Branch Naming

- feat/TAR-xxx-description
- fix/TAR-xxx-description
- refactor/description
- chore/description

## Commit Format

<gitmoji> <type>: <description>

Examples:

- sparkles feat: add user authentication
- bug fix: resolve validation error
- recycle refactor: simplify task orchestration

## Code Standards

- Frontend: ESLint + Prettier (auto-fixed on save and pre-commit)
- Backend: Ruff (lint + format, enforced by pre-commit)
- See CLAUDE.md for detailed conventions
```

---

### 2.7. Dev Container — environnement de dev identique pour tous

#### Le probleme

Avec **9 devs sous Windows**, chaque poste doit installer et maintenir :

- Node.js 20 + pnpm
- Python 3.9 + Poetry
- Ruff, Lefthook, git (bonne version)
- Docker Desktop

Les problemes classiques sous Windows :

- **Line endings** (`CRLF` vs `LF`) qui polluent les diffs et cassent les scripts bash
- **Paths** (`C:\Users\...` vs `/home/...`) qui cassent les outils
- **Permissions** sur les fichiers montes dans Docker
- **Versions qui derivent** : un dev a Node 18, un autre Node 22, la CI a Node 20
- **Python sous Windows** : conflits de versions, PATH system vs user, problemes avec Poetry

Resultat : "ca marche sur ma machine" est un probleme recurrent, et l'onboarding d'un nouveau dev prend des heures de setup.

#### Qu'est-ce qu'un Dev Container

Un Dev Container est un **environnement de dev complet defini en code**. Au lieu d'installer les outils sur chaque machine, on definit un container Docker avec tout pre-installe. L'IDE (VS Code ou JetBrains) se connecte **a l'interieur du container** — le dev code comme d'habitude, mais dans un environnement Linux identique pour tout le monde.

```
┌─ Machine du dev (Windows / macOS / Linux) ─────────┐
│                                                     │
│  IDE (VS Code / JetBrains Gateway)                  │
│    │                                                │
│    └──→ ┌─ Dev Container (Linux) ─────────────────┐ │
│         │  Node 20 + pnpm                         │ │
│         │  Python 3.9 + Poetry + ruff             │ │
│         │  Lefthook, git                          │ │
│         │  Extensions IDE pre-installees          │ │
│         │  .env, configs...                       │ │
│         │                                         │ │
│         │  Se connecte a :                        │ │
│         │  └── docker-compose (DB, RabbitMQ...)   │ │
│         └─────────────────────────────────────────┘ │
└─────────────────────────────────────────────────────┘
```

**C'est different de docker-compose** : docker-compose fait tourner les **services** (DB, backend, frontend). Le Dev Container fait tourner l'**outil de dev** (IDE, terminal, linters). Les deux coexistent.

> **Doc** : https://containers.dev/

#### Support IDE

| IDE                                   | Support Dev Container                  | Statut                                           |
| ------------------------------------- | -------------------------------------- | ------------------------------------------------ |
| **VS Code**                           | Natif via extension "Dev Containers"   | Excellent — experience fluide                    |
| **JetBrains (PHPStorm, WebStorm...)** | Via JetBrains Gateway + Dev Containers | Fonctionnel — en amelioration active depuis 2024 |
| **Terminal pur**                      | Via `devcontainer` CLI                 | Fonctionne sans IDE                              |

> **PHPStorm et les Dev Containers** : JetBrains supporte les Dev Containers via Gateway depuis 2023. Le support s'ameliore a chaque version. Les devs PHP sous PHPStorm peuvent l'utiliser, meme si l'experience est legerement moins fluide que VS Code.
>
> **Doc JetBrains** : https://www.jetbrains.com/help/idea/connect-to-devcontainer.html

#### Configuration proposee

```
.devcontainer/
├── devcontainer.json         ← Configuration principale
├── Dockerfile                ← Image custom (optionnel, si besoin de plus que les features)
└── post-create.sh            ← Script de setup post-creation
```

**`.devcontainer/devcontainer.json`** :

```jsonc
{
  "name": "ChapsMind Dev",
  "image": "mcr.microsoft.com/devcontainers/base:ubuntu-22.04",

  // Outils installes via Dev Container Features
  // (modules pre-empaquetes, pas besoin de Dockerfile custom)
  "features": {
    "ghcr.io/devcontainers/features/node:1": {
      "version": "20",
      "installYarnUsingApt": false,
    },
    "ghcr.io/devcontainers/features/python:1": {
      "version": "3.9",
      "installTools": true,
    },
    "ghcr.io/devcontainers/features/docker-in-docker:2": {},
    "ghcr.io/devcontainers/features/git:1": {},
  },

  // Script execute apres la creation du container
  "postCreateCommand": "bash .devcontainer/post-create.sh",

  // Ports exposes automatiquement
  "forwardPorts": [3000, 8000, 5432, 5672, 15672, 5555],

  // Configuration VS Code dans le container
  "customizations": {
    "vscode": {
      "settings": {
        "editor.formatOnSave": true,
        "editor.defaultFormatter": "esbenp.prettier-vscode",
        "[python]": {
          "editor.defaultFormatter": "charliermarsh.ruff",
        },
        "[vue]": {
          "editor.defaultFormatter": "esbenp.prettier-vscode",
        },
        "files.eol": "\n",
      },
      "extensions": [
        // Frontend
        "Vue.volar",
        "dbaeumer.vscode-eslint",
        "esbenp.prettier-vscode",
        // Backend
        "charliermarsh.ruff",
        "ms-python.python",
        // General
        "EditorConfig.EditorConfig",
        "eamodio.gitlens",
        "ms-azuretools.vscode-docker",
      ],
    },
  },
}
```

**`.devcontainer/post-create.sh`** :

```bash
#!/bin/bash
set -e

echo "=== Installing pnpm ==="
npm install -g pnpm

echo "=== Installing frontend dependencies ==="
cd apps/front && pnpm install
cd ../..

echo "=== Installing Poetry ==="
pip install poetry

echo "=== Installing backend dependencies ==="
cd apps/back && poetry install
cd ../..

echo "=== Installing Lefthook ==="
curl -1sLf 'https://dl.cloudsmith.io/public/evilmartians/lefthook/setup.deb.sh' | sudo -E bash
sudo apt-get install -y lefthook
lefthook install

echo "=== Setting up environment ==="
test -f .env || cp .env.example .env

echo ""
echo "✅ Dev environment ready!"
echo "   Run 'make' to see available commands"
echo "   Run 'make up' to start all services"
```

#### Ce que ca change concretement

**Avant (sans Dev Container) — onboarding Windows** :

```
1. Installer Docker Desktop
2. Installer Node.js 20 (attention a la version)
3. Installer pnpm (npm install -g pnpm)
4. Installer Python 3.9 (attention au PATH Windows)
5. Installer Poetry (curl + PATH)
6. Installer ruff (pip install ruff)
7. Configurer git (line endings, credential manager)
8. Configurer VS Code (extensions, settings)
9. Cloner le repo
10. Copier et configurer le .env
11. pnpm install dans front/
12. poetry install dans back/
13. docker compose up
14. Debugger les problemes specifiques a Windows

→ ~2h de setup, variable selon l'experience du dev
→ "Ca marche pas" garanti au moins une fois
```

**Apres (avec Dev Container)** :

```
1. Installer Docker Desktop
2. Installer VS Code + extension Dev Containers
3. git clone <repo>
4. VS Code: "Reopen in Container"
5. Attendre le build (~5 min la premiere fois, cache ensuite)
6. make up

→ ~10 min de setup, identique pour tout le monde
→ Zero probleme de version ou de PATH
```

#### Gestion des line endings (probleme Windows critique)

Le Dev Container resout le probleme des line endings une fois pour toutes. A l'interieur du container, tout est en `LF` (Linux). Mais il faut aussi configurer git cote host pour eviter les conversions automatiques :

```
# .gitattributes (a la racine du repo)
* text=auto eol=lf
*.sh text eol=lf
*.py text eol=lf
*.ts text eol=lf
*.vue text eol=lf
*.json text eol=lf
*.yml text eol=lf
*.yaml text eol=lf
*.md text eol=lf
*.css text eol=lf
*.html text eol=lf

# Binary files
*.png binary
*.jpg binary
*.ico binary
*.woff binary
*.woff2 binary
*.ttf binary
*.eot binary
```

#### Pour les devs PHPStorm (PHP backend)

Les devs PHP qui utilisent PHPStorm ont deux options :

1. **JetBrains Gateway** : se connecter au Dev Container comme VS Code (support natif depuis PHPStorm 2023.3+)
2. **Interpreter distant** : configurer PHPStorm pour utiliser le PHP du container Docker comme interpreter, sans etre "dans" le container

L'option 1 est plus uniforme. L'option 2 est un fallback si Gateway ne convient pas.

> **Doc** : https://www.jetbrains.com/help/phpstorm/configuring-remote-interpreters.html

---

## 3. Priorites et plan d'action

| #   | Amelioration                        | Effort                          | Impact DX  | Devs impactes                 | Priorite |
| --- | ----------------------------------- | ------------------------------- | ---------- | ----------------------------- | -------- |
| 1   | **Dev Container**                   | Moyen (config + test multi-IDE) | Tres eleve | 14/14 (surtout les 9 Windows) | **P0**   |
| 2   | **`.gitattributes`** (line endings) | Trivial (1 fichier)             | Eleve      | 9 devs Windows                | **P0**   |
| 3   | **`.editorconfig`**                 | Trivial (1 fichier)             | Moyen      | 14/14                         | **P0**   |
| 4   | **`.env.example`**                  | Faible (1 fichier)              | Eleve      | 14/14                         | **P0**   |
| 5   | **Makefile racine**                 | Faible (1 fichier)              | Eleve      | 14/14                         | **P0**   |
| 6   | **Config Ruff backend**             | Faible (section pyproject.toml) | Moyen      | Devs Python                   | **P1**   |
| 7   | **Lefthook** (pre-commit)           | Moyen (install + config)        | Eleve      | 14/14                         | **P1**   |
| 8   | **`CONTRIBUTING.md`**               | Moyen (redaction)               | Eleve      | 14/14                         | **P1**   |

**Aucune de ces ameliorations ne depend de la migration monorepo.** Elles peuvent etre implementees des maintenant. Les paths dans le Makefile, Lefthook et Dev Container seront a ajuster apres migration (`front/` → `apps/front/`, etc.), mais la structure reste identique.

### Ordre d'implementation recommande

**Sprint 1 — quick wins (zero risque)** :

- `.gitattributes` — forcer `LF` partout, regle le probleme Windows immediatement
- `.editorconfig` — commit direct, respecte par tous les IDE
- `.env.example` — documenter les variables existantes
- Makefile — centraliser les commandes existantes

**Sprint 2 — environnement unifie** :

- **Dev Container** — configurer, tester sur VS Code + PHPStorm Gateway, documenter
- `CONTRIBUTING.md` — rediger l'onboarding "du clone au premier commit"

**Sprint 3 — qualite de code** :

- Config Ruff — ajouter au pyproject.toml + premier `ruff format .`
- Lefthook — installer + configurer + communiquer a l'equipe
- Pre-inclus dans le Dev Container (installe automatiquement au setup)
