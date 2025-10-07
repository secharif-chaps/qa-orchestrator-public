#!/bin/bash

# Database migration script for Mint Backend
# This script can be run manually or as part of deployment

set -e  # Exit on any error

echo "🚀 Starting database migration process..."

# Check if we're in a Docker container
if [ -f /.dockerenv ]; then
    echo "📦 Running inside Docker container"
    
    # Wait for PostgreSQL to be ready
    echo "⏳ Waiting for PostgreSQL..."
    while ! nc -z db 5432; do
        sleep 1
    done
    echo "✅ PostgreSQL is ready"
    
    # Run migrations
    echo "🔄 Running Alembic migrations..."
    alembic upgrade head
    
    echo "✅ Migrations completed successfully!"
    
else
    echo "💻 Running on host system"
    
    # Check if alembic is available
    if ! command -v alembic &> /dev/null; then
        echo "❌ Alembic not found. Please install dependencies first."
        exit 1
    fi
    
    # Run migrations
    echo "🔄 Running Alembic migrations..."
    alembic upgrade head
    
    echo "✅ Migrations completed successfully!"
fi

echo "🎉 Database migration process completed!"