#!/bin/bash
set -e

echo "=== Configuring internal hosts ==="
if ! grep -q 'git.mediaspeech.com' /etc/hosts; then
  GIT_HOST_IP="${GIT_MEDIASPEECH_IP:-10.0.90.130}"
  echo "${GIT_HOST_IP} git.mediaspeech.com registry.git.mediaspeech.com" | sudo tee -a /etc/hosts > /dev/null
  echo "Added git.mediaspeech.com → ${GIT_HOST_IP}"
fi

echo "=== Running task init ==="
corepack enable
task init