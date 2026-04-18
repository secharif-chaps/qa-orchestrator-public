#!/usr/bin/env python3
"""Fail if any `logger.<level>(..., extra={...})` call overwrites a reserved
LogRecord attribute.

Python's logging library raises at runtime on these collisions:

    KeyError: "Attempt to overwrite 'module' in LogRecord"

…which turns every matching code path into a 500. Easier to catch statically.
Parses with `ast` instead of regex so multiline dicts, `dict(...)` keyword
form, and nested calls are all covered.

Usage:
    python3 .gitlab/scripts/check-logger-extra.py                 # scans apps/
    python3 .gitlab/scripts/check-logger-extra.py FILE [FILE...]  # scans those files
"""

from __future__ import annotations

import ast
import sys
from pathlib import Path

# Attributes set by the logging library on every LogRecord. Any key in an
# `extra=` dict that matches one of these raises at runtime.
# Source: https://docs.python.org/3/library/logging.html#logrecord-attributes
RESERVED_ATTRS = frozenset({
    "name", "msg", "args", "levelname", "levelno", "pathname", "filename",
    "module", "exc_info", "exc_text", "stack_info", "lineno", "funcName",
    "created", "msecs", "relativeCreated", "thread", "threadName",
    "processName", "process", "message", "asctime",
})

LOG_METHODS = frozenset({"debug", "info", "warning", "error", "critical", "exception", "log"})


def _extra_keys(node: ast.AST) -> list[str]:
    """Return string keys of `extra=` payload (dict literal or `dict(...)` call)."""
    if isinstance(node, ast.Dict):
        return [
            k.value
            for k in node.keys
            if isinstance(k, ast.Constant) and isinstance(k.value, str)
        ]
    if (
        isinstance(node, ast.Call)
        and isinstance(node.func, ast.Name)
        and node.func.id == "dict"
    ):
        return [kw.arg for kw in node.keywords if kw.arg is not None]
    return []


def _scan_file(path: Path) -> list[tuple[int, str, str]]:
    try:
        tree = ast.parse(path.read_text(encoding="utf-8"))
    except SyntaxError:
        return []

    hits: list[tuple[int, str, str]] = []
    for node in ast.walk(tree):
        if not isinstance(node, ast.Call):
            continue
        # logger.info(...), self.log.warning(...), etc.
        if not isinstance(node.func, ast.Attribute) or node.func.attr not in LOG_METHODS:
            continue
        for kw in node.keywords:
            if kw.arg != "extra":
                continue
            for key in _extra_keys(kw.value):
                if key in RESERVED_ATTRS:
                    hits.append((node.lineno, node.func.attr, key))
    return hits


def _iter_targets(paths: list[str]) -> list[Path]:
    targets: list[Path] = []
    for raw in paths:
        p = Path(raw)
        if p.is_dir():
            targets.extend(
                f for f in p.rglob("*.py")
                if "/.venv/" not in str(f) and "/site-packages/" not in str(f)
            )
        elif p.suffix == ".py" and p.is_file():
            targets.append(p)
    return targets


def main(argv: list[str]) -> int:
    if len(argv) <= 1:
        paths = ["apps"]
    else:
        paths = argv[1:]

    any_hit = False
    for py in _iter_targets(paths):
        for lineno, method, key in _scan_file(py):
            any_hit = True
            print(
                f"✗ {py}:{lineno}: logger.{method}(..., extra={{'{key}': ...}}) "
                f"— '{key}' is a reserved LogRecord attribute",
                file=sys.stderr,
            )

    if any_hit:
        print(
            "\nRename the colliding key to something unambiguous "
            "(e.g. 'module' → 'target_module').",
            file=sys.stderr,
        )
        return 1
    return 0


if __name__ == "__main__":
    sys.exit(main(sys.argv))