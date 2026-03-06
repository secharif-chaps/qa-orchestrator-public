---
name: global-commenting
description: Code commenting best practices for self-documenting code. Use when writing code that explains itself through clear structure and naming, adding minimal comments only to explain complex logic sections, or avoiding temporal comments about recent changes/fixes. Activates when reviewing whether code needs comments, preferring self-explanatory variable/function names over comments, or ensuring comments remain evergreen and relevant long-term.
allowed-tools: Read, Write, Edit, Glob, Grep, Bash
metadata:
  author: chaps-e
  version: "1.0"
---

## When to use this skill

- When writing self-documenting code through clear structure and naming
- When deciding whether a comment is necessary (prefer code clarity first)
- When adding minimal, concise comments only for complex logic sections
- When AVOIDING comments that speak to recent or temporary changes/fixes
- When ensuring comments are evergreen (relevant far into the future)
- When preferring well-named variables over inline explanatory comments
- When using PHPDoc/JSDoc only for public API documentation
- When removing outdated comments that no longer match the code
- When explaining "why" not "what" (the code shows what, comments explain why)

# Global Commenting

## Rules

- **Self-Documenting Code**: Write code that explains itself through clear structure and naming
- **Minimal, Helpful Comments**: Add concise comments only to explain large or complex sections of logic
- **No Temporal Comments**: Never leave comments about recent changes or fixes. Comments must be evergreen and relevant long-term
- **Explain "Why" Not "What"**: The code already shows what it does; comments should explain why a decision was made
- **Remove Stale Comments**: Delete comments that no longer match the code they describe
