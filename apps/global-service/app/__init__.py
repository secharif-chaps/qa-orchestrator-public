"""Single source of truth for the application version.

Reads ``pyproject.toml`` directly. ``importlib.metadata`` is not usable here
because all three services run under Poetry's ``package-mode = false``, which
skips installing the project itself — so ``version()`` would always raise.
"""

import logging
import tomllib
from pathlib import Path


def _read_version() -> str:
    try:
        pyproject = Path(__file__).resolve().parent.parent / "pyproject.toml"
        with pyproject.open("rb") as fh:
            return str(tomllib.load(fh)["tool"]["poetry"]["version"])
    except Exception as exc:  # noqa: BLE001
        logging.getLogger(__name__).warning("Could not read version from pyproject.toml: %s", exc)
        return "unknown"


__version__ = _read_version()
