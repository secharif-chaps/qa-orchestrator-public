---
name: global-coding-style
description: Coding style best practices for PHP and TypeScript/Vue. Use when following consistent naming conventions (camelCase, PascalCase, snake_case), keeping functions small and focused on single tasks, removing dead code and unused imports, or applying the DRY principle by extracting common logic. Activates when reviewing code style, running linters (task api:cs:fix, task pwa:eslint:fix), ensuring meaningful variable/function names, or maintaining consistent indentation across the codebase.
allowed-tools: Read, Write, Edit, Glob, Grep, Bash
metadata:
  author: chaps-e
  version: "1.0"
---

## When to use this skill

- When establishing and following naming conventions (camelCase for variables, PascalCase for classes)
- When keeping functions small and focused on a single task
- When choosing descriptive names that reveal intent
- When removing dead code, commented-out blocks, and unused imports
- When applying the DRY principle (extracting common logic into reusable functions)
- When maintaining consistent indentation (spaces or tabs as configured)
- When running `task api:cs:fix` or `task pwa:eslint:fix` for automated formatting
- When avoiding abbreviations and single-letter variables (except in narrow contexts like loop indices)
- When NOT writing backward-compatibility code unless specifically instructed
- When refactoring duplicated code into shared utilities

# Global Coding Style

## Rules

- **Consistent Naming Conventions**: `camelCase` for variables/methods, `PascalCase` for classes, `snake_case` for database columns
- **Automated Formatting**: Run `task api:cs:fix` and `task pwa:eslint:fix` to maintain consistent style
- **Meaningful Names**: Choose descriptive names that reveal intent; avoid abbreviations and single-letter variables except in narrow contexts
- **Small, Focused Functions**: Keep functions focused on a single task for better readability and testability
- **Consistent Indentation**: Use consistent indentation as configured by the project linters
- **Remove Dead Code**: Delete unused code, commented-out blocks, and imports rather than leaving them as clutter
- **No Backward Compatibility**: Unless specifically instructed, do not write additional logic to handle backward compatibility
- **DRY Principle**: Avoid duplication by extracting common logic into reusable functions or modules
