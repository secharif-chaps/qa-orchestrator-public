# Quick Install Guide

## 1. Install Dependencies

```bash
pip install anthropic
```

## 2. Set API Key (Optional)

For automatic French translations using Claude AI:

```bash
export ANTHROPIC_API_KEY="your-anthropic-api-key"
```

> **Note**: Without an API key, the agent will use placeholder translations like `[FR] Text` that you'll need to translate manually.

## 3. Run the Agent

```bash
# Navigate to mint-front directory
cd /Users/nicolasmercier/dev/mint-new/mint-front

# Run the agent
python3 scripts/i18n-agent.py
```

## 4. Review and Commit

The agent will:

1. Scan all Vue files
2. Show you a detailed report
3. Ask if you want to commit changes

**That's it!** 🎉

---

## Example Run

```bash
$ python3 scripts/i18n-agent.py

🚀 Starting i18n Translation Agent
📂 Root directory: /Users/nicolasmercier/dev/mint-new/mint-front
🌍 Locales: en-US.ts, fr-FR.ts

Scanning pages directory...
📄 Scanning: src/pages/(home).vue
📄 Scanning: src/pages/companies/(list).vue
...

============================================================
📊 i18n Translation Agent Report
============================================================

📄 Files scanned: 45
✏️  Files modified: 12
🔑 Keys verified: 234
➕ New keys added: 28

🌍 New translations added: 28
   ...

Do you want to commit these changes? (y/n): y
✅ Changes committed successfully
```

## Troubleshooting

### "No module named 'anthropic'"

```bash
pip install anthropic
```

### "Permission denied"

```bash
chmod +x scripts/i18n-agent.py
```

### Want to see what changed before committing?

```bash
# Run agent but answer "n" to commit prompt
python3 scripts/i18n-agent.py

# Review changes
git diff

# Commit manually if satisfied
git add .
git commit -m "🌍 i18n: add translations"
```
