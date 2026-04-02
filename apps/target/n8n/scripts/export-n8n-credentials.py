#!/usr/bin/env python3
"""
Script to export n8n credentials using the n8n CLI backup command
and place them in docker/n8n/credentials with kebab-case naming.
"""

import json
import os
import re
import shutil
import sys
import argparse
import subprocess
import tempfile
from pathlib import Path
from typing import List, Tuple


def to_kebab_case(name: str) -> str:
    """Convert a string to kebab-case."""
    # Remove special characters and replace spaces/hyphens/underscores with single hyphens
    kebab = re.sub(r'[^\w\s-]', '', name)
    kebab = re.sub(r'[-\s_]+', '-', kebab)
    kebab = re.sub(r'^-+|-+$', '', kebab)  # Remove leading/trailing hyphens
    return kebab.lower()


def get_credential_name(json_content: dict) -> str:
    """Extract the credential name from the JSON content."""
    return json_content.get('name', 'unknown-credential')


def validate_credential_file(file_path: Path) -> Tuple[bool, str]:
    """Validate that a file is a valid n8n credential JSON."""
    try:
        with open(file_path, 'r', encoding='utf-8') as f:
            content = json.load(f)

        if not isinstance(content, dict):
            return False, "Content is not a JSON object"

        if 'name' not in content:
            return False, "Missing 'name' field"

        return True, ""
    except json.JSONDecodeError as e:
        return False, f"Invalid JSON: {e}"
    except Exception as e:
        return False, f"Error reading file: {e}"


def run_docker_compose_command(cmd: List[str], capture_output: bool = True) -> Tuple[int, str, str]:
    """Run a docker compose command and return (return_code, stdout, stderr)."""
    try:
        result = subprocess.run(
            ['docker', 'compose', 'exec', '-T', 'n8n'] + cmd,
            capture_output=capture_output,
            text=True,
            timeout=300  # 5 minute timeout
        )
        return result.returncode, result.stdout, result.stderr
    except subprocess.TimeoutExpired:
        return 1, "", "Command timed out after 5 minutes"
    except Exception as e:
        return 1, "", f"Error running docker compose command: {e}"


def export_credentials_backup(temp_dir: Path) -> bool:
    """Export all credentials using n8n backup command."""
    print("Exporting credentials using n8n backup command...")

    # Export all credentials to temp directory using backup command
    returncode, stdout, stderr = run_docker_compose_command([
        'n8n', 'export:credentials', '--backup', '--output', '/tmp/credentials'
    ])

    if returncode != 0:
        print(f"Error exporting credentials: {stderr}")
        return False

    # Copy exported files from container to host
    try:
        subprocess.run([
            'docker', 'compose', 'cp', 'n8n:/tmp/credentials', str(temp_dir)
        ], check=True, capture_output=True)
        return True
    except subprocess.CalledProcessError as e:
        print(f"Error copying credential files: {e}")
        return False


def get_credential_files(credentials_dir: Path) -> List[Path]:
    """Get all valid credential JSON files from the credentials directory."""
    credential_files = []

    if not credentials_dir.exists():
        print(f"Error: Credentials directory does not exist: {credentials_dir}")
        return credential_files

    for file_path in credentials_dir.glob("*.json"):
        is_valid, error_msg = validate_credential_file(file_path)
        if is_valid:
            credential_files.append(file_path)
        else:
            print(f"Warning: Skipping {file_path.name}: {error_msg}")

    return credential_files


def preview_export(credential_files: List[Path], target_dir: Path) -> List[Tuple[Path, str]]:
    """Preview the export operation and return list of (source, target_name) tuples."""
    export_operations = []

    print("Preview of export operations:")
    print("=" * 50)

    for credential_file in credential_files:
        try:
            with open(credential_file, 'r', encoding='utf-8') as f:
                content = json.load(f)

            credential_name = get_credential_name(content)
            kebab_name = to_kebab_case(credential_name)
            target_filename = f"{kebab_name}.json"
            target_path = target_dir / target_filename

            export_operations.append((credential_file, target_filename))

            print(f"  {credential_file.name} -> {target_filename}")
            print(f"    Credential name: '{credential_name}'")
            print(f"    Target path: {target_path}")
            print()

        except Exception as e:
            print(f"Error processing {credential_file.name}: {e}")

    return export_operations


def perform_export(export_operations: List[Tuple[Path, str]], target_dir: Path) -> None:
    """Perform the actual export operation."""
    print("Performing export operations:")
    print("=" * 50)

    for source_file, target_filename in export_operations:
        target_path = target_dir / target_filename

        try:
            # Copy the file with new name
            shutil.copy2(source_file, target_path)
            print(f"✓ Exported: {source_file.name} -> {target_filename}")
        except Exception as e:
            print(f"✗ Failed to export {source_file.name}: {e}")


def cleanup_temp_files(temp_files: List[Path]) -> None:
    """Clean up any temporary files created during the process."""
    if not temp_files:
        return

    print("\nCleaning up temporary files:")
    for temp_file in temp_files:
        try:
            if temp_file.exists():
                temp_file.unlink()
                print(f"✓ Removed: {temp_file}")
        except Exception as e:
            print(f"✗ Failed to remove {temp_file}: {e}")


def main():
    parser = argparse.ArgumentParser(
        description="Export n8n credentials using n8n CLI backup and place in credentials directory with kebab-case naming"
    )
    parser.add_argument(
        "--dry-run",
        action="store_true",
        help="Preview the export operations without actually performing them"
    )
    parser.add_argument(
        "--credentials-dir",
        type=Path,
        default=Path("docker/n8n/credentials"),
        help="Directory to export credentials to (default: docker/n8n/credentials)"
    )

    args = parser.parse_args()

    # Validate directories
    if not args.credentials_dir.exists():
        print(f"Error: Credentials directory does not exist: {args.credentials_dir}")
        sys.exit(1)

    # Check if n8n service is running
    try:
        result = subprocess.run(
            ['docker', 'compose', 'ps', 'n8n'],
            capture_output=True,
            text=True
        )
        if 'Up' not in result.stdout:
            print("Error: n8n service is not running. Please start it with 'docker compose up n8n'")
            sys.exit(1)
    except Exception as e:
        print(f"Error checking n8n service status: {e}")
        sys.exit(1)

    # Export credentials to temp directory
    with tempfile.TemporaryDirectory() as temp_dir:
        temp_path = Path(temp_dir)

        if not export_credentials_backup(temp_path):
            print("Failed to export credentials from n8n.")
            sys.exit(1)

        # Get exported credential files
        exported_dir = temp_path / "credentials"
        if not exported_dir.exists():
            print("No credentials directory found in exported files.")
            sys.exit(1)

        credential_files = get_credential_files(exported_dir)

        if not credential_files:
            print("No valid credential files found in export.")
            sys.exit(1)

        print(f"Found {len(credential_files)} valid credential file(s)")
        print()

        # Preview export operations
        export_operations = preview_export(credential_files, args.credentials_dir)

        if not export_operations:
            print("No valid credentials to export.")
            sys.exit(1)

        # Perform export if not dry-run
        if not args.dry_run:
            print("\n" + "=" * 50)
            response = input("Proceed with export? (y/N): ").strip().lower()

            if response in ['y', 'yes']:
                perform_export(export_operations, args.credentials_dir)
                print(f"\n✓ Export completed successfully!")
            else:
                print("Export cancelled.")
        else:
            print(f"\nDry-run completed. {len(export_operations)} credential(s) would be exported.")
            print("Run without --dry-run to perform the actual export.")


if __name__ == "__main__":
    main()
