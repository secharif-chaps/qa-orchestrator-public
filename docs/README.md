# QA Orchestrator Integration v1.1.0

## What's New

✅ SessionManager agent for session persistence
✅ GherkinWriter agent for automatic Gherkin
✅ New qa-workflow as main workflow
✅ 9-phase structured QA methodology
✅ Complete documentation

## Quick Start
```bash
node index.js --project target --workflow qa-workflow --ticket TAR-1332
```

## Files Added

- agents/registry.js (updated with 2 new agents)
- core/engine.js (SessionManager integration)
- core/session-manager.js (NEW)
- docs/ folder with complete guides

## Next Steps

1. Review the agents: `agents/registry.js`
2. Check session-manager: `core/session-manager.js`
3. Try qa-workflow on a test ticket
4. Check session data in qa-sessions/

## Documentation

- QA_WORKFLOW_GUIDE.md — 9-phase methodology
- INTEGRATION_GUIDE.md — Architecture
- EXAMPLES.md — Real scenarios
- And more...

