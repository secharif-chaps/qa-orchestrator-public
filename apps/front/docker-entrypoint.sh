#!/bin/sh
# Runtime injection of environment variables into Vite-built JS files.
# Replaces __VITE_xxx__ placeholders with actual env var values at container startup.
# This allows a single Docker image to be deployed across all environments.

set -e

HTML_DIR="/usr/share/nginx/html"

# List of VITE_ variables to inject
VARS="VITE_BASE_URL VITE_API_URL VITE_KEYCLOAK_REALM VITE_KEYCLOAK_CLIENT_ID VITE_KEYCLOAK_URL"

for var in $VARS; do
  value=$(eval echo "\$$var")
  placeholder="__${var}__"

  if [ -z "$value" ]; then
    echo "⚠️  $var is not set — placeholder $placeholder will remain in JS files"
    continue
  fi

  # Replace in all JS and HTML files
  find "$HTML_DIR" -type f \( -name '*.js' -o -name '*.html' \) -exec sed -i "s|${placeholder}|${value}|g" {} +
  echo "✅ $var → $value"
done

# Start nginx
exec nginx -g 'daemon off;'