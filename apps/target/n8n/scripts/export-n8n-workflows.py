#!/usr/bin/env python3
"""
Script to export n8n workflows using the n8n CLI backup command
and place them in docker/n8n/workflows with kebab-case naming.
"""

import json
import re
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


def get_workflow_name(json_content: dict) -> str:
    """Extract the workflow name from the JSON content."""
    return json_content.get('name', 'unknown-workflow')


def clean_workflow_data(content: dict) -> dict:
    """Remove non-shareable data from workflow to reduce file size and improve shareability.

    Removes:
    - pinData: debug/test data pinned to nodes
    - shared: instance-specific user/project ownership info (emails, project IDs, roles)
    """
    # Remove pinData at workflow level
    if 'pinData' in content:
        del content['pinData']

    # Remove shared (contains user emails, project IDs, ownership info)
    if 'shared' in content:
        del content['shared']

    # Remove pinData from each node
    if 'nodes' in content and isinstance(content['nodes'], list):
        for node in content['nodes']:
            if isinstance(node, dict) and 'pinData' in node:
                del node['pinData']

    return content


# Metadata fields that should be ignored when comparing workflows
METADATA_FIELDS = {
    'id',
    'versionId',
    'updatedAt',
    'createdAt',
    'meta',  # Contains instanceId and other instance-specific data
    'triggerCount',
    'staticData',  # Runtime state data
}


def get_comparable_content(content: dict) -> dict:
    """Extract only the meaningful content from a workflow for comparison.

    Removes metadata fields that change without actual workflow modifications.
    """
    comparable = {}

    for key, value in content.items():
        if key not in METADATA_FIELDS:
            comparable[key] = value

    return comparable


def workflows_are_equal(new_content: dict, existing_path: Path) -> bool:
    """Compare two workflows ignoring metadata fields.

    Returns True if the workflows have the same meaningful content.
    """
    if not existing_path.exists():
        return False

    try:
        with open(existing_path, 'r', encoding='utf-8') as f:
            existing_content = json.load(f)
    except (json.JSONDecodeError, IOError):
        return False

    new_comparable = get_comparable_content(new_content)
    existing_comparable = get_comparable_content(existing_content)

    # Compare as JSON strings for deep equality
    return json.dumps(new_comparable, sort_keys=True) == json.dumps(existing_comparable, sort_keys=True)


def validate_workflow_file(file_path: Path) -> Tuple[bool, str]:
    """Validate that a file is a valid n8n workflow JSON."""
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


def export_workflows_backup(temp_dir: Path) -> bool:
    """Export all workflows using n8n backup command."""
    print("Exporting workflows using n8n backup command...")

    # Export all workflows to temp directory using backup command
    returncode, stdout, stderr = run_docker_compose_command([
        'n8n', 'export:workflow', '--backup', '--output', '/tmp/workflows'
    ])

    if returncode != 0:
        print(f"Error exporting workflows: {stderr}")
        return False

    # Copy exported files from container to host
    try:
        subprocess.run([
            'docker', 'compose', 'cp', 'n8n:/tmp/workflows', str(temp_dir)
        ], check=True, capture_output=True)
        return True
    except subprocess.CalledProcessError as e:
        print(f"Error copying workflow files: {e}")
        return False


def get_workflow_files(workflows_dir: Path) -> List[Path]:
    """Get all valid workflow JSON files from the workflows directory."""
    workflow_files = []

    if not workflows_dir.exists():
        print(f"Error: Workflows directory does not exist: {workflows_dir}")
        return workflow_files

    for file_path in workflows_dir.glob("*.json"):
        is_valid, error_msg = validate_workflow_file(file_path)
        if is_valid:
            workflow_files.append(file_path)
        else:
            print(f"Warning: Skipping {file_path.name}: {error_msg}")

    return workflow_files


