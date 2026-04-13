#!/bin/sh
set -e

CA_CERT_FILE=${CA_CERT_FILE:-"/certs/ca-cert.pem"}
CA_CERT_FILE_TARGET="/usr/local/share/ca-certificates/custom-ca-cert.crt"

# Add CA certificate if provided and valid
if [ -f "$CA_CERT_FILE" ] && [ ! -f "$CA_CERT_FILE_TARGET" ]; then
	echo "Adding CA certificate: $CA_CERT_FILE"

	# Validate certificate format
	if ! openssl x509 -in "$CA_CERT_FILE" -noout -text >/dev/null 2>&1; then
		echo "WARNING: Invalid CA certificate format, skipping"
	else
		# Ensure target directory exists and is writable
		TARGET_DIR="$(dirname "$CA_CERT_FILE_TARGET")"
		if [ ! -d "$TARGET_DIR" ]; then
			mkdir -p "$TARGET_DIR" || exit 1
		fi

		if [ ! -w "$TARGET_DIR" ]; then
			echo "ERROR: Cannot write to CA certificates directory: $TARGET_DIR"
			exit 1
		fi

		# Copy and update certificates
		cp "$CA_CERT_FILE" "$CA_CERT_FILE_TARGET" || exit 1
		update-ca-certificates || echo "WARNING: Failed to update CA certificates"
		echo "CA certificate installed successfully"
	fi
elif [ -f "$CA_CERT_FILE_TARGET" ]; then
	echo "CA certificate already installed"
fi

if [ "$1" = 'frankenphp' ] || [ "$1" = 'php' ] || [ "$1" = 'bin/console' ]; then
    if [ "$APP_ENV" = "prod" ]; then
        composer install --prefer-dist --no-progress --no-interaction --no-dev --optimize-autoloader --classmap-authoritative
    else
        composer install --prefer-dist --no-progress --no-interaction
    fi

	if grep -q ^DATABASE_URL= .env; then
		echo "Waiting for database to be ready..."
		ATTEMPTS_LEFT_TO_REACH_DATABASE=60
		until [ $ATTEMPTS_LEFT_TO_REACH_DATABASE -eq 0 ] || DATABASE_ERROR=$(php bin/console dbal:run-sql "SELECT 1" 2>&1); do
			if [ $? -eq 255 ]; then
				# If the Doctrine command exits with 255, an unrecoverable error occurred
				ATTEMPTS_LEFT_TO_REACH_DATABASE=0
				break
			fi
			sleep 1
			ATTEMPTS_LEFT_TO_REACH_DATABASE=$((ATTEMPTS_LEFT_TO_REACH_DATABASE - 1))
			echo "Still waiting for database to be ready... Or maybe the database is not reachable. $ATTEMPTS_LEFT_TO_REACH_DATABASE attempts left."
		done

		if [ $ATTEMPTS_LEFT_TO_REACH_DATABASE -eq 0 ]; then
			echo "The database is not up or not reachable:"
			echo "$DATABASE_ERROR"
			exit 1
		else
			echo "The database is now ready and reachable"
		fi

		if [ "$( find ./migrations/doctrine -iname '*.php' -print -quit )" ]; then
			php bin/console doctrine:migrations:migrate --no-interaction --all-or-nothing
		fi

		# Setup Messenger transports (creates the failed queue table if using Doctrine transport)
		php bin/console messenger:setup-transports --no-interaction
	fi

	if grep -q ^OPENSEARCH_URL= .env; then
		echo "Waiting for OpenSearch to be ready..."
		ATTEMPTS_LEFT_TO_REACH_OPENSEARCH=60
		until [ $ATTEMPTS_LEFT_TO_REACH_OPENSEARCH -eq 0 ] || OPENSEARCH_ERROR=$(php bin/console opensearch:check-connection 2>&1); do
			if [ $? -eq 255 ]; then
				# If the OpenSearch command exits with 255, an unrecoverable error occurred
				ATTEMPTS_LEFT_TO_REACH_OPENSEARCH=0
				break
			fi
			sleep 1
			ATTEMPTS_LEFT_TO_REACH_OPENSEARCH=$((ATTEMPTS_LEFT_TO_REACH_OPENSEARCH - 1))
			echo "Still waiting for OpenSearch to be ready... Or maybe OpenSearch is not reachable. $ATTEMPTS_LEFT_TO_REACH_OPENSEARCH attempts left."
		done

		if [ $ATTEMPTS_LEFT_TO_REACH_OPENSEARCH -eq 0 ]; then
			echo "OpenSearch is not up or not reachable:"
			echo "$OPENSEARCH_ERROR"
			exit 1
		else
			echo "OpenSearch is now ready and reachable"
		fi

		echo "Searching for OpenSearch migrations"
		if [ "$( find ./migrations/opensearch -iname '*.php' -print -quit )" ]; then
			echo "Executing OpenSearch migrations..."
			php bin/console opensearch:migrations:migrate --no-interaction --force
		fi
	fi

	# Announce to global-service gateway registry (non-blocking, best-effort)
	if [ -n "${GLOBAL_SERVICE_URL:-}" ]; then
		echo "Announcing to gateway registry..."
		php bin/console app:gateway:announce || echo "WARNING: Gateway announce failed (will be detected via healthcheck)"
	fi

	if [ "${PLATFORM:-linux}" = "linux" ]; then
		setfacl -R -m u:www-data:rwX -m u:"$(whoami)":rwX var
		setfacl -dR -m u:www-data:rwX -m u:"$(whoami)":rwX var
		echo "✓ ACL configured successfully"
	else
		echo "setfacl not available, using chmod..."
		chmod -R 775 var
		chown -R www-data:www-data var
		echo "✓ Permissions set with chmod"
	fi
fi

exec docker-php-entrypoint "$@"
