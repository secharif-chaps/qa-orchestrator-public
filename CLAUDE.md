# Claude AI Assistant Instructions

## Important: Project Documentation

This project maintains detailed documentation in the `.claude/` folder that MUST be followed:

- **Project Overview**: See `.claude/index.md` for setup and architecture
- **Git Commits**: Always follow `.claude/git-commit-guide.md` for ALL commits
- **Theme Guidelines**: Follow `.claude/theme.md` for styling and color system
- **API Patterns**: Use `.claude/api-patterns.md` for API integration patterns
- **Permission System**: Follow `.claude/permissions.md` for implementing access control
- **Routing**: See `.claude/routing.md` for Vue Router patterns and file-based routing
- **Test Users**: Use `.claude/test-users.md` for testing different permission levels

## Key Requirements

1. **ALWAYS** check and follow the guidelines in `.claude/` folder before performing any task
2. **NEVER** commit without following the git commit format with gitmojis
3. When in doubt, read the relevant `.claude/` documentation first
4. Run tests before committing when available (yarn test:all)
5. **ALWAYS** implement proper permission checks using resource-based composables
6. Use test users from `.claude/test-users.md` to verify permission functionality

## Git Commit Format (Quick Reference)

Format: `<gitmoji> <type>(<scope>): <description>`

Examples:

- `✨ feat(companies): add new company listing feature`
- `🐛 fix(auth): resolve login redirect issue`
- `💄 style(ui): improve button hover states`
- `♻️ refactor(api): restructure API client`
- `🔧 chore(deps): update dependencies`

## Project Stack

- Vue 3 + TypeScript
- Pinia for state management
- Vue Router for navigation
- Tailwind CSS for styling
- Workspace-based multi-tenancy
- Keycloak for authentication & authorization
- Resource-based permission system

## Permission System

This project implements a granular permission system:

- **Resource-based composables**: Use `useCompanyPermissions()` for company-related features
- **Route guards**: All pages have permission requirements defined in `<route>` blocks
- **UI conditional rendering**: Hide/show elements based on user permissions
- **Backend integration**: Permissions are synced between Keycloak and database

### Available Permissions

- `company.create`, `company.delete`, `company.view`
- `workspace.read`, `workspace.write`
- `admin.workspaces`

### Testing Permissions

Use test users defined in `.claude/test-users.md` to test different permission scenarios.

## Design System

### Typography

- **Font Family**: Hanken Grotesk
- **Font Weights**: Regular (400), Semibold (600), Bold (700)
- **Optical Sizing**: Auto

#### Type Scale

**Headlines:**
- `headline.3xl`: 24px / Bold / Line-height: 31px
- `headline.2xl`: 20px / Regular / Line-height: 26px
- `headline.lg`: 16px / Bold, Semibold, Regular / Line-height: 22px

**Body Text:**
- `text.base`: 14px / Bold, Semibold, Regular / Line-height: 18px
- `text.sm`: 12px / Bold, Semibold, Regular / Line-height: 16px
- `text.xs`: 11px / Bold, Semibold, Regular / Line-height: 14px

#### Writing Tone

- **Précis, sans rigidité**: Direct and clear messages without complexity
- **Engageant**: Positive vocabulary, avoiding technical jargon
- **Assertif mais accessible**: Active voice with clear definitions
- **Clair**: Important information highlighted with subtle conviviality

### Color Palette

The design system is built around a harmonious and modern palette with five main color families, each with 10 intensity levels (50-950).

#### Primary Colors