def preview_export(workflow_files: List[Path], target_dir: Path) -> List[Tuple[Path, str]]:
    """Preview the export operation and return list of (source, target_name) tuples."""
    export_operations = []

    print("Preview of export operations:")
    print("=" * 50)

    for workflow_file in workflow_files:
        try:
            with open(workflow_file, 'r', encoding='utf-8') as f:
                content = json.load(f)

            workflow_name = get_workflow_name(content)
            kebab_name = to_kebab_case(workflow_name)
            target_filename = f"{kebab_name}.json"
            target_path = target_dir / target_filename

            export_operations.append((workflow_file, target_filename))

            print(f"  {workflow_file.name} -> {target_filename}")
            print(f"    Workflow name: '{workflow_name}'")
            print(f"    Target path: {target_path}")
            print()

        except Exception as e:
            print(f"Error processing {workflow_file.name}: {e}")

    return export_operations


def perform_export(export_operations: List[Tuple[Path, str]], target_dir: Path) -> Tuple[int, int, int]:
    """Perform the actual export operation.

    Returns a tuple of (exported_count, skipped_count, failed_count).
    """
    print("Performing export operations:")
    print("=" * 50)

    exported = 0
    skipped = 0
    failed = 0

    for source_file, target_filename in export_operations:
        target_path = target_dir / target_filename

        try:
            # Read and clean the workflow
            with open(source_file, 'r', encoding='utf-8') as f:
                content = json.load(f)

            # Remove pinData and other non-shareable data
            cleaned_content = clean_workflow_data(content)

            # Check if the workflow has actually changed (ignoring metadata)
            if workflows_are_equal(cleaned_content, target_path):
                print(f"- Skipped: {target_filename} (no meaningful changes)")
                skipped += 1
                continue

            with open(target_path, 'w', encoding='utf-8') as f:
                json.dump(cleaned_content, f, indent=2, ensure_ascii=False)

            print(f"✓ Exported: {source_file.name} -> {target_filename}")
            exported += 1
        except Exception as e:
            print(f"✗ Failed to export {source_file.name}: {e}")
            failed += 1

    return exported, skipped, failed


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
        description="Export n8n workflows using n8n CLI backup and place in workflows directory with kebab-case naming"
    )
    parser.add_argument(
        "--dry-run",
        action="store_true",
        help="Preview the export operations without actually performing them"
    )
    parser.add_argument(
        "--workflows-dir",
        type=Path,
        default=Path("docker/n8n/workflows"),
        help="Directory to export workflows to (default: docker/n8n/workflows)"
    )

    args = parser.parse_args()

    # Validate directories
    if not args.workflows_dir.exists():
        print(f"Error: Workflows directory does not exist: {args.workflows_dir}")
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

    # Export workflows to temp directory
    with tempfile.TemporaryDirectory() as temp_dir:
        temp_path = Path(temp_dir)

        if not export_workflows_backup(temp_path):
            print("Failed to export workflows from n8n.")
            sys.exit(1)

        # Get exported workflow files
        exported_dir = temp_path / "workflows"
        if not exported_dir.exists():
            print("No workflows directory found in exported files.")
            sys.exit(1)

        workflow_files = get_workflow_files(exported_dir)

        if not workflow_files:
            print("No valid workflow files found in export.")
            sys.exit(1)

        print(f"Found {len(workflow_files)} valid workflow file(s)")
        print()

        # Preview export operations
        export_operations = preview_export(workflow_files, args.workflows_dir)

        if not export_operations:
            print("No valid workflows to export.")
            sys.exit(1)

        # Perform export if not dry-run
        if not args.dry_run:
            print("\n" + "=" * 50)
            response = input("Proceed with export? (y/N): ").strip().lower()

            if response in ['y', 'yes']:
                exported, skipped, failed = perform_export(export_operations, args.workflows_dir)
                print(f"\n✓ Export completed: {exported} exported, {skipped} skipped (unchanged), {failed} failed")
            else:
                print("Export cancelled.")
        else:
            print(f"\nDry-run completed. {len(export_operations)} workflow(s) would be exported.")
            print("Run without --dry-run to perform the actual export.")


if __name__ == "__main__":
    main()
