#!/bin/bash
set -e

echo "=== Enabling Corepack (for Yarn 4) ==="
corepack enable

echo "=== Installing frontend dependencies ==="
if [ -f apps/front/package.json ]; then
  cd apps/front && yarn install
  cd ../..
fi

echo "=== Installing Poetry ==="
pip install poetry

echo "=== Installing backend dependencies ==="
if [ -f apps/screen/pyproject.toml ]; then
  cd apps/screen && poetry install
  cd ../..
fi

echo "=== Installing Lefthook ==="
curl -1sLf 'https://dl.cloudsmith.io/public/evilmartians/lefthook/setup.deb.sh' | sudo -E bash
sudo apt-get install -y lefthook
lefthook install

echo "=== Setting up environment ==="
test -f .env || cp .env.example .env

echo ""
echo "Dev environment ready!"
echo "  Run 'task' to see available commands"
echo "  Run 'task up' to start all services"