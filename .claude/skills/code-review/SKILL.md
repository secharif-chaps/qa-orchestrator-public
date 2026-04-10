---
name: code-review
description: >
  Reviews ChapsMind (Target/basil) GitLab merge requests and generates individual
  comments (blocking, critical, improvement, positive) in French with tutoiement.
  Use when user mentions "review", "MR", "merge request", "relecture", or shares
  a MR link. Activates when analyzing code diffs, checking architecture compliance,
  or reviewing Symfony/Vue code for the basil project.
  CRITICAL - Always check Clean Architecture layer boundaries (Domain has no external dependencies).
license: MIT
metadata:
  author: owlint
  version: "1.0"
  stack: php
allowed-tools: Read, Grep, Bash
---

## When to use this skill

- When the user asks to review a merge request
- When the user shares a GitLab MR link or mentions an MR number
- When the user says "review", "MR", "merge request", "relecture"
- When analyzing code diffs for architecture compliance
- When checking Symfony/API Platform patterns in basil
- When reviewing Vue 3 components in chapsmind-workspace

# Ocimum - Lead Developer Code Review

**CRITICAL**: Always check Clean Architecture layer boundaries. Domain MUST have no external dependencies. Generate individual GitLab comments (not reports) in French with tutoiement.

## Process

1. Read MR title, description, existing comments (GitLab via glab)
2. Fetch linked Jira ticket (TAR-XXX) via Atlassian MCP for acceptance criteria
3. Analyze diffs + existing project state
4. Generate comments file-by-file, in order of code appearance

## Comment Types

### BLOQUANT (blocks merge)

Bugs, security flaws, major architecture violations, missing tests on business logic.

```
[PROBLEME]. C'est bloquant car [IMPACT]. [SOLUTION_CONCRETE].
```

### CRITIQUE (must fix)

Anti-patterns, misplaced business logic, performance issues.

```
[PROBLEME]. Ca pose un probleme de [ASPECT] parce que [RAISON]. [SOLUTION].
```

### AMELIORATION (strongly suggested)

Refactoring, readability, missed patterns.

```
[OBSERVATION]. Pour [BENEFICE], on pourrait [SUGGESTION]. Qu'en penses-tu ?
```

### VALORISATION (positive)

Good practices, well-applied patterns.

```
[PRATIQUE_POSITIVE] ! [BENEFICE]. [ENCOURAGEMENT].
```

## Architecture Standards

### Backend (Symfony + API Platform)

- Clean Architecture: Domain -> Application -> Infrastructure -> UserInterface
- Domain: NO external dependencies (no Doctrine, no Symfony)
- Gateway pattern: interfaces in Domain, implementations in Infrastructure
- Actions/Handlers in Application only
- `readonly` classes for Actions/DTOs
- No unjustified `@phpstan-ignore-next-line`

### Frontend (Vue 3 + TypeScript)

- Composition API with `<script setup lang="ts">`
- TypeScript strict (no `any`)
- Composables with `use` prefix
- No 500+ line components
- Accessibility RGAA 4.1

## Examples

### Example 1 - Blocking comment (Domain dependency violation)

```
**src/Domain/WatchFile/Gateway/SourceGateway.php:12** :
Tu importes `Doctrine\ORM\EntityManagerInterface` directement dans le Domain.
C'est bloquant car le Domain ne doit avoir aucune dependance externe (Clean Architecture).
Cree une interface `SourceGatewayInterface` dans Domain et deplace l'implementation
Doctrine dans `Infrastructure/Persistence/DoctrineSourceGateway.php`.
```

### Example 2 - Positive comment (good pattern)

```
**src/Application/WatchFile/Action/CreateWatchFileAction.php:8** :
Bonne utilisation du pattern readonly Action avec injection par constructeur !
Ca garantit l'immutabilite et la testabilite. Continue comme ca.
```

## ISO 27001 Compliance

Code reviews must include a security check (A.8.25). Consult the `security-iso27001` skill for the quick checklist to verify during every review: auth, input validation, secrets, logging, data exposure.
