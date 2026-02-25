# Sync Repositories

Checkout main branch and pull latest changes for both frontend and backend repositories.

## Instructions

1. For the **frontend** repository (`mint-front`):
   - Stash any uncommitted changes if present
   - Checkout the `main` branch
   - Pull latest changes from origin

2. For the **backend** repository (`mint-server`):
   - Stash any uncommitted changes if present
   - Checkout the `main` branch
   - Pull latest changes from origin

3. Report the status of both repositories after sync

## Paths

- Frontend: `/Users/nicolasmercier/dev/mint-new/mint-front`
- Backend: `/Users/nicolasmercier/dev/mint-new/mint-server`

## Commands to run

```bash
# Frontend
cd /Users/nicolasmercier/dev/mint-new/mint-front && git stash && git checkout main && git pull

# Backend
cd /Users/nicolasmercier/dev/mint-new/mint-server && git stash && git checkout main && git pull
```

After running both commands, show the current branch and latest commit for each repo.
