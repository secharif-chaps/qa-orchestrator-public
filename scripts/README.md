# i18n Translation Agent

An intelligent agent that automatically detects missing translations in your Vue 3 application and migrates untranslated text to the i18n system.

## Features

- ✅ **Scans all Vue files** in `src/pages/` and `src/components/`
- ✅ **Verifies existing i18n keys** exist in both `en-US.ts` and `fr-FR.ts`
- ✅ **Detects untranslated text** in templates (headings, paragraphs, buttons, labels, etc.)
- ✅ **Auto-generates translation keys** based on file context and text content
- ✅ **Translates to French** using Claude AI API (or placeholder if no API key)
- ✅ **Updates Vue files** to use `$t()` syntax
- ✅ **Commits changes** with proper gitmoji format
- ✅ **Provides detailed stats** about all changes made

## What Gets Translated

The agent intelligently detects translatable text in:

### Template Elements

- Headings: `<h1>`, `<h2>`, `<h3>`, etc.
- Text containers: `<p>`, `<span>`, `<div>`, `<label>`
- Interactive elements: `<button>`, `<a>`, `<li>`
- Table elements: `<th>`, `<td>`

### Attributes

- `placeholder="Enter name"`
- `title="Tooltip text"`
- `label="Field label"`
- `alt="Image description"`
- `aria-label="Screen reader text"`

## What Gets Skipped

The agent is smart enough to skip:

- ❌ Numbers and dates
- ❌ Already translated text (`{{ $t('key') }}`)
- ❌ Variable interpolations (`{{ someVar }}`)
- ❌ Tailwind CSS classes
- ❌ URLs and links
- ❌ Acronyms (ID, URL, API, etc.)
- ❌ Very short text (< 2 characters)
- ❌ Code and technical identifiers

## Installation

### Prerequisites

```bash
# Install Python dependencies
pip install anthropic

# Set your Anthropic API key (optional - for auto-translation)
export ANTHROPIC_API_KEY="your-api-key"
```

### Without API Key

The agent will still work without an API key, but French translations will be placeholders like `[FR] Original Text` that you'll need to translate manually.

## Usage

### Basic Usage

```bash
# From mint-front directory
cd /Users/nicolasmercier/dev/mint-new/mint-front

# Run the agent
python3 scripts/i18n-agent.py
```

### With API Key

```bash
# Option 1: Environment variable
export ANTHROPIC_API_KEY="your-key"
python3 scripts/i18n-agent.py

# Option 2: Pass as argument
python3 scripts/i18n-agent.py your-api-key
```

## What Happens

1. **Scanning Phase**
   - Scans all `.vue` files in `src/pages/` and `src/components/`
   - Shows progress for each file

2. **Verification Phase**
   - Checks all existing `$t('key')` usage
   - Reports missing keys in locale files

3. **Detection Phase**
   - Finds untranslated text in templates
   - Generates appropriate i18n keys

4. **Translation Phase**
   - Translates English text to French using Claude AI
   - Falls back to placeholder if no API key

5. **Update Phase**
   - Updates Vue files with `$t('key')` syntax
   - Updates locale files with new translations

6. **Report Phase**
   - Shows detailed statistics
   - Lists all new translations added

7. **Commit Phase**
   - Prompts to commit changes
   - Creates proper gitmoji commit message

## Example Output

```
🚀 Starting i18n Translation Agent
📂 Root directory: /Users/nicolasmercier/dev/mint-new/mint-front
🌍 Locales: en-US.ts, fr-FR.ts

Scanning pages directory...
📄 Scanning: src/pages/(home).vue
📄 Scanning: src/pages/companies/(list).vue
📄 Scanning: src/pages/settings/profile.vue
...

Scanning components directory...
📄 Scanning: src/components/ui/Button.vue
📄 Scanning: src/components/layout/Header.vue
...

💾 Saving translations to locale files...

============================================================
📊 i18n Translation Agent Report
============================================================

📄 Files scanned: 45
✏️  Files modified: 12
🔑 Keys verified: 234
➕ New keys added: 28

⚠️  Missing translations found: 3
   - src/pages/admin/costs.vue: Key 'admin.costs.title' missing in: fr-FR
   - src/components/Header.vue: Key 'nav.logout' missing in: en-US

🌍 New translations added: 28
   companieslistemptytitle:
      en: No companies yet
      fr: Aucune entreprise pour le moment
   companieslistemptydescription:
      en: Start by adding your first company
      fr: Commencez par ajouter votre première entreprise
   ...

============================================================

Do you want to commit these changes? (y/n): y
✅ Changes committed successfully
```

## Key Generation Strategy

The agent generates keys based on:

1. **File Context**: Uses the folder structure
   - `src/pages/companies/(list).vue` → `companieslist.*`
   - `src/components/settings/Header.vue` → `settingsheader.*`

2. **Text Content**: Creates readable key from text
   - "No companies yet" → `no_companies_yet`
   - "Start Search" → `start_search`

3. **Uniqueness**: Adds suffix if key exists
   - `companieslist_title_1`
   - `companieslist_title_2`

## Locale File Structure

The agent preserves the nested structure of your locale files:

**Before:**

```typescript
export default {
  company: {
    list: {
      title: 'Companies',
    },
  },
}
```

**After:**

```typescript
export default {
  company: {
    list: {
      title: 'Companies',
      empty: {
        title: 'No companies yet',
        description: 'Start by adding your first company',
      },
    },
  },
}
```

## Troubleshooting

### Translation API Errors

If you see `⚠️ Translation API error`, the agent will fall back to placeholder translations. You can:

1. Check your API key is valid
2. Check your internet connection
3. Manually translate `[FR]` placeholders later

### Git Conflicts

If you get git conflicts during commit:

1. The agent stages files in: `src/i18n/locales/`, `src/pages/`, `src/components/`
2. Resolve conflicts manually
3. Run `git commit` to complete

### Incorrect Translations

If text is incorrectly identified as translatable:

1. Add pattern to `skip_patterns` in `_is_translatable()` method
2. Or manually revert specific changes

## Advanced Configuration

You can customize the agent by modifying these constants in the script:

```python
# Directories to scan
PAGES_DIR = ROOT_DIR / "src" / "pages"
COMPONENTS_DIR = ROOT_DIR / "src" / "components"

# Locale files
EN_LOCALE_FILE = LOCALES_DIR / "en-US.ts"
FR_LOCALE_FILE = LOCALES_DIR / "fr-FR.ts"

# Text detection patterns
TEMPLATE_TEXT_PATTERN = re.compile(...)
ATTRIBUTE_TEXT_PATTERN = re.compile(...)
```

## Best Practices

1. **Run on clean git state**: Commit existing changes first
2. **Review changes**: Check the report before committing
3. **Test after running**: Run your dev server and check for errors
4. **Run iteratively**: Run multiple times as your codebase evolves
5. **Manual review**: Always review auto-generated translations for accuracy

## Limitations

- Only supports English → French translation
- Requires Claude AI API for real translations
- May not detect complex Vue syntax (e.g., `v-html`)
- Cannot translate computed properties in `<script>` sections
- Key generation may not always match your naming convention

## Future Improvements

- [ ] Support for more languages
- [ ] Better key naming strategies (configurable)
- [ ] Support for pluralization rules
- [ ] Detect translations needed in `<script>` sections
- [ ] Integration with translation services (DeepL, Google Translate)
- [ ] Interactive mode to review each change
- [ ] Support for Vue i18n pluralization

## Support

For issues or questions:

1. Check the report output for specific errors
2. Review the changes in git diff
3. Modify the script for your specific needs

---

**Author**: Claude AI
**License**: MIT
