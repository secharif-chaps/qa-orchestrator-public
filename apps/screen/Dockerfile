FROM python:3.12-slim AS base

ARG VERSION=dev
LABEL org.opencontainers.image.title="chapsmind-screen" \
      org.opencontainers.image.description="ChapsMind screen backend — FastAPI service for company data and AI workflows" \
      org.opencontainers.image.version="${VERSION}" \
      org.opencontainers.image.vendor="ChapsVision" \
      org.opencontainers.image.authors="ChapsVision <dev-chapsmind@chapsvision.com>" \
      org.opencontainers.image.url="https://git.mediaspeech.com/chapsmind/chapsmind" \
      org.opencontainers.image.source="https://git.mediaspeech.com/chapsmind/chapsmind/-/tree/main/apps/screen" \
      org.opencontainers.image.licenses="Proprietary" \
      org.opencontainers.image.base.name="docker.io/library/python:3.12-slim"

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

# ── Dev stage: all deps (pytest, ruff, mypy, etc.) ───────────────────
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

RUN chmod +x entrypoint.sh && sed -i 's/\r$//' entrypoint.sh

EXPOSE 8000
ENTRYPOINT ["./entrypoint.sh"]
