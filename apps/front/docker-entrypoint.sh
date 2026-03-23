#!/bin/sh
# Runtime injection of environment variables into Vite-built JS files.
# Replaces __VITE_xxx__ placeholders with actual env var values at container startup.
# This allows a single Docker image to be deployed across all environments.
#
# All env vars starting with VITE_ are automatically injected.
# The corresponding placeholder in the built JS must be __VITE_xxx__.

set -e

HTML_DIR="/usr/share/nginx/html"

# Auto-discover all VITE_* environment variables
env | grep '^VITE_' | while IFS='=' read -r var value; do
  placeholder="__${var}__"

  if [ -z "$value" ]; then
    echo "⚠️  $var is set but empty — placeholder $placeholder will remain"
    continue
  fi

  # Escape sed special characters in value: \ & | "
  escaped_value=$(printf '%s' "$value" | sed 's/[\\&|"]/\\&/g')

  # Replace placeholder AND surrounding quotes if present
  # Handles both: "__VITE_FOO__" → "value" and __VITE_FOO__ → value
  find "$HTML_DIR" -type f \( -name '*.js' -o -name '*.html' \) \
    -exec sed -i "s|\"${placeholder}\"|\"${escaped_value}\"|g; s|${placeholder}|${escaped_value}|g" {} +
  echo "✅ $var → $value"
done

# Start nginx
exec nginx -g 'daemon off;'