- **Sage** (Primary - #5D7374)
  - Usage: Primary CTAs, navigation elements, selections, tags, and illustrations
  - Conveys calm and reliability

- **Almond** (Secondary - #DCEFE3)
  - Usage: Background accents, secondary CTAs, subtle highlights
  - Adds freshness and lightness

- **Rose** (Tertiary - #EFC9F3)
  - Usage: Emotional touches, separators, secondary tags
  - Brings warmth and engagement

#### Semantic Colors

- **Success (Green - #29AD72)**: Successful actions, validation, confirmation
- **Warning (Orange - #5FA884)**: Warnings, calls to attention
- **Error (Red - #DB1C50)**: Errors, blockages, critical actions
- **Information (Blue - #3CB6DA)**: Neutral information, guidance

#### Neutral Colors

- **Black (#1B211E)**: Text, titles, tertiary CTAs
- **White (#F2F2F3)**: Primary backgrounds
- **Gray Scale**: Secondary CTAs, avatars, discrete elements

#### Color Distribution Guidelines

To maintain visual hierarchy and balance:
- **40% White**: Dominant surface color for clarity and breathing space
- **20% Sage**: Primary identity color for main CTAs and navigation
- **15% Black**: Text readability and visual contrast
- **5% Gray**: Discrete structural elements
- **5% Almond**: Background accents and complementary touches
- **5% Rose**: Emotional accents used sparingly

### Gradients

- **Brand Gradient**: Almond 200 → Sage 600 (for brand elements)
- **AI Gradient**: Almond 400 → Rose 500 (temporary, will be updated)

### Accessibility

All color combinations must meet WCAG AAA standards:
- Minimum contrast ratio of 7:1 for normal text
- Minimum contrast ratio of 4.5:1 for large text
- Test all combinations before implementation

#### Approved Color Combinations (WCAG AAA Compliant)

**Text on Backgrounds:**
- ✅ Black text on White background (16.47:1)
- ✅ Black text on Almond-100 background (13.72:1)
- ✅ Black text on Rose-200 background (11.23:1)
- ✅ White text on Sage-600 background (5.04:1)
- ✅ Sage-700 text on Almond-100 background (5.43:1)
- ✅ Sage-700 text on White background (16.47:1)
- ✅ Sage-800 text on Sage-200 background (5.79:1)
- ✅ Rose-800 text on Rose-100 background (4.94:1)
- ✅ Gray-500 text on White background (4.88:1)

**Semantic Colors:**
- ✅ Green-500 text on White background (5.74:1)
- ✅ Orange-400 text on Black background (7.98:1)
- ✅ Red-600 text on White background (7.98:1)
- ✅ Blue-400 text on Black background (6.98:1)

**Dark Mode Combinations:**
- ✅ White text on Sage-950 background (8.15:1)
- ✅ Sage-300 text on Sage-950 background (6.26:1)
- ✅ Rose-200 text on Sage-950 background (8.15:1)
- ✅ Almond-400 text on Black background (8.59:1)

#### Forbidden Color Combinations (Insufficient Contrast)

**Never use these combinations for text:**
- ❌ Rose-200 on White background (1.47:1)
- ❌ Rose-200 on Rose-100 background (1.29:1)
- ❌ Almond-100 on White background (1.20:1)
- ❌ Gray-300 on White background (2.10:1)
- ❌ Gray-400 on Gray-100 background (2.74:1)
- ❌ Sage-400 on Sage-100 background (2.61:1)
- ❌ Gray-500 on Sage-950 background (2.45:1)
- ❌ Sage-600 on Sage-950 background (1.20:1)

#### Best Practices for Color Usage

1. **Primary Text**: Always use Black on light backgrounds or White on dark backgrounds
2. **Interactive Elements**: Ensure CTAs and buttons have sufficient contrast in all states (default, hover, active, disabled)
3. **Status Colors**: When using semantic colors, always pair with appropriate backgrounds:
   - Success (Green): Use on white or very dark backgrounds
   - Error (Red): Use on white or light backgrounds
   - Warning (Orange): Use on dark backgrounds
   - Information (Blue): Use on dark backgrounds
4. **Testing**: Always verify contrast ratios using tools before implementation
5. **Fallbacks**: Provide additional visual indicators beyond color alone (icons, borders, patterns)

### Spacing System

The design follows a **4px grid system** for consistent spacing:

#### Spacing Scale
- `none`: 0px
- `3xs`: 4px (0.25rem)
- `2xs`: 8px (0.5rem)
- `xs`: 12px (0.75rem)
- `md`: 16px (1rem)
- `lg`: 20px (1.25rem)
- `xl`: 24px (1.5rem)
- `2xl`: 32px (2rem)
- `3xl`: 36px (2.25rem)
- `4xl`: 40px (2.5rem)

#### Best Practices
- Use 4px or 8px as base for all spacing
- Related elements: smaller spacing (4px)
- Separate sections: larger spacing (16px, 24px, 32px)
- Combine with modular grid for structure

### Shadow System

#### Standard Shadows
- `shadow-1`: Light elevation - subtle cards
- `shadow-2`: Medium elevation - hover states
- `shadow-3`: High elevation - modals
- `shadow-4`: Maximum elevation - floating elements
- `shadow-inner`: Inset shadow for inputs
- `shadow-volume`: Complex multi-layer shadow

#### Colored Shadows (Semantic)
- Pink shadow for Rose elements
- Green shadow for Success states
- Blue shadow for Information
- Orange shadow for Warnings
- Red shadow for Errors

### Border Radius

- `rounded-full` (2000px): CTAs, icon buttons, avatars
- `rounded-2xl` (16px): Cards, triggers, moderate rounding
- `rounded-3xl` (24px): Important blocks, containers

### Blur Effects

- **Frosted Cloud**: Light, airy interfaces (30% opacity)
- **Frosted Glass**: Cold, minimal effect (25% opacity)
- **Midnight Glass**: Dark mode vibrant (30% opacity)
- **Default blur**: Simple implementation (30% opacity)

### Grid System

#### Breakpoints
- `xs`: 480px (Mobile) - 2 columns
- `sm`: 744px (Large mobile/tablet) - 4 columns
- `md`: 1024px (Laptop) - 6 columns
- `lg`: 1440px (Desktop) - 8 columns
- `xl`: 1920px (Large desktop) - 12 columns

#### Grid Configuration
- **Margins**: 16px (mobile), 24-32px (desktop)
- **Gutters**: 16px (mobile), 24-32px (desktop)
- **Default design target**: Desktop (1440px)

### Implementation Guidelines

1. **Use CSS Variables**: All design tokens should be CSS custom properties
2. **Semantic Naming**: Use semantic names for colors, spacing, and shadows
3. **Dark Mode**: Ensure all elements have appropriate dark mode variants
4. **Consistency**: Always use tokens from the design system
5. **4px Grid**: All spacing must be multiples of 4px
6. **Accessibility First**: Test all combinations for WCAG compliance
7. **Responsive Design**: Mobile-first approach with defined breakpoints

## UI Components

- **ALWAYS use custom UI components** from `@/components/ui/` instead of third-party libraries when available:
  - **Alert**: Use `Alert` component instead of `OAlert` (Feathers) or `RAlert` (Reka)
    - For warnings, errors, info messages, and important notifications
  - **Input**: Use `Input` component instead of `OInput` (Feathers)
    - For all form inputs, search fields, and text entry
  - **Badge**: Use `Badge` component instead of any third-party badge/chip/tag components
    - For status indicators, counts, labels, tags, and small metadata
- These custom components provide:
  - Theme-aware styling that works in both light and dark modes
  - Consistent design language across the application
  - Subtle gradients and modern aesthetics
  - Better TypeScript support
- See `src/components/CLAUDE.md` for detailed component usage and examples

## Important Reminders

- Only commit when explicitly asked by the user
- Include Claude footer in commit messages
- Follow existing code patterns and conventions
