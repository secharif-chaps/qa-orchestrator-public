# MINT

A modern and fast Vue 3 v4 webapp with TypeScript, data fetching, state management, and comprehensive tooling.

## User Application Workflow

🗂️ Workspaces

- Users create companies cards to monitor companies information online
- A User always belongs to a workspace
- Company cards are shared in a workspace

🔄 Company Lifecycle & Tasks workflow

By creating a company card the system create tasks which are responsible of finding specific informations online by calling different n8n workflows

The frontend monitor the tasks status and call the backend to start tasks.

Key Components:

- User:
- Company:
- Workspace:
- Tasks:

👤 User Experience

1. Dashboard: See stats and recent companies
2. Companies List View: List companies
3. Company view: see information about a company

## Standards

- Stack: Vue.js, TypeScript, TailwindCSS v4, Reka UI, Pinia, Pinia Colada
- Patterns: ALWAYS use Composition API + `<script setup lang="ts">`, NEVER use Options API
- ALWAYS Keep types alongside your code, use TypeScript for type safety, prefer `interface` over `type` for defining types
- Keep unit and integration tests alongside the file they test: `src/ui/Button.vue` + `src/ui/Button.spec.ts`
- ALWAYS use TailwindCSS classes rather than manual CSS
- DO NOT hard code colors, use design system semantic colors from main.css and Tailwind's color system
- ONLY add meaningful comments that explain why something is done, not what it does
- Dev server is already running on `http://localhost:3000` with HMR enabled. NEVER launch it yourself
- ALWAYS use named functions when declaring methods, use arrow functions only for callbacks
- ALWAYS prefer named exports over default exports

## Project Structure

Keep this section up to date with the project structure. Use it as a reference to find files and directories.

EXAMPLES are there to illustrate the structure, not to be implemented as-is.

```
public/ # Public static files (favicon, robots.txt, static images, etc.)
src/
├── api/ # MUST export individual functions that fetch data
│   ├── client.ts # file for fetch wrapper for auth and headers
├── components/ # Reusable Vue components
│   ├── ui/ # Base UI components (buttons, inputs, etc.) if any
│   ├── layout/ # Layout components (header, footer, sidebar) if any
│   └── features/ # Feature-specific components
│       └── home/ # EXAMPLE of components specific to the homepage
├── composables/ # Composition functions
├── stores/ # Pinia stores for global state (NOT data fetching)
├── queries/ # Pinia Colada queries for data fetching
│   ├── companies.ts # EXAMPLE file for companies-related queries
│   ├── tasks.ts # EXAMPLE file for tasks-related queries
│   └── workspaces.ts # EXAMPLE file for workspaces-related queries
├── pages/ # Page components (Vue Router + Unplugin Vue Router)
│   ├── (home).vue # EXAMPLE index page using a group for a better name renders at /
│   ├── companies.vue # EXAMPLE that renders at /users
│   └── companies.[companyId].vue # EXAMPLE that renders at /users/:companyId
├── plugins/ # Vue plugins
├── utils/ # Global utility pure functions
├── assets/ # Static assets that are processed by Vite (e.g CSS)
├── main.ts # Entry point for the application, add and configure plugins, and mount the app
├── App.vue # Root Vue component
└── router/ # Vue Router configuration
    └── index.ts # Router setup
```

## Project Commands

Frequently used commands:

- `pnpm run build`: bundles the project for production
- `pnpm run test`: runs all tests
- `pnpm exec vitest run <test-files>`: runs one or multiple specific test files
  - add `--coverage` to check missing test coverage

## Development Workflow

ALWAYS follow the workflow when implementing a new feature or fixing a bug. This ensures consistency, quality, and maintainability of the codebase.

1. Plan your tasks, review them with user. Include tests when possible
2. Write code, following the [project structure](#project-structure) and [conventions](#standards)
3. **ALWAYS test implementations work**:
   - Write [tests](#using-playwright-mcp-server) for logic and components
   - Use the Playwright MCP server to test like a real user
4. Stage your changes with `git add` once a feature works
5. Review changes and analyze the need of refactoring

## Testing Workflow

## Unit and Integration Tests

- Test critical logic first
- Split the code if needed to make it testable

### Using Playwright MCP Server

1. Navigate to the relevant page
2. Wait for content to load completely
3. Test primary user interactions
4. Test secondary functionality (error states, edge cases)
5. Check the JS console for errors or warnings
   - If you see errors, investigate and fix them immediately
   - If you see warnings, document them and consider fixing if they affect user experience
6. Document any bugs found and fix them immediately

## Research & Documentation

- **NEVER hallucinate or guess URLs**
- ALWAYS try accessing the `llms.txt` file first to find relevant documentation. EXAMPLE: `https://pinia-colada.esm.dev/llms.txt`
  - If it exists, it will contain other links to the documentation for the LLMs used in this project
- ALWAYS follow existing links in table of contents or documentation indices
- Verify examples and patterns from documentation before using

## MCP Servers

You have these MCP servers configured globally:

- **Playwright**: Browser automation for visual testing and UI interactions. Use this server when testing UI changes (Playwright can navigate, screenshot, and interact)

Note: These are user-level servers available in all your projects.
