# i18n Translation Agent - Summary

## What You Asked For

An agent that:

1. Scans every Vue file in `pages/` and `components/`
2. Verifies existing i18n keys exist in both locale files
3. Detects plain text without internationalization
4. Adds missing translations to locale files
5. Updates Vue files to use i18n syntax
6. Provides statistics and commits changes

## What I Built

A Python script (`scripts/i18n-agent.py`) that does exactly that! ✅

## Key Features

### 1. Smart Text Detection

- ✅ Finds text in `<h1>`, `<p>`, `<span>`, `<button>`, `<label>`, etc.
- ✅ Finds text in attributes: `placeholder`, `title`, `label`, `alt`, `aria-label`
- ✅ Skips numbers, URLs, acronyms, Tailwind classes
- ✅ Skips already internationalized text (`{{ $t() }}`)

### 2. Key Generation

- ✅ Based on file path: `src/pages/companies/(list).vue` → `companieslist.*`
- ✅ Based on text content: "No companies yet" → `no_companies_yet`
- ✅ Ensures uniqueness with suffixes

### 3. Translation

- ✅ Uses Claude AI API for English → French translation
- ✅ Falls back to placeholders if no API key
- ✅ Preserves nested structure of locale files

### 4. Verification

- ✅ Checks all existing `$t('key')` usage
- ✅ Reports missing keys in either locale file
- ✅ Verifies both `en-US.ts` and `fr-FR.ts`

### 5. Updating

- ✅ Updates Vue templates with `{{ $t('key') }}`
- ✅ Updates attributes with `:attr="$t('key')"`
- ✅ Preserves TypeScript export format
- ✅ Maintains nested object structure

### 6. Reporting

- ✅ Shows files scanned/modified
- ✅ Shows keys added/verified
- ✅ Lists missing translations
- ✅ Shows all new translations (en + fr)

### 7. Git Integration

- ✅ Stages all changed files
- ✅ Creates gitmoji commit message
- ✅ Includes detailed stats in commit
- ✅ Prompts before committing

## How It Answers Your Questions

### Q: "If a file contains text using i18n, verify keys exist"

**A**: ✅ The `_verify_existing_keys()` method:

- Finds all `$t('key')` usage
- Checks if key exists in both `en-US.ts` and `fr-FR.ts`
- Reports missing keys with file location

### Q: "If plain text without internationalization, add to locale files"

**A**: ✅ The `_convert_untranslated_text()` method:

- Detects translatable text in templates
- Generates appropriate key
- Translates to French (or placeholder)
- Adds to both locale files
- Updates Vue file with `$t()` syntax

### Q: "Give stats about changes with list of new translations"

**A**: ✅ The `generate_report()` method shows:

```
📄 Files scanned: 45
✏️  Files modified: 12
🔑 Keys verified: 234
➕ New keys added: 28

🌍 New translations added: 28
   companieslist.empty.title:
      en: No companies yet
      fr: Aucune entreprise pour le moment
   ...
```

## Usage

### Quick Start

```bash
# Install dependency
pip install anthropic

# Set API key (optional)
export ANTHROPIC_API_KEY="your-key"

# Run agent
cd /Users/nicolasmercier/dev/mint-new/mint-front
python3 scripts/i18n-agent.py
```

### What Happens

1. Scans all `.vue` files in `src/pages/` and `src/components/`
2. Shows progress for each file
3. Generates detailed report
4. Asks if you want to commit
5. Creates gitmoji commit with stats

## Files Created

1. **`scripts/i18n-agent.py`** - Main agent script (570 lines)
2. **`scripts/README.md`** - Detailed documentation
3. **`scripts/INSTALL.md`** - Quick install guide
4. **`scripts/AGENT_SUMMARY.md`** - This file

## Example Transformations

### Before:

```vue
<template>
  <div>
    <h1>Welcome</h1>
    <p>This is some text</p>
    <input placeholder="Enter name" />
  </div>
</template>
```

### After:

```vue
<template>
  <div>
    <h1>{{ $t('welcome') }}</h1>
    <p>{{ $t('pages.component.this_is_some_text') }}</p>
    <input :placeholder="$t('pages.component.enter_name')" />
  </div>
</template>
```

### Locale Files Updated:

```typescript
// en-US.ts
export default {
  welcome: 'Welcome',
  pages: {
    component: {
      this_is_some_text: 'This is some text',
      enter_name: 'Enter name',
    },
  },
}

// fr-FR.ts
export default {
  welcome: 'Bienvenue',
  pages: {
    component: {
      this_is_some_text: 'Ceci est du texte',
      enter_name: 'Entrez le nom',
    },
  },
}
```

## Limitations

1. **Script sections**: Doesn't translate computed properties or JavaScript strings
2. **Complex Vue syntax**: May miss `v-html` or complex directives
3. **Language pair**: Only supports English ↔ French
4. **Key naming**: Generated keys may not match your preferred convention
5. **Manual review**: Always review auto-translations for accuracy

## Customization

You can modify the script to:

- Change key generation strategy
- Add support for more languages
- Customize text detection patterns
- Adjust what gets skipped
- Change commit message format

## Next Steps

1. **Test the agent**:

   ```bash
   python3 scripts/i18n-agent.py
   ```

2. **Review the report** before committing

3. **Check git diff** to see changes

4. **Run your dev server** to verify everything works

5. **Adjust the script** if needed for your use case

## Support

The script is well-documented with:

- Inline comments explaining logic
- Type hints for all functions
- Clear class and method structure
- Comprehensive README

You can:

- Read the code to understand how it works
- Modify patterns in the script
- Adjust key generation logic
- Add new features as needed

---

**Ready to use!** Just run `python3 scripts/i18n-agent.py` 🚀
