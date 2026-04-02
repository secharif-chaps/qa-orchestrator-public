#!/usr/bin/env python3
"""
Validate that all markdown files in docs/ are referenced in mkdocs.yml navigation.
This script ensures documentation is discoverable and prevents orphaned documentation files.
"""

import sys
from pathlib import Path
import yaml
import re
import subprocess


def get_all_markdown_files(docs_dir: Path) -> set[str]:
    """Get all markdown files tracked by Git in the docs directory, relative to docs/."""
    markdown_files = set()
    try:
        # Get all tracked files in docs/ directory using git ls-files
        result = subprocess.run(
            ["git", "ls-files", "docs/"],
            cwd=docs_dir.parent,
            capture_output=True,
            text=True,
            check=True,
        )

        for line in result.stdout.strip().split("\n"):
            if line and line.endswith(".md"):
                # Remove 'docs/' prefix to get relative path
                relative_path = line.replace("docs/", "", 1)
                markdown_files.add(relative_path)
    except subprocess.CalledProcessError as e:
        print(f"⚠️  Warning: Could not get Git tracked files: {e}")
        print("⚠️  Falling back to all markdown files in docs/")
        # Fallback to all files if git fails
        for md_file in docs_dir.rglob("*.md"):
            relative_path = md_file.relative_to(docs_dir)
            markdown_files.add(str(relative_path))

    return markdown_files


def extract_nav_files(nav_structure, prefix: str = "") -> set[str]:
    """Recursively extract all file paths from mkdocs navigation structure."""
    nav_files = set()

    if isinstance(nav_structure, dict):
        for key, value in nav_structure.items():
            if isinstance(value, str):
                # Direct file reference
                nav_files.add(value)
            elif isinstance(value, list):
                # Nested navigation
                nav_files.update(extract_nav_files(value, prefix))
            elif isinstance(value, dict):
                # Dictionary value
                nav_files.update(extract_nav_files(value, prefix))
    elif isinstance(nav_structure, list):
        for item in nav_structure:
            nav_files.update(extract_nav_files(item, prefix))
    elif isinstance(nav_structure, str):
        # Direct string reference
        nav_files.add(nav_structure)

    return nav_files


def main():
    """Main validation function."""
    project_root = Path(__file__).parent.parent
    docs_dir = project_root / "docs"
    mkdocs_file = project_root / "mkdocs.yml"

    # Load mkdocs.yml with custom tag handling
    try:
        # Add constructor for !ENV tag (just return the first value)
        def env_constructor(loader, node):
            if isinstance(node, yaml.SequenceNode):
                values = loader.construct_sequence(node)
                return values[0] if values else ""
            return loader.construct_scalar(node)

        # Add constructor for Python name tags (used by mkdocs-material)
        def python_name_constructor(loader, node):
            return loader.construct_scalar(node)

        yaml.SafeLoader.add_constructor("!ENV", env_constructor)
        yaml.SafeLoader.add_constructor(
            "tag:yaml.org,2002:python/name:material.extensions.emoji.twemoji",
            python_name_constructor,
        )
        yaml.SafeLoader.add_constructor(
            "tag:yaml.org,2002:python/name:material.extensions.emoji.to_svg",
            python_name_constructor,
        )
        yaml.SafeLoader.add_constructor(
            "tag:yaml.org,2002:python/name:pymdownx.superfences.fence_code_format",
            python_name_constructor,
        )

        with open(mkdocs_file, "r", encoding="utf-8") as f:
            mkdocs_config = yaml.safe_load(f)
    except Exception as e:
        print(f"❌ Error loading mkdocs.yml: {e}")
        return 1

    # Get all markdown files
    all_md_files = get_all_markdown_files(docs_dir)

    # Extract files from navigation
    nav_files = extract_nav_files(mkdocs_config.get("nav", []))

    # Find orphaned files (in docs/ but not in nav)
    orphaned_files = all_md_files - nav_files

    # Find missing files (in nav but not in docs/)
    missing_files = nav_files - all_md_files

    # Report results
    has_errors = False

    if orphaned_files:
        has_errors = True
        print("❌ ERROR: The following markdown files are not referenced in mkdocs.yml navigation:")
        print()
        for file in sorted(orphaned_files):
            print(f"  - {file}")
        print()
        print("These files will not appear in the documentation menu.")
        print("Please add them to the 'nav' section in mkdocs.yml")
        print()

    if missing_files:
        has_errors = True
        print("❌ ERROR: The following files are referenced in mkdocs.yml but don't exist:")
        print()
        for file in sorted(missing_files):
            print(f"  - {file}")
        print()
        print("Please remove these references from mkdocs.yml or create the files.")
        print()

    if not has_errors:
        print("✅ All markdown files are properly referenced in mkdocs.yml navigation")
        return 0

    return 1


if __name__ == "__main__":
    sys.exit(main())
