---
name: global-conventions
description: General development conventions for the Basil project. Use when organizing files in the standard directory structure (Domain/Application/Infrastructure), following version control best practices with feature branches, using environment variables for configuration, or maintaining documentation. Activates when setting up new features, reviewing merge requests, managing dependencies, or ensuring testing requirements are met before merging.
allowed-tools: Read, Write, Edit, Glob, Grep, Bash
metadata:
  author: chaps-e
  version: "1.0"
---

## When to use this skill

- When organizing files in the predictable directory structure (Domain, Application, Infrastructure)
- When following version control best practices (feature branches, clear commit messages)
- When using environment variables for configuration (NEVER committing secrets)
- When keeping dependencies minimal and documented
- When participating in code review (as author or reviewer)
- When ensuring testing requirements are met before merging
- When using feature flags for incomplete features instead of long-lived branches
- When maintaining README files with setup instructions
- When following the established project structure conventions
- When documenting significant changes in changelogs

# Global Conventions

## Rules

- **Consistent Project Structure**: Organize files in the DDD structure (`Domain/`, `Application/`, `Infrastructure/`, `UserInterface/`)
- **Version Control**: Use feature branches, clear commit messages, and meaningful merge requests with descriptions
- **Environment Configuration**: Use environment variables for configuration; never commit secrets or API keys
- **Dependency Management**: Keep dependencies up-to-date and minimal; document why major dependencies are used
- **Code Review**: Establish consistent review expectations for both reviewers and authors
- **Testing Requirements**: Unit and integration tests required before merging
- **Feature Flags**: Use feature flags for incomplete features rather than long-lived branches
- **Documentation**: Maintain up-to-date README files with setup instructions and architecture overview
- **Changelog**: Keep a changelog to track significant changes and improvements
