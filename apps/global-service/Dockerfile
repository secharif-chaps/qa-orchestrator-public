FROM python:3.12-slim

WORKDIR /app

# System dependencies
RUN apt-get update && apt-get install -y \
    gcc \
    libpq-dev \
    curl \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# TODO: Consider migrating to uv for faster builds (10-100x faster than Poetry)
# Install Poetry
RUN curl -sSL https://install.python-poetry.org | POETRY_HOME=/opt/poetry python3 - && \
    ln -s /opt/poetry/bin/poetry /usr/local/bin/poetry && \
    poetry --version

# Copy dependency files
COPY pyproject.toml poetry.lock ./

# Disable venv inside container
RUN poetry config virtualenvs.create false

# Install dependencies
RUN poetry install --no-interaction --no-ansi --no-root

# Copy app code
COPY ./protos/ protos/
COPY ./scripts/ scripts/
COPY ./app/ app/
COPY ./alembic/ alembic/
COPY alembic.ini run.py ./
COPY ./docker/entrypoint.sh ./

# Generate gRPC protos at BUILD TIME (not runtime)
RUN python scripts/generate_protos.py

# Make entrypoint executable
RUN chmod +x entrypoint.sh && sed -i 's/\r$//' entrypoint.sh

EXPOSE 8001 50051

ENTRYPOINT ["./entrypoint.sh"]
