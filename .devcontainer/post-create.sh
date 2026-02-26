#!/bin/bash
set -e

echo "=== Configuring internal hosts ==="
if ! grep -q 'git.mediaspeech.com' /etc/hosts; then
  GIT_HOST_IP="${GIT_MEDIASPEECH_IP:-10.0.90.130}"
  echo "${GIT_HOST_IP} git.mediaspeech.com registry.git.mediaspeech.com" | sudo tee -a /etc/hosts > /dev/null
  echo "Added git.mediaspeech.com → ${GIT_HOST_IP}"
fi

echo "=== Setting up environment ==="
test -f .env || cp .env.example .env

echo "=== Enabling Corepack (for Yarn 4) ==="
corepack enable

echo "=== Installing git hooks (husky) ==="
yarn install

echo "=== Installing frontend dependencies ==="
if [ -f apps/front/package.json ]; then
  cd apps/front
  # Create .yarnrc.yml from dist template if missing (contains private registry config)
  if [ ! -f .yarnrc.yml ] && [ -f .yarnrc.dist.yml ]; then
    cp .yarnrc.dist.yml .yarnrc.yml
    echo "⚠️  Created .yarnrc.yml from template."
    echo "   Configure registry credentials: task front:setup-yarnrc"
    echo "   (Credentials available in Passbolt — search for 'CHAPSMIND_VUELLAR')"
  fi
  yarn install || echo "⚠️  yarn install failed — run: task front:setup-yarnrc && cd apps/front && yarn install"
  cd ../..
fi

echo "=== Installing Poetry ==="
pip install poetry

echo "=== Installing backend dependencies ==="
if [ -f apps/screen/pyproject.toml ]; then
  cd apps/screen && poetry install
  cd ../..
fi

echo ""
echo "Dev environment ready!"
echo "  Run 'task' to see available commands"
echo "  Run 'task up' to start all services"