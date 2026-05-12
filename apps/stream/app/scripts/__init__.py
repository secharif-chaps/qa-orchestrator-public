"""One-shot operator scripts for the Stream service.

These scripts are not part of the running service — they are CLI entry
points executed inside the container (`docker compose exec stream
python -m app.scripts.<name>`) for smoke tests, manual recovery, and
ad-hoc operations.
"""
