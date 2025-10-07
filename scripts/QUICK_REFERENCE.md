# i18n Agent - Quick Reference Card

## 🚀 Run the Agent

```bash
cd /Users/nicolasmercier/dev/mint-new/mint-front
python3 scripts/i18n-agent.py
```

## 📦 Install (One-time)

```bash
pip install anthropic
export ANTHROPIC_API_KEY="your-key"  # Optional
```

## 🎯 What It Does

| Action | Description |
|--------|-------------|
| **Scans** | All `.vue` files in `src/pages/` and `src/components/` |
| **Verifies** | All existing `$t('key')` have translations in both locales |
| **Detects** | Plain text in templates not using i18n |
| **Translates** | English → French using Claude AI |
| **Updates** | Vue files with `{{ $t('key') }}` syntax |
| **Commits** | Changes with gitmoji format |

## ✅ Translates

- `<h1>`, `<h2>`, `<h3>`, `<h4>`, `<h5>`, `<h6>`
- `<p>`, `<span>`, `<div>`, `<label>`
- `<button>`, `<a>`, `<li>`
- `<th>`, `<td>`
- `placeholder=""`, `title=""`, `label=""`, `alt=""`, `aria-label=""`

## ❌ Skips

- Numbers and dates
- Already translated (`{{ $t() }}`)
- Variable interpolations (`{{ var }}`)
- Tailwind CSS classes
- URLs and links
- Acronyms (ID, URL, API)
- Text < 2 characters

## 📊 Report Shows

```
📄 Files scanned: 45
✏️  Files modified: 12
🔑 Keys verified: 234
➕ New keys added: 28

⚠️  Missing translations: 3
🌍 New translations: 28
```

## 🔑 Key Generation

| Input | Output |
|-------|--------|
| File: `src/pages/companies/(list).vue` | Prefix: `companieslist` |
| Text: `"No companies yet"` | Key: `no_companies_yet` |
| **Result** | `companieslist.no_companies_yet` |

## 🌍 Locale Files

- **en-US.ts**: `/src/i18n/locales/en-US.ts`
- **fr-FR.ts**: `/src/i18n/locales/fr-FR.ts`

## 🔄 Workflow

1. **Run agent** → 2. **Review report** → 3. **Check git diff** → 4. **Commit or adjust**

## ⚙️ Configuration

Edit these in `scripts/i18n-agent.py`:

```python
PAGES_DIR = ROOT_DIR / "src" / "pages"
COMPONENTS_DIR = ROOT_DIR / "src" / "components"
EN_LOCALE_FILE = LOCALES_DIR / "en-US.ts"
FR_LOCALE_FILE = LOCALES_DIR / "fr-FR.ts"
```

## 🐛 Troubleshooting

| Issue | Solution |
|-------|----------|
| "No module named 'anthropic'" | `pip install anthropic` |
| "Permission denied" | `chmod +x scripts/i18n-agent.py` |
| Translation errors | Check API key or use placeholders |
| Git conflicts | Resolve manually and commit |

## 📝 Example

**Before:**
```vue
<h1>Welcome</h1>
<button>Click me</button>
```

**After:**
```vue
<h1>{{ $t('welcome') }}</h1>
<button>{{ $t('click_me') }}</button>
```

**Locale files:**
```typescript
// en-US.ts: welcome: 'Welcome', click_me: 'Click me'
// fr-FR.ts: welcome: 'Bienvenue', click_me: 'Cliquez ici'
```

## 🎯 Best Practices

1. ✅ Run on clean git state
2. ✅ Review report before committing
3. ✅ Test in dev server after running
4. ✅ Run iteratively as codebase evolves
5. ✅ Manual review for translation accuracy

## 📚 Documentation

- **Full docs**: `scripts/README.md`
- **Install guide**: `scripts/INSTALL.md`
- **Summary**: `scripts/AGENT_SUMMARY.md`
- **This card**: `scripts/QUICK_REFERENCE.md`

---

**Need help?** Read `scripts/README.md` for detailed documentation.
