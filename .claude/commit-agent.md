# Smart Commit Agent

## Overview

The Smart Commit Agent automatically analyzes your git changes, intelligently groups them by scope and type, creates well-structured commits following the project's guidelines, and pushes everything to the remote repository.

## Usage

Simply type in your Claude Code session:

```
/commit
```

That's it! The agent will:
1. ✅ Analyze all changes with `git status` and `git diff`
2. ✅ Group changes logically by feature area and type
3. ✅ Create multiple focused commits (not one giant commit)
4. ✅ Follow gitmoji + conventional commits format
5. ✅ Include detailed descriptions and bullet points
6. ✅ Add Claude footer to all commits
7. ✅ Push all commits to `origin/main`

## What It Does

### Intelligent Grouping

The agent groups changes by:
- **Feature area**: sidebar, components, pages, stores, etc.
- **Change type**: features, fixes, styling, refactoring
- **Related functionality**: changes that belong together

**Example**: If you modified sidebar navigation, the sidebar store, added a new Badge variant, and updated the layout - the agent will create separate commits for each logical group rather than one big "update files" commit.

### Commit Format

All commits follow the project standard:

```
<gitmoji> <type>(<scope>): <description>

- Detailed change 1
- Detailed change 2

🤖 Generated with [Claude Code](https://claude.com/claude-code)

Co-Authored-By: Claude <noreply@anthropic.com>
```

### Quality Standards

The agent ensures:
- ✅ Focused, single-purpose commits
- ✅ Descriptive messages with context
- ✅ Proper gitmoji and type selection
- ✅ Semantic scopes (sidebar, ui, components, etc.)
- ✅ Detailed bullet points for complex changes
- ✅ Files grouped logically together

## Example Output

```
## Commits Created & Pushed:

1. **✨ feat(sidebar): add horizontal scroll/swipe gesture navigation**
   - Horizontal scroll detection for trackpad & mouse
   - Navigation methods in sidebar store
   - Threshold-based switching with gesture locking

2. **💄 style(components): improve Badge and AnalysisCard UI states**
   - New 'slate' Badge variant
   - Loading/error states converted to backdrop blur overlays

3. **💄 style(layout): improve layout structure and dark mode support**
   - Gradient overlay for visual depth
   - Dark mode improvements
   - Layout formatting cleanup

All changes committed and pushed to origin/main ✅
```

## When to Use

Use the commit agent when:
- ✅ You've made multiple changes and want them properly organized
- ✅ You're not sure how to group your changes
- ✅ You want consistent commit messages
- ✅ You want to follow the project's git guidelines automatically
- ✅ You're ready to commit and push everything

## Technical Details

**Command file**: `.claude/commands/commit.md`

**Follows guidelines from**: `.claude/git-commit-guide.md`

**Permissions required**:
- `git status`, `git diff`, `git log`
- `git add`, `git commit`, `git push`

**Branch**: Currently works on `main` branch (no feature branches)

## Manual Override

If you need more control, you can still commit manually:
- The agent is a helper, not a requirement
- You can review changes before using `/commit`
- You can make manual commits alongside agent commits

## Tips

- Run `/commit` when you have a logical set of changes ready
- The agent works best with 2-10 file changes
- Very large change sets may need manual grouping
- Review the agent's proposed grouping if unsure

---

**Pro tip**: The agent learns from your project's commit history, so the more you use consistent commit messages, the better it gets at grouping!
