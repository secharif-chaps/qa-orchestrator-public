---
name: global-validation
description: Input validation best practices for PHP/Symfony and Vue/Nuxt. Use when always validating on server-side (never trusting client-side alone), using client-side validation for UX feedback, failing early with specific field-level error messages, or preferring allowlists over blocklists. Activates when implementing Symfony Validator constraints, Zod schemas in frontend, sanitizing input to prevent injection attacks (SQL, XSS), or validating business rules at the appropriate application layer.
allowed-tools: Read, Write, Edit, Glob, Grep, Bash
metadata:
  author: chaps-e
  version: "1.0"
---

## When to use this skill

- When ALWAYS validating on server-side (never trusting client-side alone)
- When adding client-side validation for immediate UX feedback
- When validating input as early as possible (fail fast)
- When providing clear, field-specific error messages
- When preferring allowlists over blocklists for validation rules
- When checking data types, formats, ranges, and required fields
- When sanitizing input to prevent injection attacks (SQL, XSS, command injection)
- When using Symfony Validator constraints (`#[Assert\NotBlank]`, `#[Assert\Length]`)
- When using `#[EnumConstraint]` for enum validation in DTOs
- When validating business rules at the Application layer
- When applying consistent validation across all entry points (API, CLI, background jobs)

# Global Validation

## Rules

- **Validate on Server Side**: Always validate on the server; never trust client-side validation alone
- **Client-Side for UX**: Use client-side validation for immediate user feedback, but duplicate checks server-side
- **Fail Early**: Validate input as early as possible and reject invalid data before processing
- **Specific Error Messages**: Provide clear, field-specific error messages that help users correct their input
- **Allowlists Over Blocklists**: Define what is allowed rather than trying to block everything that's not
- **Type and Format Validation**: Check data types, formats, ranges, and required fields systematically
- **Sanitize Input**: Sanitize user input to prevent injection attacks (SQL, XSS, command injection)
- **Business Rule Validation**: Validate business rules at the Application layer
- **Consistent Validation**: Apply validation consistently across all entry points (API, CLI, background jobs)

## ISO 27001 Compliance

This skill touches security-sensitive areas (A.8.26). Consult the `security-iso27001` skill for applicable controls on input sanitization and injection prevention.
