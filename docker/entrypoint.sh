#!/bin/sh

# Run database migrations
echo "Running database migrations..."
alembic upgrade head

# Generate gRPC protos ONLY if they don't exist
if [ ! -f "/app/app/grpc_generated/organization_pb2.py" ]; then
    echo "🔹 Generating gRPC protos..."
    python scripts/generate_protos.py
else
    echo "✅ gRPC protos already exist, skipping generation"
fi

# Start the application
echo "Starting the application..."
exec python run.py 