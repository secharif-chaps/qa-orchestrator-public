# ── Base stage: system deps + poetry ─────────────────────────────────
FROM python:3.12-slim AS base

WORKDIR /app

# Install system dependencies
RUN apt-get update && apt-get install -y \
    gcc \
    libpq-dev \
    netcat-traditional \
    curl \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Install Poetry
RUN curl -sSL https://install.python-poetry.org | POETRY_HOME=/opt/poetry python3 - && \
    cd /usr/local/bin && \
    ln -s /opt/poetry/bin/poetry && \
    poetry --version

# Copy dependency files
COPY pyproject.toml poetry.lock ./

# Configure poetry to not use a virtual environment
RUN poetry config virtualenvs.create false

# ── Dev stage: all deps (test, lint, mypy) ───────────────────────────
FROM base AS dev

RUN poetry install --no-interaction --no-ansi --no-root

COPY ./app/ app/
COPY ./alembic/ alembic/
COPY ./tests/ tests/
COPY alembic.ini run.py ./
COPY ./docker/entrypoint.sh ./

RUN chmod +x entrypoint.sh && sed -i 's/\r$//' entrypoint.sh

EXPOSE 8000
ENTRYPOINT ["./entrypoint.sh"]

# ── Prod stage (default): main deps only ─────────────────────────────
FROM base AS prod

RUN poetry install --only main --no-interaction --no-ansi --no-root

COPY ./app/ app/
COPY ./alembic/ alembic/
COPY alembic.ini run.py ./
COPY ./docker/entrypoint.sh ./

# Make the entrypoint script executable and fix line endings
RUN chmod +x entrypoint.sh && sed -i 's/\r$//' entrypoint.sh

# Expose the port
EXPOSE 8000

# Set the entrypoint
ENTRYPOINT ["./entrypoint.sh"]
