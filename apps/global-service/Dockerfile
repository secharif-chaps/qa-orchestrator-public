FROM python:3.12-slim AS base

ARG VERSION=dev
LABEL org.opencontainers.image.title="chapsmind-global-service" \
      org.opencontainers.image.description="ChapsMind API gateway — auth, routing, token management, user/org admin" \
      org.opencontainers.image.version="${VERSION}" \
      org.opencontainers.image.vendor="ChapsVision" \
      org.opencontainers.image.authors="ChapsVision <dev-chapsmind@chapsvision.com>" \
      org.opencontainers.image.url="https://git.mediaspeech.com/chapsmind/chapsmind" \
      org.opencontainers.image.source="https://git.mediaspeech.com/chapsmind/chapsmind/-/tree/main/apps/global-service" \
      org.opencontainers.image.licenses="Proprietary" \
      org.opencontainers.image.base.name="docker.io/library/python:3.12-slim"

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

# ── Dev stage: all deps (pytest, ruff, mypy, etc.) ───────────────────
FROM base AS dev

RUN poetry install --no-interaction --no-ansi --no-root

COPY ./protos/ protos/
COPY ./scripts/ scripts/
COPY ./app/ app/
COPY ./alembic/ alembic/
COPY ./tests/ tests/
COPY alembic.ini run.py ./
COPY ./docker/entrypoint.sh ./

# Generate gRPC protos at BUILD TIME (not runtime)
RUN python scripts/generate_protos.py

RUN chmod +x entrypoint.sh && sed -i 's/\r$//' entrypoint.sh

EXPOSE 8000 50051
ENTRYPOINT ["./entrypoint.sh"]

# ── Prod stage (default): main deps only ─────────────────────────────
FROM base AS prod

RUN poetry install --only main --no-interaction --no-ansi --no-root

COPY ./protos/ protos/
COPY ./scripts/ scripts/
COPY ./app/ app/
COPY ./alembic/ alembic/
COPY alembic.ini run.py ./
COPY ./docker/entrypoint.sh ./

# Generate gRPC protos at BUILD TIME (not runtime)
RUN python scripts/generate_protos.py

RUN chmod +x entrypoint.sh && sed -i 's/\r$//' entrypoint.sh

EXPOSE 8000 50051
ENTRYPOINT ["./entrypoint.sh"]
