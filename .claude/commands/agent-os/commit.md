# Smart Commit Command

Analyze all changes, intelligently group them by scope and type, then create focused commits following gitmoji + conventional commit format on a feature branch.

## What This Command Does

1. **Checks Current Branch**
   - Verifies if already on a feature branch or on main
   - If on main, creates appropriate feature branch based on changes

2. **Analyzes Changes**
   - Runs `git status` to see all modifications
   - Runs `git diff` to understand the nature of changes
   - Reviews recent commits (`git log -5 --oneline`) for style context

3. **Groups Changes Logically**
   - By **scope**: sidebar, auth, components, api, db, etc.
   - By **type**: feat, fix, style, refactor, chore, etc.
   - By **functionality**: changes that belong together

4. **Creates Focused Commits**
   - Stages relevant files for each logical group
   - Writes descriptive commit messages with gitmoji
   - Includes Jira ticket (TAR-xxx) in the description
   - Includes detailed bullet points for complex changes

5. **Pushes to Remote**
   - Pushes feature branch to origin
   - Confirms successful push
   - Reminds user to create merge/pull request

## Commit Format

Each commit follows this structure:

```
<gitmoji> <type>(<scope>): TAR-xxx <description>

- Detailed change 1
- Detailed change 2
- Additional context if needed

```

**Note**: `chore` and `docs` commits may omit the Jira ticket number (TAR-xxx).

## Common Gitmojis

- ✨ `:sparkles:` - New features
- 🐛 `:bug:` - Bug fixes
- 💄 `:lipstick:` - UI/styling
- ♻️ `:recycle:` - Refactoring
- 🔧 `:wrench:` - Configuration
- 🔥 `:fire:` - Removing code
- ⚡ `:zap:` - Performance
- 🗃️ `:card_file_box:` - Database
- 📝 `:memo:` - Documentation
- 🧪 `:test_tube:` - Tests
- 🔒 `:lock:` - Security
- 📦 `:package:` - Dependencies

## Grouping Examples

### Good Grouping ✅

- **Commit 1**: All sidebar-related changes (sidebar.vue + sidebar store + routes)
- **Commit 2**: UI component updates (Badge.vue + Alert.vue + shared styles)
- **Commit 3**: API endpoint additions (routes + schemas + services)
- **Commit 4**: Database migrations and model updates
- **Commit 5**: Test additions for new features

### Bad Grouping ❌

- All changes in one giant commit
- Random unrelated files grouped together
- Mixing feat + fix + style in one commit
- Too many tiny commits for trivial changes

## Special Cases

- **New files**: Include with the feature commit they belong to
- **Deletions**: Group with refactoring/restructuring commit
- **Type definitions**: Include with feature that generates them (e.g., `typed-router.d.ts` with routing)
- **Formatting only**: Use `style` type
- **Breaking changes**: Add "BREAKING CHANGE: ..." in commit body

## Feature Branch Workflow

**CRITICAL**: Never commit directly to main branch!

### Branch Naming Conventions

- `feat/TAR-xxx-feature-name` - New features
- `fix/TAR-xxx-bug-name` - Bug fixes
- `refactor/TAR-xxx-refactor-name` - Code refactoring
- `docs/doc-name` - Documentation updates (ticket optional)
- `chore/task-name` - Maintenance tasks (ticket optional)

### Workflow Steps

1. Check if on main branch
2. If on main, create feature branch with appropriate prefix (include Jira ticket)
3. Make commits on feature branch
4. Push feature branch to origin
5. Remind user to create merge/pull request

## Usage

Simply run:

```
/commit
```

The agent will:

1. Check current branch and create feature branch if needed
2. Analyze all your changes
3. Group them intelligently
4. Create multiple focused commits (with TAR-xxx ticket reference)
5. Push feature branch to origin
6. Prompt you to create merge/pull request

## Output

After completion, you'll see:

```
## Feature Branch: feat/TAR-123-collapsible-navigation

## Commits Created & Pushed:

1. **✨ feat(sidebar): TAR-123 add collapsible navigation menu**
   - Implemented toggle functionality
   - Added animation transitions
   - Updated mobile responsiveness

2. **💄 style(components): TAR-123 update Badge component theming**
   - Added dark mode support
   - Improved color contrast for accessibility

3. **🧪 test(sidebar): TAR-123 add navigation tests**
   - Unit tests for toggle behavior
   - Accessibility tests

All changes committed and pushed to origin/feat/TAR-123-collapsible-navigation

Next step: Create a merge/pull request to merge into main
```

## Important Notes

- **NEVER commits directly to main** - always creates/uses feature branch
- Automatically creates feature branch if on main
- Quality over quantity - creates fewer, well-organized commits
- Always includes Jira ticket (TAR-xxx) except for chore/docs
- Pushes feature branch after all commits are created
- Reminds user to create merge/pull request
- Use for any number of changes - from few files to large refactors
