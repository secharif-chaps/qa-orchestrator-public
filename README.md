# Mint local deployment

## Installation

```bash
git clone --recurse-submodules ssh://git@git.mediaspeech.com:17890/mint/infra.git
git submodule foreach git checkout main
docker compose up -d
```

## Upgrade

```bash
git submodule update --remote --recursive
docker compose pull
docker compose up -d
```
